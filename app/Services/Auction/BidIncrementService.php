<?php

declare(strict_types=1);

namespace App\Services\Auction;

use App\Models\Auction;

/**
 * The single implementation of the auction's bid-increment ladder.
 *
 * `auctions.bid_rules` is a JSON list of {from, to, increment} bands. Resolving
 * an increment used to be duplicated in six places (two in
 * AuctionBiddingController, two in AuctionAdminController, and inline copies in
 * the panels) and three of those copies disagreed with each other on the band
 * boundary and on the fallback increment. Everything now delegates here, and the
 * clients are handed the resolved figure instead of recomputing it.
 */
class BidIncrementService
{
    /**
     * Normalise the rules into a list of floats, dropping malformed rows.
     * Values arrive from `<input type="number">` as strings, so everything is cast.
     *
     * @return list<array{from: float, to: float, increment: float}>
     */
    public function rules(Auction $auction): array
    {
        $raw = $auction->bid_rules;

        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        if (! is_array($raw)) {
            return [];
        }

        $rules = [];
        foreach ($raw as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $rules[] = [
                'from' => isset($rule['from']) ? (float) $rule['from'] : 0.0,
                // A missing upper bound means "and everything above".
                'to' => isset($rule['to']) && $rule['to'] !== '' ? (float) $rule['to'] : PHP_FLOAT_MAX,
                'increment' => isset($rule['increment']) ? (float) $rule['increment'] : 0.0,
            ];
        }

        return $rules;
    }

    /**
     * The increment that applies at the given price, or 0.0 when the ladder is
     * exhausted (i.e. the maximum bid has been reached).
     *
     * The band test is inclusive at both ends. If no band contains the price,
     * the first band that starts above it is used, so a price sitting in a gap
     * between bands still resolves.
     */
    public function incrementFor(Auction $auction, float $currentPrice): float
    {
        $rules = $this->rules($auction);

        foreach ($rules as $rule) {
            if ($currentPrice >= $rule['from'] && $currentPrice <= $rule['to']) {
                if ($rule['increment'] > 0) {
                    return $rule['increment'];
                }
            }
        }

        // Price falls in a gap (or the matching band has a zero increment):
        // use the next band up.
        foreach ($rules as $rule) {
            if ($currentPrice < $rule['from'] && $rule['increment'] > 0) {
                return $rule['increment'];
            }
        }

        return 0.0;
    }

    /**
     * Why the ladder produced no increment, in words an organizer can act on.
     *
     * "Maximum bid reached" is only true when the price has climbed past the top band. When
     * the BASE price already sits above it, nothing was reached at all — the ladder simply
     * never covered the opening price, and saying "maximum reached" sends the organizer
     * looking for a bid that was never placed.
     */
    public function noIncrementReason(Auction $auction, float $currentPrice): string
    {
        $tops = [];
        foreach ($this->rules($auction) as $rule) {
            if ((float) ($rule['increment'] ?? 0) > 0) {
                $tops[] = (float) ($rule['to'] ?? 0);
            }
        }

        if ($tops === []) {
            return 'No bid rule has an increment above zero, so no bid can be placed. '
                . 'Set the increments under Edit auction -> Bid Increments.';
        }

        $ceiling = max($tops);

        if ($currentPrice > $ceiling) {
            return sprintf(
                'The bid rules stop at %s but the price is %s, so there is no increment to apply. '
                . 'Extend the top bid rule under Edit auction -> Bid Increments.',
                format_points($ceiling),
                format_points($currentPrice)
            );
        }

        return 'Maximum bid reached. No further increments available.';
    }

    /**
     * The price the next raise would land on, or null when the ladder is
     * exhausted. Used for bidding, and for deciding which teams are priced out
     * of the player currently on the block.
     */
    public function nextBidAmount(Auction $auction, float $currentPrice): ?float
    {
        $increment = $this->incrementFor($auction, $currentPrice);

        return $increment > 0 ? $currentPrice + $increment : null;
    }

    /**
     * The decrement for walking a price back down (organizer correction).
     * Mirrors incrementFor(), but a price in a gap resolves to the band *below*
     * it rather than above.
     */
    public function decrementFor(Auction $auction, float $currentPrice): float
    {
        $rules = $this->rules($auction);

        foreach ($rules as $rule) {
            if ($currentPrice >= $rule['from'] && $currentPrice <= $rule['to']) {
                if ($rule['increment'] > 0) {
                    return $rule['increment'];
                }
            }
        }

        $decrement = 0.0;
        foreach ($rules as $rule) {
            if ($currentPrice > $rule['from'] && $rule['increment'] > 0) {
                $decrement = $rule['increment'];
            }
        }

        return $decrement;
    }

    /**
     * Payload handed to the panels and the bidding page so no client recomputes
     * the ladder.
     *
     * @return array{current_price: float, increment: float, next_bid_amount: float|null, max_reached: bool}
     */
    public function state(Auction $auction, float $currentPrice): array
    {
        $increment = $this->incrementFor($auction, $currentPrice);

        return [
            'current_price' => $currentPrice,
            'increment' => $increment,
            'next_bid_amount' => $increment > 0 ? $currentPrice + $increment : null,
            'max_reached' => $increment <= 0,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Sealed (closed) bidding
    |--------------------------------------------------------------------------
    | A sealed amount is typed by the team rather than derived from the ladder, so
    | unlike an open raise it has to be checked against a fixed grid. Nothing did
    | that before: the closed branch only floored the amount, so a sealed bid of
    | 1,234,567 was accepted.
    */

    public function sealedStep(Auction $auction): float
    {
        return $auction->closedBidStep();
    }

    /**
     * Is this a legal sealed amount — an exact multiple of the auction's step?
     *
     * Integer minor units, never fmod and never an epsilon comparison:
     *
     *  - `fmod(0.3, 0.1)` is 0.09999999999999998, not 0. Any modulo on floats is wrong
     *    for exactly the values this feature exists to police.
     *  - An epsilon needs a constant whose correct value depends on magnitude: too tight
     *    at 9,000,000, too loose at a step of 0.05. There is no single right number, so
     *    the check would be wrong somewhere in the range.
     *  - Cents is not an approximation of the domain. Every money column here is
     *    decimal(15,2), so two decimal places IS the grain. And the range is provably
     *    exact: decimal(15,2) maxes out around 1e15 cents, inside both a 64-bit int and
     *    a double's exact-integer range (2^53 ≈ 9.007e15).
     */
    public function isLegalSealedAmount(Auction $auction, float $amount): bool
    {
        $stepCents = (int) round($this->sealedStep($auction) * 100);
        $cents = (int) round($amount * 100);

        // A zero step is a configuration error, not a licence for any amount.
        if ($stepCents <= 0 || $cents <= 0) {
            return false;
        }

        return $cents % $stepCents === 0;
    }

    /**
     * The nearest legal amounts either side of an illegal one.
     *
     * For the error message only — naming both neighbours ("try 9M or 9.1M") is more
     * use to somebody under a clock than "invalid amount".
     *
     * @return array{below: float, above: float}
     */
    public function nearestLegalAmounts(Auction $auction, float $amount): array
    {
        $step = $this->sealedStep($auction);

        if ($step <= 0) {
            return ['below' => $amount, 'above' => $amount];
        }

        $stepCents = (int) round($step * 100);
        $cents = (int) round($amount * 100);

        $below = intdiv($cents, $stepCents) * $stepCents;

        // Cast: PHP's `/` yields an int when the division happens to be exact, so an
        // amount on a round boundary would come back a different type from one that is not.
        return [
            'below' => (float) ($below / 100),
            'above' => (float) (($below + $stepCents) / 100),
        ];
    }

    /**
     * Round an amount UP onto the grid.
     *
     * Never call this on the validation path — an illegal sealed amount is rejected, not
     * silently corrected. This exists to snap a published floor and to drive the +/-
     * buttons, so pressing one rescues somebody who typed an illegal figure.
     */
    public function snapUpToStep(Auction $auction, float $amount): float
    {
        $step = $this->sealedStep($auction);

        if ($step <= 0) {
            return $amount;
        }

        $stepCents = (int) round($step * 100);
        $cents = (int) round($amount * 100);

        if ($cents <= 0) {
            return 0.0;
        }

        return (float) ((int) ceil($cents / $stepCents) * $stepCents / 100);
    }

    /** The first legal amount strictly above the given one — a tie-break round's floor. */
    public function nextLegalAbove(Auction $auction, float $amount): float
    {
        $step = $this->sealedStep($auction);

        if ($step <= 0) {
            return $amount;
        }

        $stepCents = (int) round($step * 100);
        $cents = (int) round($amount * 100);

        return (float) ((intdiv(max(0, $cents), $stepCents) + 1) * $stepCents / 100);
    }
}

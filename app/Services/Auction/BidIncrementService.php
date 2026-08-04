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
}

<?php

declare(strict_types=1);

namespace App\Services\Auction;

use App\Models\AuctionClosedBidRound;

/**
 * The drawn lot that settles a tie the re-bid ladder could not.
 *
 * The point of this class is not randomness — it is *defensible* randomness. A team that
 * loses a player to a coin toss in front of a hall will ask how the coin was tossed, and
 * "the server picked one" is not an answer. So the draw records its algorithm, its seed
 * and the exact candidate list it used, which is everything needed to recompute the
 * winner by hand afterwards and confirm it.
 *
 * `random_int()` would be just as fair and leaves nothing to recompute. MySQL's `RAND()`
 * would also be unverifiable, and this codebase deliberately avoids MySQL-only SQL in
 * application code.
 *
 * Injected rather than called statically so a test can bind a fixed-seed double and
 * assert the recorded values really do reproduce the winner.
 */
class ClosedBidLotService
{
    /**
     * Draw a winner from the tied teams.
     *
     * @param  array<int, int>  $candidateTeamIds
     * @return array{algorithm: string, seed: string, candidates: array<int, int>, winner_team_id: int}
     */
    public function draw(AuctionClosedBidRound $round, array $candidateTeamIds): array
    {
        $candidates = array_values(array_unique(array_map('intval', $candidateTeamIds)));

        if ($candidates === []) {
            throw new \InvalidArgumentException('A lot needs at least one candidate.');
        }

        // Sorted first, so the candidate ordering is fixed by the data rather than by
        // whatever order the rows happened to arrive in — otherwise the ordering is
        // itself an unrecorded input to the result.
        sort($candidates);

        $seed = $this->seed();
        $digest = hash_hmac('sha256', $this->message($round, $candidates), $seed);
        $index = (int) (hexdec(substr($digest, 0, 8)) % count($candidates));

        return [
            'algorithm' => AuctionClosedBidRound::LOT_ALGORITHM,
            'seed' => $seed,
            'candidates' => $candidates,
            'winner_team_id' => $candidates[$index],
        ];
    }

    /**
     * Recompute a recorded draw.
     *
     * This is the method that makes the record meaningful: given only what was stored,
     * anyone can re-derive the winner and check it matches.
     *
     * @param  array<int, int>  $candidates
     */
    public function verify(AuctionClosedBidRound $round, array $candidates, string $seed): int
    {
        $digest = hash_hmac('sha256', $this->message($round, $candidates), $seed);

        return $candidates[(int) (hexdec(substr($digest, 0, 8)) % count($candidates))];
    }

    /** The round id binds the draw to this round, so one seed cannot serve two draws. */
    private function message(AuctionClosedBidRound $round, array $candidates): string
    {
        return $round->id . '|' . implode(',', $candidates);
    }

    /**
     * Overridden in tests to make a draw reproducible.
     *
     * Never taken from the request: a client-supplied seed would let the caller choose
     * the winner, which is precisely what this class exists to prevent.
     */
    protected function seed(): string
    {
        return bin2hex(random_bytes(32));
    }
}

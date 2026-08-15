<?php

declare(strict_types=1);

namespace App\Services\Auction;

use App\Models\Auction;
use App\Models\AuctionPool;

/**
 * What the room is waiting for, in one sentence, decided by the server.
 *
 * Every screen worked this out for itself and none of them agreed. The wall had four cases
 * hardcoded in Javascript, the ticker had one line — "Next player coming up…" — which it showed
 * whether the auction had not begun, had just finished a pool, or had been closed an hour ago,
 * and the team manager's dashboard said "Waiting for Next Player" in all of the same situations.
 * A hall watching the strip after the last lot was told the next player was coming up.
 *
 * Two states in particular were missing everywhere:
 *
 *   - a pool ending. This is the pause a room actually notices — the marquee players are gone,
 *     nothing is on the block, and the evening has not stopped. Saying "waiting for next player"
 *     through it is wrong: there is no next player until the organizer starts the next pool.
 *   - the auction ending. "Next player coming up…" under a finished auction is the one caption
 *     nobody can act on.
 *
 * Computed here so the wording is the same on the wall, the strip and every manager's phone, and
 * so a screen cannot show a stage the auction is not in. It rides on the feeds those screens
 * already read, and the existing `AuctionStatusUpdate` nudge is what makes it arrive at once
 * rather than on the next poll.
 */
class AuctionStageService
{
    public const NOT_STARTED = 'not_started';
    public const PAUSED = 'paused';
    public const COMPLETED = 'completed';
    public const POOL_COMPLETE = 'pool_complete';
    public const NO_POOL = 'no_pool';
    public const ALL_DONE = 'all_done';
    public const BETWEEN = 'between';
    public const LIVE = 'live';

    public function __construct(private AuctionPoolService $pools) {}

    /**
     * Both feeds that call this have already counted the players and, on the ticker, already
     * have the pool progress — so both are accepted rather than re-queried. These endpoints are
     * read by every screen in the hall, and a stage caption is not worth three extra queries a
     * tick per viewer.
     *
     * @param  array<string,mixed>|null  $progress  poolProgress(), when the caller already has it
     * @param  array<string,int>|null  $counts  status => count over biddable players
     * @return array{key:string,heading:string,subline:string,pool_name:?string,next_pool:?string}
     */
    public function for(Auction $auction, ?array $progress = null, ?array $counts = null): array
    {
        $progress ??= $this->pools->poolProgress($auction);

        $counts ??= $auction->auctionPlayers()
            ->where('is_retained', false)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        $sold = (int) ($counts['sold'] ?? 0);
        $waiting = (int) ($counts['waiting'] ?? 0);
        $onBlock = (int) ($counts['on_auction'] ?? 0);

        $active = $progress['active_pool'] ?? null;
        $next = $progress['next_pool']['name'] ?? null;

        // ── Decided by the auction's own status first ──
        if ($auction->status === 'completed') {
            return $this->stage(
                self::COMPLETED,
                'AUCTION COMPLETE',
                $sold > 0 ? "{$sold} " . ($sold === 1 ? 'player' : 'players') . ' sold' : $auction->name,
                $active['name'] ?? null,
                null,
            );
        }

        if ($auction->status === 'paused') {
            return $this->stage(self::PAUSED, 'AUCTION PAUSED', 'Back shortly', $active['name'] ?? null, $next);
        }

        if ($auction->status !== 'running') {
            return $this->stage(self::NOT_STARTED, 'WAITING FOR AUCTION', 'Waiting to start the auction', null, $next);
        }

        // ── Running, with somebody on the block ──
        if ($onBlock > 0) {
            return $this->stage(self::LIVE, 'AUCTION IS LIVE', $active['name'] ?? $auction->name, $active['name'] ?? null, $next);
        }

        /*
         * ── Running, nothing on the block ──
         *
         * `finished` is the pool service's own test — nothing queued AND nobody live — so this
         * cannot announce the end of a pool over a player who is still being bid for.
         */
        $poolFinished = $active ? (bool) ($active['finished'] ?? false) : false;
        $lastClosed = $active ? null : $this->lastClosedPoolName($auction);

        if ($poolFinished || $lastClosed) {
            $poolName = $poolFinished ? ($active['name'] ?? null) : $lastClosed;

            // A pool that ends with nothing left anywhere is the end of the auction's work,
            // not a gap before the next pool — there is no next pool to wait for.
            if (! $next && $waiting === 0) {
                return $this->stage(self::ALL_DONE, 'ALL PLAYERS DONE', 'Waiting for the auction to be closed', $poolName, null);
            }

            return $this->stage(
                self::POOL_COMPLETE,
                $this->poolHeading($poolName),
                $next ? "Waiting to start {$next}" : 'Waiting to start the next pool',
                $poolName,
                $next,
            );
        }

        if (! $active) {
            // Started, no pool ever run: the organizer has not chosen one yet.
            return $this->stage(
                self::NO_POOL,
                'AUCTION IS LIVE',
                $next ? "Waiting to start {$next}" : 'Waiting to start the first pool',
                null,
                $next,
            );
        }

        if ($waiting === 0) {
            return $this->stage(self::ALL_DONE, 'ALL PLAYERS DONE', 'Waiting for the auction to be closed', $active['name'] ?? null, $next);
        }

        return $this->stage(
            self::BETWEEN,
            'WAITING FOR NEXT PLAYER',
            $active['name'] ?? $auction->name,
            $active['name'] ?? null,
            $next,
        );
    }

    /** "Pool 1" → "POOL 1 COMPLETE", and an unnamed pool → "POOL COMPLETE". */
    private function poolHeading(?string $poolName): string
    {
        $name = trim((string) $poolName);

        if ($name === '') {
            return 'POOL COMPLETE';
        }

        return mb_strtoupper($name) . ' COMPLETE';
    }

    /**
     * The pool that was closed most recently.
     *
     * Closing a pool leaves the auction with no active pool at all, so without this the wall
     * would fall back to "waiting for next player" in exactly the gap the organizer wanted
     * named — between one pool ending and the next being started.
     */
    private function lastClosedPoolName(Auction $auction): ?string
    {
        return AuctionPool::where('auction_id', $auction->id)
            ->where('status', AuctionPool::STATUS_COMPLETED)
            ->orderByDesc('updated_at')
            ->orderByDesc('sequence')
            ->value('name');
    }

    /** @return array{key:string,heading:string,subline:string,pool_name:?string,next_pool:?string} */
    private function stage(string $key, string $heading, string $subline, ?string $poolName, ?string $next): array
    {
        return [
            'key' => $key,
            'heading' => $heading,
            'subline' => $subline,
            'pool_name' => $poolName,
            'next_pool' => $next,
        ];
    }
}

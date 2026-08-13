<?php

declare(strict_types=1);

namespace App\Services\Auction;

use App\Models\ActualTeam;
use App\Models\Auction;
use App\Models\AuctionPlayer;
use App\Models\AuctionPool;
use App\Models\AuctionTeamBudget;
use App\Models\Player;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pool ordering ("lots") and per-team budget maths for the auction.
 */
class AuctionPoolService
{
    /**
     * (Re)draw lot numbers for every player in a pool according to its order mode.
     * lot_number is 1..N in the drawn order.
     */
    public function generateLotNumbers(AuctionPool $pool): void
    {
        // Only biddable (non-retained) players get a draw position.
        $players = $pool->players()->where('is_retained', false)->orderBy('id')->get();
        $ordered = $this->orderPlayers($players, $pool->order_mode);

        foreach ($ordered->values() as $i => $auctionPlayer) {
            $auctionPlayer->update(['lot_number' => $i + 1]);
        }
    }

    /**
     * Return the pool's players in the order implied by the given mode.
     */
    public function orderPlayers(Collection $players, string $mode): Collection
    {
        $list = $players->values();

        return match ($mode) {
            AuctionPool::MODE_RANDOM => $list->shuffle()->values(),
            AuctionPool::MODE_ODD_EVEN => $this->oddEvenOrder($list),
            // Manual keeps the operator-assigned lot_number ordering (nulls last).
            AuctionPool::MODE_MANUAL => $list->sortBy(fn ($p) => $p->lot_number ?? PHP_INT_MAX)->values(),
            // Sequential = insertion order (already sorted by id).
            default => $list,
        };
    }

    /**
     * Odd positions first, then even: indices 0,2,4,… then 1,3,5,…
     * (1st, 3rd, 5th players drawn first, then 2nd, 4th, 6th).
     */
    protected function oddEvenOrder(Collection $list): Collection
    {
        $odd = [];
        $even = [];
        foreach ($list->values() as $i => $player) {
            if ($i % 2 === 0) {
                $odd[] = $player;
            } else {
                $even[] = $player;
            }
        }

        return collect(array_merge($odd, $even));
    }

    /**
     * The next player to auction, in drawn lot order.
     *
     * When a pool is active the queue is locked to that pool — the organizer runs one
     * pool to exhaustion before moving on. With no active pool this falls back to
     * every pool in sequence order, which is the pre-pool-lock behaviour.
     */
    public function nextPlayer(Auction $auction): ?AuctionPlayer
    {
        $activePool = $this->activePool($auction);

        return AuctionPlayer::query()
            ->where('auction_players.auction_id', $auction->id)
            ->where('auction_players.status', 'waiting')
            ->where('auction_players.is_retained', false)
            ->whereNotNull('auction_players.auction_pool_id')
            ->join('auction_pools', 'auction_pools.id', '=', 'auction_players.auction_pool_id')
            ->when(
                $activePool,
                fn ($q) => $q->where('auction_players.auction_pool_id', $activePool->id),
                fn ($q) => $q->where('auction_pools.is_enabled', true)
                    ->where('auction_pools.is_unsold_pool', false)
            )
            ->orderBy('auction_pools.sequence')
            ->orderByRaw('auction_players.lot_number IS NULL, auction_players.lot_number')
            ->select('auction_players.*')
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | Pool-locked auctioning
    |--------------------------------------------------------------------------
    | The organizer picks one pool and the auction serves only that pool until it
    | is exhausted, rather than drifting across pools.
    */

    /** The pool currently being auctioned, if any. */
    public function activePool(Auction $auction): ?AuctionPool
    {
        return AuctionPool::where('auction_id', $auction->id)
            ->where('status', AuctionPool::STATUS_ACTIVE)
            ->first();
    }

    /**
     * Players still to be auctioned, locked to the active pool when there is one.
     *
     * This is what the organizer panel's queue is built from, so the panel can never
     * offer a player the server would refuse to put on the block.
     */
    public function waitingPlayersQuery(Auction $auction)
    {
        $activePool = $this->activePool($auction);

        return AuctionPlayer::query()
            ->where('auction_players.auction_id', $auction->id)
            ->where('auction_players.status', 'waiting')
            ->where('auction_players.is_retained', false)
            ->when($activePool, fn ($q) => $q->where('auction_players.auction_pool_id', $activePool->id));
    }

    /**
     * Activate a pool, locking the auction to it.
     *
     * @return array{success: bool, message: string, pool?: AuctionPool}
     */
    public function activatePool(Auction $auction, AuctionPool $pool): array
    {
        if ($pool->auction_id !== $auction->id) {
            return ['success' => false, 'message' => 'That pool belongs to a different auction.'];
        }

        if (! $pool->isEnabled()) {
            return ['success' => false, 'message' => sprintf('%s is disabled. Enable it before starting it.', $pool->name)];
        }

        // Finish the live player before switching pools, otherwise a player from the
        // outgoing pool would be stranded mid-bid.
        $live = AuctionPlayer::where('auction_id', $auction->id)->where('status', 'on_auction')->exists();
        if ($live) {
            return ['success' => false, 'message' => 'Finish the player currently on the block before changing pools.'];
        }

        if ($pool->waitingPlayers()->count() === 0) {
            return ['success' => false, 'message' => sprintf('%s has no players left to auction.', $pool->name)];
        }

        DB::transaction(function () use ($auction, $pool) {
            // Any other active pool steps aside; only one pool runs at a time. A pool
            // with players still waiting goes back to pending, not completed.
            AuctionPool::where('auction_id', $auction->id)
                ->where('status', AuctionPool::STATUS_ACTIVE)
                ->where('id', '!=', $pool->id)
                ->get()
                ->each(function (AuctionPool $other) {
                    $other->update([
                        'status' => $other->isExhausted()
                            ? AuctionPool::STATUS_COMPLETED
                            : AuctionPool::STATUS_PENDING,
                        'completed_at' => $other->isExhausted() ? now() : null,
                    ]);
                });

            $pool->update([
                'status' => AuctionPool::STATUS_ACTIVE,
                'activated_at' => now(),
                'completed_at' => null,
                // Counts every run of this pool, including a re-auction round.
                'times_used' => (int) $pool->times_used + 1,
            ]);

            /*
             * An ENDED auction comes back to life when a pool is started in it.
             *
             * Otherwise the auction sits `completed` with an active pool underneath — the panel
             * reads the auction's status, not the pool's, so it would show the ended screen over
             * a pool that is genuinely running and refuse to serve a player from it.
             *
             * Only `completed` is lifted. `paused` is a deliberate act with its own Resume
             * control, and `scheduled` means the organizer has not started the auction yet —
             * quietly starting it here would take that decision away from them.
             */
            if ($auction->status === 'completed') {
                $auction->update(['status' => 'running']);
            }
        });

        return [
            'success' => true,
            'message' => sprintf('%s is now live (run #%d).', $pool->name, (int) $pool->fresh()->times_used),
            'pool' => $pool->fresh(),
        ];
    }

    /**
     * Mark a pool finished and suggest the next enabled one. Deliberately does not
     * auto-advance — the organizer decides when the next pool starts.
     *
     * @return array{success: bool, message: string, next_pool?: array{id: int, name: string}|null}
     */
    public function completePool(Auction $auction, AuctionPool $pool): array
    {
        if ($pool->auction_id !== $auction->id) {
            return ['success' => false, 'message' => 'That pool belongs to a different auction.'];
        }

        /*
         * Closing early actually SETS THE REST ASIDE, rather than only saying so.
         *
         * The confirm dialog has always told the operator that "N player(s) still in it will be
         * left unsold" — and nothing carried that out. The pool was stamped completed and its
         * players were left sitting at status `waiting` inside it: not auctioned, not unsold,
         * absent from final allotment (which looks for unsold players in the unsold pile), and
         * unreachable from the panel because the pool they are in is closed. Ninety-five
         * players could disappear from the auction on one click that promised the opposite.
         *
         * They go where a player nobody bid on goes, because that is what happened to them —
         * the pool closed without their lot being called. Re-auction brings them back, using
         * the source pool recorded on each, exactly as it does for a player who was passed.
         */
        $remaining = $pool->waitingPlayers()->get();

        foreach ($remaining as $auctionPlayer) {
            $auctionPlayer->update(['status' => 'unsold']);
            $this->moveToUnsoldPool($auctionPlayer);
        }

        $pool->update([
            'status' => AuctionPool::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $next = $this->nextEnabledPool($auction);

        return [
            'success' => true,
            'message' => $remaining->isNotEmpty()
                ? sprintf(
                    '%s closed. %d player(s) moved to the unsold list for final allotment.',
                    $pool->name,
                    $remaining->count()
                )
                : sprintf('%s complete.', $pool->name),
            'unsold_count' => $remaining->count(),
            'next_pool' => $next ? ['id' => $next->id, 'name' => $next->name] : null,
        ];
    }

    /**
     * Reopen a pool the auction has already finished with, and run it again.
     *
     * Not the same thing as restarting a pool. A restart undoes that pool's SALES and refunds
     * the purses — right when a pool was run wrongly, and wrong when the organizer simply wants
     * another go at the players nobody took. Closing a pool early sets its uncalled players
     * unsold, and those are what this brings back: the sales stand, the teams keep their squads,
     * and the players who found no buyer go up again.
     *
     * `source_pool_id` is what makes it possible — unsold players share one pile per auction, so
     * the pile itself cannot say where anybody came from.
     *
     * @return array{success: bool, message: string, reclaimed?: int}
     */
    public function reopenPool(Auction $auction, AuctionPool $pool): array
    {
        if ($pool->auction_id !== $auction->id) {
            return ['success' => false, 'message' => 'That pool belongs to a different auction.'];
        }


        // A live player belongs to the pool that is running; switching under them would strand
        // them mid-bid.
        if (AuctionPlayer::where('auction_id', $auction->id)->where('status', 'on_auction')->exists()) {
            return ['success' => false, 'message' => 'Finish the player currently on the block first.'];
        }

        $reclaimed = 0;

        DB::transaction(function () use ($auction, $pool, &$reclaimed) {
            /*
             * Which players come back depends on which pool this is.
             *
             * A normal pool reclaims the players who went unsold FROM it — they were moved to
             * the shared pile and `source_pool_id` is the only record of where they came from.
             *
             * The unsold pile itself reclaims the players already sitting IN it. Running the
             * pile directly is the "one more round for everybody nobody took" that an organizer
             * reaches for near the end, and it used to be refused outright — leaving the
             * re-auction round, which scatters them back across their original pools, as the
             * only way to offer them again.
             */
            $returning = $pool->isUnsoldPool()
                ? AuctionPlayer::where('auction_id', $auction->id)
                    ->where('auction_pool_id', $pool->id)
                    ->whereIn('status', ['unsold', 'passed', 'skipped'])
                    ->get()
                : AuctionPlayer::where('auction_id', $auction->id)
                    ->where('source_pool_id', $pool->id)
                    ->whereIn('status', ['unsold', 'passed', 'skipped'])
                    ->get();

            foreach ($returning as $player) {
                $player->update([
                    // Stays where it is for the pile; moves home for a normal pool.
                    'auction_pool_id' => $pool->id,
                    'source_pool_id' => null,
                    'status' => 'waiting',
                    'current_price' => $player->base_price,
                    'current_bid_team_id' => null,
                    'final_price' => null,
                    // The draw is redone below; a stale lot number would order them by the run
                    // they were dropped from.
                    'lot_number' => null,
                ]);

                $reclaimed++;
            }

            /*
             * Enabled and pending again, whatever it was.
             *
             * A pool closed early is `completed`, and one taken out of play is disabled — and
             * either would make activatePool() refuse the very thing the organizer just asked
             * for. Reopening is that decision, so it carries it out rather than reporting a
             * state the operator then has to go and fix by hand.
             */
            $pool->update([
                'is_enabled' => true,
                'status' => AuctionPool::STATUS_PENDING,
                'completed_at' => null,
            ]);

            if ($reclaimed > 0) {
                $this->generateLotNumbers($pool->fresh());
            }
        });

        $waiting = $pool->fresh()->waitingPlayers()->count();

        if ($waiting === 0) {
            return [
                'success' => false,
                'message' => sprintf('%s has nobody to auction — every player in it was sold.', $pool->name),
            ];
        }

        $activated = $this->activatePool($auction, $pool->fresh());

        return $activated + [
            'reclaimed' => $reclaimed,
            'message' => $activated['success']
                ? sprintf(
                    '%s reopened with %d player(s)%s.',
                    $pool->name,
                    $waiting,
                    $reclaimed > 0 ? sprintf(' — %d brought back from unsold', $reclaimed) : ''
                )
                : $activated['message'],
        ];
    }

    /**
     * The unsold pile as the panel needs to see it, or null when there is nobody in it.
     *
     * `waiting` counts players at any of the unsold statuses as well as `waiting`, because the
     * pile holds them in both states: `unsold` while it sits there, `waiting` once it is being
     * run. One number, so the strip reads the same either way.
     */
    private function unsoldPoolSummary(Auction $auction): ?array
    {
        $pile = AuctionPool::where('auction_id', $auction->id)
            ->where('is_unsold_pool', true)
            ->withCount(['players as available_count' => fn ($q) => $q
                ->whereIn('status', ['unsold', 'passed', 'skipped', 'waiting'])
                ->where('is_retained', false)])
            ->orderBy('id')
            ->first();

        if (! $pile || (int) $pile->available_count < 1) {
            return null;
        }

        return [
            'id' => $pile->id,
            'name' => $pile->name,
            'waiting' => (int) $pile->available_count,
            'status' => $pile->status,
            'is_unsold_pool' => true,
        ];
    }

    /**
     * The next enabled pool with players left, by sequence. Used as a suggestion when
     * the active pool runs dry.
     */
    public function nextEnabledPool(Auction $auction): ?AuctionPool
    {
        return AuctionPool::where('auction_id', $auction->id)
            ->enabled()
            ->biddable()
            ->where('status', '!=', AuctionPool::STATUS_ACTIVE)
            ->whereHas('players', fn ($q) => $q->where('status', 'waiting')->where('is_retained', false))
            ->orderBy('sequence')
            ->first();
    }

    /**
     * Pool progress for the panels: which pool is live, how far through it we are, and
     * what is queued next.
     *
     * @return array<string, mixed>
     */
    public function poolProgress(Auction $auction): array
    {
        $pools = AuctionPool::where('auction_id', $auction->id)
            ->biddable()
            ->withCount([
                'players as waiting_count' => fn ($q) => $q->where('status', 'waiting')->where('is_retained', false),
                'players as sold_count' => fn ($q) => $q->where('status', 'sold'),
                // Still being auctioned. An empty queue is not a finished pool while one of
                // its players is on the block, and the two were the same number.
                'players as on_block_count' => fn ($q) => $q->where('status', 'on_auction'),
                'players as total_count',
            ])
            /*
             * How many of this pool's players are sitting in the unsold pile.
             *
             * Counted through `source_pool_id` rather than `auction_pool_id`, because an unsold
             * player has been MOVED to the shared pile — the pool no longer contains them. This
             * is the number "Reopen a pool" offers to bring back, and only the server can work
             * it out: the panel never sees the pile.
             */
            ->withCount(['unsoldFrom as unsold_from_count'])
            ->orderBy('sequence')
            ->get();

        $active = $pools->firstWhere('status', AuctionPool::STATUS_ACTIVE);
        $next = $this->nextEnabledPool($auction);

        return [
            'active_pool' => $active ? [
                'id' => $active->id,
                'name' => $active->name,
                'category' => $active->category,
                'base_price' => $active->base_price,
                'sequence' => $active->sequence,
                'times_used' => (int) $active->times_used,
                'total' => (int) $active->total_count,
                'waiting' => (int) $active->waiting_count,
                'sold' => (int) $active->sold_count,
                'done' => (int) $active->total_count - (int) $active->waiting_count,
                'on_block' => (int) $active->on_block_count,

                // Nothing left to call up. True the moment the last player leaves the queue,
                // so it is already true while that player is being auctioned.
                'exhausted' => (int) $active->waiting_count === 0,

                /*
                 * Actually finished: nothing waiting AND nobody still on the block.
                 *
                 * The panel announced "Pool complete" off `exhausted` alone, so it said so
                 * over a player who was still live — and offered the controls for a pool
                 * that had not ended yet.
                 */
                'finished' => (int) $active->waiting_count === 0 && (int) $active->on_block_count === 0,
            ] : null,
            'next_pool' => $next ? ['id' => $next->id, 'name' => $next->name] : null,
            /*
             * The unsold pile, when it has anybody in it.
             *
             * Kept OUT of `pools` on purpose: that list is biddable()-scoped and is read by the
             * "start the next pool" suggestion and by every screen that means "the pools of this
             * auction". The pile is not one of those — it is never offered automatically, and
             * nothing should pick it up by accident. It is offered here as a separate, named
             * thing the organizer can deliberately choose to run.
             */
            'unsold_pool' => $this->unsoldPoolSummary($auction),
            'pools' => $pools->map(fn (AuctionPool $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'category' => $p->category,
                'sequence' => $p->sequence,
                'status' => $p->status,
                'is_enabled' => $p->isEnabled(),
                'times_used' => (int) $p->times_used,
                'total' => (int) $p->total_count,
                'waiting' => (int) $p->waiting_count,
                'sold' => (int) $p->sold_count,
                // Players from this pool now in the unsold pile — what reopening would reclaim.
                'unsold_from' => (int) $p->unsold_from_count,
            ])->values()->all(),
        ];
    }

    /**
     * The auction's single unsold pool, created on first use.
     *
     * ONE pile for the whole auction, not one per source pool.
     *
     * Splitting them per source read well on paper — "run allotment pool by pool" — and was
     * wrong in the room. Allotment is not a per-pool exercise: it asks which teams are short
     * of a legal squad and which players are left, and both of those are properties of the
     * whole auction. Divided by origin, the screen showed four short lists that had to be
     * mentally recombined before any of them could be acted on, a team's remaining slots had
     * to be tracked across all four, and a player's pool of origin — which stopped mattering
     * the moment nobody bid on them — decided which list they appeared in.
     *
     * Nothing is lost by merging: a player's origin now rides on `auction_players.source_pool_id`,
     * so re-auction can still return them somewhere biddable and the list can still say where
     * they came from. It is simply no longer the thing that organises the screen.
     */
    public function unsoldPoolFor(AuctionPool|Auction $poolOrAuction): AuctionPool
    {
        // An unsold pool collects for itself — a re-auctioned player who goes unsold
        // again stays where they are rather than nesting another level.
        if ($poolOrAuction instanceof AuctionPool && $poolOrAuction->isUnsoldPool()) {
            return $poolOrAuction;
        }

        $auctionId = $poolOrAuction instanceof AuctionPool
            ? $poolOrAuction->auction_id
            : $poolOrAuction->id;

        /*
         * Oldest first, so an auction carrying per-pool unsold pools from before this change
         * keeps using the one it already has rather than opening a second alongside them. The
         * migration merges the rest into it.
         */
        $existing = AuctionPool::where('auction_id', $auctionId)
            ->where('is_unsold_pool', true)
            ->orderBy('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        $organizationId = $poolOrAuction instanceof AuctionPool
            ? $poolOrAuction->organization_id
            : $poolOrAuction->organization_id;

        return AuctionPool::create([
            'auction_id' => $auctionId,
            'organization_id' => $organizationId,
            'name' => 'Unsold',
            'order_mode' => AuctionPool::MODE_SEQUENTIAL,
            // Sits after every biddable pool so it never gets picked up as "next".
            'sequence' => (int) AuctionPool::where('auction_id', $auctionId)->max('sequence') + 1,
            'is_unsold_pool' => true,
            // No parent: it belongs to the auction, not to any one pool.
            'parent_pool_id' => null,
            // Holding pool: never started as a bidding round.
            'is_enabled' => false,
            'status' => AuctionPool::STATUS_PENDING,
        ]);
    }

    /**
     * Move a player who attracted no bids into their pool's unsold holding pool.
     *
     * Returns the pool they came from so the action can be undone.
     */
    public function moveToUnsoldPool(AuctionPlayer $auctionPlayer): ?AuctionPool
    {
        $sourcePool = $auctionPlayer->pool;

        if (! $sourcePool || $sourcePool->isUnsoldPool()) {
            return $sourcePool;
        }

        $unsoldPool = $this->unsoldPoolFor($sourcePool);

        $auctionPlayer->update([
            'auction_pool_id' => $unsoldPool->id,
            /*
             * Where they came from, kept on the player.
             *
             * The pile is shared by the whole auction now, so it cannot answer this itself —
             * and re-auction needs the answer to put them back somewhere biddable rather than
             * leaving them in a pool the auction never serves.
             */
            'source_pool_id' => $sourcePool->id,
            // The draw is over for this player; a re-auction round redraws.
            'lot_number' => null,
        ]);

        return $sourcePool;
    }

    /**
     * Unsold holding pools with players waiting to be allotted, for the
     * post-auction allotment screen.
     *
     * @return \Illuminate\Support\Collection<int, AuctionPool>
     */
    public function unsoldPools(Auction $auction)
    {
        return AuctionPool::where('auction_id', $auction->id)
            ->where('is_unsold_pool', true)
            ->withCount(['players as unsold_count' => fn ($q) => $q->whereIn('status', ['unsold', 'skipped'])])
            ->with('parentPool:id,name')
            ->orderBy('sequence')
            ->get();
    }

    /**
     * Players waiting to be allotted.
     *
     * Kept as a collection of groups because the allotment screen renders it that way, but
     * there is now one group: unsold players go to a single pool for the whole auction rather
     * than one per source pool (see unsoldPoolFor). An auction that still has several from
     * before that change is handled honestly — each is listed — and the migration collapses
     * them, so in practice this is one list of everyone still unplaced.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function allotmentGroups(Auction $auction)
    {
        return $this->unsoldPools($auction)
            ->map(function (AuctionPool $pool) {
                $players = $pool->players()
                    ->whereIn('status', ['unsold', 'skipped'])
                    ->with(['player.playerType', 'player.battingProfile', 'player.bowlingProfile', 'sourcePool:id,name'])
                    ->orderBy('id')
                    ->get();

                return [
                    'pool' => $pool,
                    'source_pool_name' => $pool->parentPool?->name ?? $pool->name,
                    'players' => $players,
                ];
            })
            ->filter(fn (array $group) => $group['players']->isNotEmpty())
            ->values();
    }

    /**
     * Every team with the figures allotment needs: how short of a legal squad they are,
     * and what they can still spend.
     *
     * Ordered by need — the shortest squad first — so the screen reads as a worklist.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function allotmentTeams(Auction $auction)
    {
        return \App\Models\ActualTeam::forTournament($auction->tournament_id)
            ->orderBy('name')
            ->get()
            ->map(function ($team) use ($auction) {
                $state = $this->teamPurseState($auction, $team->id);

                return [
                    'team' => $team,
                    'slots_filled' => $state['slots_filled'],
                    'slots_required' => $state['slots_required'],
                    'slots_short' => $state['slots_remaining'],
                    'remaining' => $state['remaining'],
                    'spent' => $state['spent'],
                    'needs_players' => $state['slots_remaining'] > 0,
                ];
            })
            ->sortByDesc('slots_short')
            ->values();
    }

    /**
     * Can this team take this player at this price?
     *
     * Allotment checks the *total* purse, deliberately not the squad reserve: the
     * reserve exists precisely to guarantee the remaining slots stay affordable, so
     * applying it here would refuse the very purchases it was reserved for.
     *
     * @return array{allowed: bool, reason: string|null, remaining: float}
     */
    public function canAllot(Auction $auction, int $actualTeamId, float $price): array
    {
        $remaining = $this->remainingBudget($auction, $actualTeamId);

        if (! $this->budgetApplies($auction)) {
            return ['allowed' => true, 'reason' => null, 'remaining' => $remaining];
        }

        if ($price > $remaining) {
            return [
                'allowed' => false,
                'reason' => sprintf(
                    'Only %s left in the purse, and this player costs %s.',
                    format_points($remaining),
                    format_points($price)
                ),
                'remaining' => $remaining,
            ];
        }

        return ['allowed' => true, 'reason' => null, 'remaining' => $remaining];
    }

    /**
     * Propose an allotment of the given players across the teams that are short of a
     * legal squad — always the neediest affordable team next, so squads level up
     * rather than one team absorbing everything.
     *
     * Returns proposals only; nothing is written.
     *
     * @param  \Illuminate\Support\Collection<int, AuctionPlayer>  $players
     * @return array{proposals: list<array{auction_player_id: int, player_name: string, team_id: int, team_name: string, price: float}>, unassigned: list<array{auction_player_id: int, player_name: string, reason: string}>}
     */
    public function proposeAllotment(Auction $auction, $players): array
    {
        // Working copy of each team's need and purse, updated as we assign.
        $teams = $this->allotmentTeams($auction)
            ->map(fn (array $row) => [
                'id' => $row['team']->id,
                'name' => $row['team']->name,
                'short' => $row['slots_short'],
                'remaining' => $row['remaining'],
            ])
            ->all();

        $proposals = [];
        $unassigned = [];

        foreach ($players as $auctionPlayer) {
            $price = (float) $auctionPlayer->base_price;
            $name = $auctionPlayer->player->name ?? ('Player #' . $auctionPlayer->player_id);

            // Neediest team that can still afford this player.
            $bestKey = null;
            foreach ($teams as $key => $team) {
                if ($team['short'] < 1 || $team['remaining'] < $price) {
                    continue;
                }
                if ($bestKey === null
                    || $team['short'] > $teams[$bestKey]['short']
                    || ($team['short'] === $teams[$bestKey]['short'] && $team['remaining'] > $teams[$bestKey]['remaining'])) {
                    $bestKey = $key;
                }
            }

            if ($bestKey === null) {
                $unassigned[] = [
                    'auction_player_id' => $auctionPlayer->id,
                    'player_name' => $name,
                    'reason' => 'No team still needs a player and can afford ' . format_points($price) . '.',
                ];
                continue;
            }

            $proposals[] = [
                'auction_player_id' => $auctionPlayer->id,
                'player_name' => $name,
                'team_id' => $teams[$bestKey]['id'],
                'team_name' => $teams[$bestKey]['name'],
                'price' => $price,
            ];

            $teams[$bestKey]['short']--;
            $teams[$bestKey]['remaining'] -= $price;
        }

        return ['proposals' => $proposals, 'unassigned' => $unassigned];
    }

    /**
     * Make sure every retained player of this auction's tournament has an auction row.
     *
     * Retention is set on the team (Teams -> edit -> squad), which writes
     * `players.player_mode` and `players.retained_value`. But every budget and squad-slot
     * figure in the auction module counts `auction_players` rows where `is_retained` is
     * true — so a retained player with no row costs their team nothing and fills no slot.
     *
     * That row used to be created as a side effect of filing the player into a pool on the
     * Pools screen. It no longer is: a retained player is never bid on, so putting them in
     * a bidding queue was always a fiction, and it meant retention silently depended on an
     * unrelated screen having been visited. This reconciles the two directly.
     *
     * Deliberately pool-less and lot-less. Nothing in the auction draws from a retained
     * row — `generateLotNumbers()` and every waiting-player query already filter
     * `is_retained = false` — so giving it a pool would only make it look biddable.
     *
     * Idempotent: safe to call on every pool-screen load. An existing price is never
     * overwritten by a default (see resolveRetainedPrice()), so an organizer who set a
     * figure by hand keeps it.
     */
    /**
     * The teams actually taking part in this auction.
     *
     * One definition, used by the ticker, the panel and the sealed round — otherwise the
     * broadcast strip lists teams the sealed round never invites, and the two disagree in
     * front of an audience about who is even in the room.
     *
     * Per-auction budget rows are the explicit statement of participation, so when any exist
     * they are authoritative. When none do — the common case, where the organizer never
     * allocated per-team budgets — every team in the tournament is taking part, which is the
     * behaviour every existing auction already relies on.
     *
     * @return \Illuminate\Support\Collection<int, ActualTeam>
     */
    public function participatingTeams(Auction $auction)
    {
        /*
         * Approved sides only, for everybody — there is no Superadmin bypass here as there
         * is on the Groups screen. Who may LOOK at a list is a permissions question; who may
         * spend money in this auction is not. A pending registration turning up in a sealed
         * round can win a player, and the sealed board listed all seven of the tournament's
         * teams (including the same club twice, once approved and once not) with a purse and
         * a Withdraw button beside each.
         */
        $allTeams = ActualTeam::forTournament($auction->tournament_id)
            ->approvedForTournament($auction->tournament_id)
            ->orderBy('name')
            ->get();

        $allocated = AuctionTeamBudget::where('auction_id', $auction->id)
            ->pluck('actual_team_id')
            ->all();

        if ($allocated === []) {
            return $allTeams;
        }

        // Intersected, not substituted: a budget row is an organizer's statement of intent,
        // but it cannot let a side in that the tournament has not approved.
        return $allTeams->whereIn('id', $allocated)->values();
    }

    public function syncRetainedPlayers(Auction $auction): int
    {
        if (! $auction->tournament_id) {
            return 0;
        }

        // Retained players can have a NULL organisation (they may never have registered
        // themselves), so scope by their team's tournament rather than by org.
        $retained = Player::withoutOrganizationScope()
            ->where('player_mode', 'retained')
            ->whereHas('actualTeam', fn ($q) => $q->where('tournament_id', $auction->tournament_id))
            ->get(['id', 'actual_team_id', 'retained_value']);

        if ($retained->isEmpty()) {
            return 0;
        }

        $existing = AuctionPlayer::where('auction_id', $auction->id)
            ->whereIn('player_id', $retained->pluck('id'))
            ->get()
            ->keyBy('player_id');

        $touched = 0;

        foreach ($retained as $player) {
            $row = $existing->get($player->id);

            // Someone already bought or is bidding on this player — a stale `retained`
            // flag must not rewrite a live result. Leave it for a human to sort out.
            if ($row && $row->status !== 'waiting') {
                continue;
            }

            $price = $this->resolveRetainedPrice($auction, $player, null, $row);

            AuctionPlayer::updateOrCreate(
                ['auction_id' => $auction->id, 'player_id' => $player->id],
                [
                    'organization_id' => $auction->organization_id,
                    'is_retained' => true,
                    'team_id' => $player->actual_team_id,
                    'retained_price' => $price,
                    'auction_pool_id' => null,
                    'lot_number' => null,
                    'status' => 'waiting',
                    'base_price' => 0,
                    'current_price' => 0,
                    'starting_price' => 0,
                ]
            );

            $touched++;
        }

        return $touched;
    }

    /**
     * Base price for a player in a pool: explicit per-player value, else the pool's
     * price, else the auction's.
     */
    /**
     * What a retained player costs their team in this auction.
     *
     * A blank retention price used to be written straight through as 0, inside an
     * updateOrCreate — so re-assigning an already-priced retained player with the
     * field left empty silently wiped their price and the team got them for nothing.
     * Blank now means "fall back"; only an explicit 0 makes a retention free.
     */
    public function resolveRetainedPrice(
        Auction $auction,
        ?Player $player = null,
        mixed $submitted = null,
        ?AuctionPlayer $existing = null
    ): float {
        // An explicit figure wins, including a deliberate 0.
        if (is_numeric($submitted)) {
            return (float) $submitted;
        }

        // Never overwrite a price somebody already set with a default.
        if ($existing && is_numeric($existing->retained_price) && (float) $existing->retained_price > 0) {
            return (float) $existing->retained_price;
        }

        // The player's own retention value — required at the point they were retained,
        // so it is the closest thing on record to the organizer's actual intent.
        if ($player && is_numeric($player->retained_value) && (float) $player->retained_value > 0) {
            return (float) $player->retained_value;
        }

        return $auction->defaultRetainedValue();
    }

    public function resolveBasePrice(Auction $auction, ?AuctionPool $pool, mixed $playerPrice = null): float
    {
        if (is_numeric($playerPrice)) {
            return (float) $playerPrice;
        }

        if ($pool && is_numeric($pool->base_price) && (float) $pool->base_price > 0) {
            return (float) $pool->base_price;
        }

        return (float) ($auction->base_price ?? 0);
    }

    /**
     * Budget & team-budget conditions apply ONLY to auction-type tournaments.
     * Open tournaments have no budget mechanics.
     */
    public function budgetApplies(Auction $auction): bool
    {
        return $auction->tournament?->isAuction() ?? true;
    }

    /**
     * Per-team allocation, falling back to the auction-wide uniform cap.
     *
     * A row wins even when its budget is 0 — *row existence* decides, not the value.
     * That is deliberate: AuctionAdminController::update() deletes the row for a blank
     * input rather than writing 0, so a zero row can only ever be a deliberate "this
     * team has no money". Do not "fix" this to `?:`.
     */
    public function allocatedBudget(Auction $auction, int $actualTeamId): float
    {
        $row = AuctionTeamBudget::where('auction_id', $auction->id)
            ->where('actual_team_id', $actualTeamId)
            ->first();

        if ($row) {
            return (float) $row->budget;
        }

        return (float) ($auction->max_budget_per_team ?? 0);
    }

    /** Amount a team spent on players SOLD to it in the live auction. */
    public function soldSpent(Auction $auction, int $actualTeamId): float
    {
        return (float) AuctionPlayer::where('auction_id', $auction->id)
            ->where('status', 'sold')
            ->where('sold_to_team_id', $actualTeamId)
            ->sum('final_price');
    }

    /** Retention cost of a team's retained players — counts against its budget up front. */
    public function retainedSpent(Auction $auction, int $actualTeamId): float
    {
        return (float) AuctionPlayer::where('auction_id', $auction->id)
            ->where('is_retained', true)
            ->where('team_id', $actualTeamId)
            ->sum('retained_price');
    }

    /**
     * The purse a team actually brings to the auction floor: its whole allocation
     * less what its retentions already cost.
     *
     * This is the honest denominator for a spend bar and the right threshold for
     * "nearly broke" — a team that retained most of its budget is fully committed by
     * design, not in trouble.
     */
    public function auctionPurse(Auction $auction, int $actualTeamId): float
    {
        if (! $this->budgetApplies($auction)) {
            return PHP_FLOAT_MAX;
        }

        return $this->allocatedBudget($auction, $actualTeamId) - $this->retainedSpent($auction, $actualTeamId);
    }

    /** Total committed by a team: sold purchases + retained-player costs. */
    public function spent(Auction $auction, int $actualTeamId): float
    {
        return $this->soldSpent($auction, $actualTeamId) + $this->retainedSpent($auction, $actualTeamId);
    }

    public function remainingBudget(Auction $auction, int $actualTeamId): float
    {
        if (! $this->budgetApplies($auction)) {
            return PHP_FLOAT_MAX; // open tournaments: no budget cap
        }

        return $this->allocatedBudget($auction, $actualTeamId) - $this->spent($auction, $actualTeamId);
    }

    public function canAfford(Auction $auction, int $actualTeamId, float $amount): bool
    {
        return $amount <= $this->remainingBudget($auction, $actualTeamId);
    }

    /*
    |--------------------------------------------------------------------------
    | Squad slots & the reserve rule
    |--------------------------------------------------------------------------
    | A team must always retain enough purse to buy the squad slots it still has
    | to fill, so it cannot spend everything early and end up unable to field a
    | legal side:
    |
    |   reserve = max(0, slotsRemaining - 1) * minPricePerPlayer
    |   maxBid  = remainingBudget - reserve
    |
    | The "- 1" is the slot the current bid would itself fill.
    */

    /**
     * Squad slots a team has already filled in this auction.
     *
     * Deliberately mirrors soldSpent() + retainedSpent() exactly — the same rows
     * that cost money count as slots, so money and slots can never disagree.
     * (The roster pivots are not used here: sellToTeam historically skipped
     * them, so they undercount.)
     */
    public function slotsFilled(Auction $auction, int $actualTeamId): int
    {
        return $this->soldCount($auction, $actualTeamId) + $this->retainedCount($auction, $actualTeamId);
    }

    /** Players a team bought in the live auction. Mirrors soldSpent() row for row. */
    public function soldCount(Auction $auction, int $actualTeamId): int
    {
        return (int) AuctionPlayer::where('auction_id', $auction->id)
            ->where('status', 'sold')
            ->where('sold_to_team_id', $actualTeamId)
            ->count();
    }

    /** Players a team retained. Mirrors retainedSpent() row for row. */
    public function retainedCount(Auction $auction, int $actualTeamId): int
    {
        return (int) AuctionPlayer::where('auction_id', $auction->id)
            ->where('is_retained', true)
            ->where('team_id', $actualTeamId)
            ->count();
    }

    /** Slots a team still has to fill to reach the minimum legal squad. */
    public function slotsRemaining(Auction $auction, int $actualTeamId): int
    {
        return max(0, $auction->minSquadSize() - $this->slotsFilled($auction, $actualTeamId));
    }

    /**
     * Purse a team must hold back for the slots it still has to fill after the
     * one it is bidding on right now.
     */
    public function reserveFor(Auction $auction, int $actualTeamId): float
    {
        if (! $this->budgetApplies($auction)) {
            return 0.0;
        }

        return $this->reserveFrom($auction, $this->slotsRemaining($auction, $actualTeamId));
    }

    /**
     * The reserve formula itself, taking a slot count rather than looking one up.
     *
     * Exists so teamPurseState() can build the whole picture from four queries
     * instead of re-deriving each figure through its own lookups, without a second
     * copy of the rule drifting from this one.
     */
    private function reserveFrom(Auction $auction, int $slotsRemaining): float
    {
        return max(0, $slotsRemaining - 1) * $auction->minPricePerPlayer();
    }

    /**
     * The most a team may bid right now without breaching its squad reserve.
     * Never negative — a team that has already over-committed is simply capped
     * at zero rather than reported as owing money.
     */
    public function maxAllowedBid(Auction $auction, int $actualTeamId): float
    {
        if (! $this->budgetApplies($auction)) {
            return PHP_FLOAT_MAX; // open tournaments: no budget mechanics
        }

        return max(
            0.0,
            $this->remainingBudget($auction, $actualTeamId) - $this->reserveFor($auction, $actualTeamId)
        );
    }

    /**
     * Share of a team's allocation it may commit to any ONE player in open bidding.
     *
     * Unset means no ceiling — PHP_FLOAT_MAX rather than a default percentage, so an auction
     * that has not configured this is bound only by the reserve rule and behaves exactly as it
     * did before the setting existed. Computed off the team's TOTAL allocation, like the sealed
     * cap, so the figure is fixed for the auction and cannot move under a team mid-round.
     */
    public function openPerPlayerCap(Auction $auction, int $actualTeamId): float
    {
        $pct = $auction->maxBidPct();

        if ($pct === null || ! $this->budgetApplies($auction)) {
            return PHP_FLOAT_MAX;
        }

        return $this->allocatedBudget($auction, $actualTeamId) * $pct / 100;
    }

    /**
     * The most a team may bid on the player in front of it, under EVERY open-round rule.
     *
     * maxAllowedBid() is the squad-reserve rule alone and is left that way on purpose — the
     * sealed round's message logic reads it as "the reserve ceiling" and would start naming the
     * wrong rule if it silently included this one.
     */
    public function openBidCeiling(Auction $auction, int $actualTeamId): float
    {
        /*
         * A full squad can bid nothing at all.
         *
         * Expressed as a ceiling of zero rather than as a separate check at each call site,
         * because `canAffordWithReserve()` below is the one gate every bid path already goes
         * through — an open raise, a sell, a sealed submission, the organizer bidding on a team's
         * behalf, final allotment. Six places would have needed the same new condition, and the
         * one that got missed would be the one that let a full team buy a player.
         *
         * It is also simply true: a team with no places left has nothing to spend a purse on.
         */
        if ($this->squadIsFull($auction, $actualTeamId)) {
            return 0.0;
        }

        return min(
            $this->maxAllowedBid($auction, $actualTeamId),
            $this->openPerPlayerCap($auction, $actualTeamId)
        );
    }

    /**
     * Has this team filled every place the rules allow?
     *
     * `maxSquadSize()` when one is set, else the required size — both now resolve through the
     * TOURNAMENT unless the auction overrides (Auction::rule()), so a squad size set once for the
     * competition is what closes teams out of the bidding.
     *
     * Icon (retained) players count, because they occupy a place exactly as a bought player does:
     * a squad of 20 with 4 icons has 16 to buy, not 20.
     */
    public function squadIsFull(Auction $auction, int $actualTeamId): bool
    {
        $size = $auction->maxSquadSize() ?? $auction->minSquadSize();

        if ($size < 1) {
            return false;
        }

        /*
         * The two leaf counts directly, not teamPurseState() — that calls purseFrom(), which is
         * where `excluded` is decided, and `excluded` depends on this. Reading the leaves keeps
         * the dependency one-way.
         */
        $filled = $this->soldCount($auction, $actualTeamId)
            + $this->retainedCount($auction, $actualTeamId);

        return $filled >= $size;
    }

    /** Budget check including the squad reserve. Use this at every bid/sell. */
    public function canAffordWithReserve(Auction $auction, int $actualTeamId, float $amount): bool
    {
        return $amount <= $this->openBidCeiling($auction, $actualTeamId);
    }

    /*
    |--------------------------------------------------------------------------
    | Per-player spend cap (sealed rounds)
    |--------------------------------------------------------------------------
    | A team may commit at most a configured share of its budget to any ONE player,
    | so a single sealed round cannot swallow most of a purse. This is a separate
    | ceiling from the squad reserve and both bind at once.
    */

    /**
     * The cap formula, taking a figure rather than looking one up.
     *
     * Exists for the same reason as reserveFrom(): teamPurseState() already holds the
     * allocation, and calling perPlayerCap() from inside it would re-run
     * allocatedBudget() and add a query to a method that runs once per team on a
     * two-second poll.
     */
    private function perPlayerCapFrom(Auction $auction, float $allocated): float
    {
        return $allocated * $auction->closedBidMaxPct() / 100;
    }

    /**
     * Most a team may commit to a single player.
     *
     * A share of the team's TOTAL allocation — deliberately not its remaining purse and
     * not its post-retention purse, so the figure is fixed for the auction and never
     * moves under a team mid-round.
     */
    public function perPlayerCap(Auction $auction, int $actualTeamId): float
    {
        if (! $this->budgetApplies($auction)) {
            return PHP_FLOAT_MAX;
        }

        return $this->perPlayerCapFrom($auction, $this->allocatedBudget($auction, $actualTeamId));
    }

    /**
     * The effective sealed ceiling: the lower of the per-player cap and the squad
     * reserve's maximum. "Both caps bind" is this one line.
     */
    public function perPlayerCeiling(Auction $auction, int $actualTeamId): float
    {
        if (! $this->budgetApplies($auction)) {
            return PHP_FLOAT_MAX;
        }

        return min(
            $this->perPlayerCap($auction, $actualTeamId),
            $this->maxAllowedBid($auction, $actualTeamId)
        );
    }

    public function canAffordSealed(Auction $auction, int $actualTeamId, float $amount): bool
    {
        return $amount <= $this->perPlayerCeiling($auction, $actualTeamId);
    }

    /**
     * Why a sealed amount was refused, naming WHICH ceiling bound.
     *
     * Without that a team is told "you may bid up to 7M" and cannot tell whether it
     * should sell a player, wait for the reserve to free up, or accept that the rule
     * simply forbids spending more on one player.
     */
    public function sealedBlockedMessage(Auction $auction, int $actualTeamId, float $amount, ?string $teamName = null): string
    {
        $name = $teamName ?: 'This team';
        $cap = $this->perPlayerCap($auction, $actualTeamId);
        $reserveMax = $this->maxAllowedBid($auction, $actualTeamId);

        if ($cap <= $reserveMax) {
            return sprintf(
                '%s may not spend more than %s on one player (%s%% of a %s budget). Requested %s.',
                $name,
                format_points($cap),
                rtrim(rtrim(number_format($auction->closedBidMaxPct(), 2, '.', ''), '0'), '.'),
                format_points($this->allocatedBudget($auction, $actualTeamId)),
                format_points($amount)
            );
        }

        return $this->reserveBlockedMessage($auction, $actualTeamId, $amount, $teamName);
    }

    /**
     * A team is excluded from the player currently on the block when it cannot
     * meet the next bid under the reserve rule. Recomputed per player and per
     * price step, so a team priced out of a 5M player can still bid on a 1M one.
     */
    public function isExcluded(Auction $auction, int $actualTeamId, float $nextBidAmount): bool
    {
        if (! $this->budgetApplies($auction)) {
            return false;
        }

        return $nextBidAmount > $this->openBidCeiling($auction, $actualTeamId);
    }

    /**
     * Everything the panels and the bidding page need to render a team's purse
     * state, computed once from a single set of formulas.
     *
     * Built from five leaf reads and then derived arithmetically, rather than calling
     * remainingBudget()/reserveFor()/maxAllowedBid()/slotsFilled() and letting each
     * re-query underneath. That took this from ~22 queries per team to 5 — which
     * matters because pollState() calls it once per team on a two-second poll.
     *
     * The derivations reuse the same helpers the standalone methods use
     * (reserveFrom), so there is no second copy of a rule to drift.
     *
     * @return array{allocated: float, spent: float, remaining: float, reserve: float, max_bid_allowed: float, slots_filled: int, slots_required: int, slots_remaining: int, excluded: bool, auction_purse: float, retained_spent: float, auction_spent: float, retained_count: int, retained_expected: int, slots_max: int|null, per_player_cap_pct: float|null, per_player_cap: float, sealed_max_bid: float}
     */
    public function teamPurseState(Auction $auction, int $actualTeamId, ?float $nextBidAmount = null): array
    {
        $applies = $this->budgetApplies($auction);

        return $this->purseFrom($auction, [
            'allocated' => $applies ? $this->allocatedBudget($auction, $actualTeamId) : 0.0,
            'sold_spent' => $this->soldSpent($auction, $actualTeamId),
            'retained_spent' => $this->retainedSpent($auction, $actualTeamId),
            'sold_count' => $this->soldCount($auction, $actualTeamId),
            'retained_count' => $this->retainedCount($auction, $actualTeamId),
        ], $nextBidAmount);
    }

    /**
     * The same figures for MANY teams, at a fixed cost.
     *
     * teamPurseState() runs five leaf queries per team, and pollState() calls it once per
     * team on a two-second poll — seven teams meant thirty-five queries every two seconds,
     * all of them aggregates over the same two tables, while the auction was also writing
     * bids to one of them. On the live panel poll-state was taking between one and eight
     * seconds.
     *
     * Every one of those leaves is the same aggregate filtered to a team, so they group
     * instead: three queries here, whatever the number of teams. The arithmetic is
     * untouched — both paths go through purseFrom(), so a rule cannot drift between the
     * money the panel shows and the money the bidding page shows.
     *
     * Deliberately NOT cached. A cache would need invalidating on every bid, sale, undo,
     * withdrawal and retention edit; miss one and a team is shown money it has already
     * spent, mid-auction, in front of a hall. This is exact by construction.
     *
     * @param  list<int>  $teamIds
     * @return array<int, array<string, mixed>>
     */
    public function teamPurseStates(Auction $auction, array $teamIds, ?float $nextBidAmount = null): array
    {
        $applies = $this->budgetApplies($auction);

        // Sold: spend and squad count for every team in one pass.
        $sold = AuctionPlayer::where('auction_id', $auction->id)
            ->where('status', 'sold')
            ->whereNotNull('sold_to_team_id')
            ->groupBy('sold_to_team_id')
            ->selectRaw('sold_to_team_id AS team_id, SUM(final_price) AS spent, COUNT(*) AS filled')
            ->get()
            ->keyBy('team_id');

        // Retained rows key on `team_id`, not `sold_to_team_id`. Mixing the two would
        // credit the wrong side and the totals would still look plausible.
        $retained = AuctionPlayer::where('auction_id', $auction->id)
            ->where('is_retained', true)
            ->whereNotNull('team_id')
            ->groupBy('team_id')
            ->selectRaw('team_id, SUM(retained_price) AS spent, COUNT(*) AS filled')
            ->get()
            ->keyBy('team_id');

        $budgets = AuctionTeamBudget::where('auction_id', $auction->id)
            ->pluck('budget', 'actual_team_id');

        $fallback = (float) ($auction->max_budget_per_team ?? 0);

        $states = [];

        foreach ($teamIds as $teamId) {
            $teamId = (int) $teamId;

            // A team with no players appears in none of the prefetches, and must still come
            // back with a full state of zeros rather than a missing key. SUM() over an empty
            // group is null, so every read is cast at this boundary.
            $states[$teamId] = $this->purseFrom($auction, [
                'allocated' => $applies
                    ? (float) ($budgets[$teamId] ?? $fallback)
                    : 0.0,
                'sold_spent' => (float) ($sold[$teamId]->spent ?? 0),
                'retained_spent' => (float) ($retained[$teamId]->spent ?? 0),
                'sold_count' => (int) ($sold[$teamId]->filled ?? 0),
                'retained_count' => (int) ($retained[$teamId]->filled ?? 0),
            ], $nextBidAmount);
        }

        return $states;
    }

    /**
     * The derivation, shared by the single-team and batched paths.
     *
     * Takes the five leaf figures already read and does the arithmetic. Keeping this in one
     * place is the point: two copies of these rules would eventually disagree about what a
     * team can afford, and the two screens showing it would disagree in public.
     *
     * @param  array{allocated: float, sold_spent: float, retained_spent: float, sold_count: int, retained_count: int}  $leaves
     */
    private function purseFrom(Auction $auction, array $leaves, ?float $nextBidAmount): array
    {
        $applies = $this->budgetApplies($auction);

        $allocated = $leaves['allocated'];
        $soldSpent = $leaves['sold_spent'];
        $retainedSpent = $leaves['retained_spent'];
        $soldCount = $leaves['sold_count'];
        $retainedCount = $leaves['retained_count'];

        $spent = $soldSpent + $retainedSpent;
        $slotsFilled = $soldCount + $retainedCount;
        $slotsRemaining = max(0, $auction->minSquadSize() - $slotsFilled);

        /*
         * A team that has filled every place is out of the auction, whatever its purse says.
         *
         * Derived from the counts already in hand rather than by calling squadIsFull(), which
         * would re-run both count queries for a figure this method has just computed.
         */
        $squadSize = $auction->maxSquadSize() ?? $auction->minSquadSize();
        $squadFull = $squadSize >= 1 && $slotsFilled >= $squadSize;

        $remaining = $applies ? $allocated - $spent : PHP_FLOAT_MAX;
        $reserve = $applies ? $this->reserveFrom($auction, $slotsRemaining) : 0.0;
        $maxBid = $applies ? max(0.0, $remaining - $reserve) : PHP_FLOAT_MAX;

        return [
            'allocated' => $allocated,
            'spent' => $spent,
            'remaining' => $remaining,
            'reserve' => $reserve,
            'max_bid_allowed' => $maxBid,
            'slots_filled' => $slotsFilled,
            'slots_required' => $auction->minSquadSize(),
            'slots_remaining' => $slotsRemaining,
            'squad_full' => $squadFull,
            'squad_size' => $squadSize,
            /*
             * Checked against the SAME ceiling canAffordWithReserve() uses, or a team would look
             * able to bid an amount the server then refuses.
             *
             * A full squad is excluded outright, whatever the amount: openBidCeiling() returns 0
             * for one, so every bid path refuses it server-side and this is what says so on the
             * screens before anybody tries.
             */
            'excluded' => $squadFull || ($applies && $nextBidAmount !== null && $nextBidAmount > (
                $auction->maxBidPct() !== null
                    ? min($maxBid, $allocated * $auction->maxBidPct() / 100)
                    : $maxBid
            )),

            // The "show both" split: the whole allocation, and the purse left for
            // bidding once retentions are paid for.
            'auction_purse' => $applies ? $allocated - $retainedSpent : PHP_FLOAT_MAX,
            'retained_spent' => $retainedSpent,
            'auction_spent' => $soldSpent,
            'retained_count' => $retainedCount,
            'retained_expected' => $auction->expectedRetainedPerTeam(),
            'slots_max' => $auction->maxSquadSize(),

            // Sealed ceilings. Derived from $allocated and $maxBid already in hand —
            // calling perPlayerCap() here would re-run allocatedBudget() and cost a
            // sixth query on a method that runs once per team every two seconds.
            // Open-round ceiling. Null pct means "not configured", which is a different
            // statement from 100% and must not draw a limit on a team's screen.
            'open_per_player_cap_pct' => $applies ? $auction->maxBidPct() : null,
            'open_per_player_cap' => $applies && $auction->maxBidPct() !== null
                ? $allocated * $auction->maxBidPct() / 100
                : PHP_FLOAT_MAX,
            'open_max_bid' => $applies && $auction->maxBidPct() !== null
                ? min($maxBid, $allocated * $auction->maxBidPct() / 100)
                : $maxBid,

            'per_player_cap_pct' => $applies ? $auction->closedBidMaxPct() : null,
            'per_player_cap' => $applies ? $this->perPlayerCapFrom($auction, $allocated) : PHP_FLOAT_MAX,
            'sealed_max_bid' => $applies
                ? min($this->perPlayerCapFrom($auction, $allocated), $maxBid)
                : PHP_FLOAT_MAX,
        ];
    }

    /**
     * Human-readable reason a bid was blocked, for the API error and the
     * exclusion tooltip.
     */
    public function reserveBlockedMessage(Auction $auction, int $actualTeamId, float $amount, ?string $teamName = null): string
    {
        $reserve = $this->reserveFor($auction, $actualTeamId);
        $slotsAfter = max(0, $this->slotsRemaining($auction, $actualTeamId) - 1);
        $maxBid = $this->maxAllowedBid($auction, $actualTeamId);
        $who = $teamName ?: 'This team';

        /*
         * Name the per-player ceiling when THAT is what bound, not the reserve.
         *
         * Being told to hold money back for empty squad places, when the money is there and
         * the rule in the way is a single-player limit, sends a manager looking for the wrong
         * fix. Checked first because a cap below the reserve maximum is the binding rule.
         */
        $cap = $this->openPerPlayerCap($auction, $actualTeamId);

        if ($cap < $maxBid && $amount > $cap) {
            return sprintf(
                '%s may not spend more than %s on one player (%s%% of a %s budget). Requested %s.',
                $who,
                format_points($cap),
                rtrim(rtrim(number_format((float) $auction->maxBidPct(), 2, '.', ''), '0'), '.'),
                format_points($this->allocatedBudget($auction, $actualTeamId)),
                format_points($amount)
            );
        }

        if ($reserve > 0 && $amount > $maxBid) {
            return sprintf(
                '%s must retain %s for %d remaining squad slot%s. Max bid allowed: %s (tried %s).',
                $who,
                format_points($reserve),
                $slotsAfter,
                $slotsAfter === 1 ? '' : 's',
                format_points($maxBid),
                format_points($amount)
            );
        }

        return sprintf(
            '%s has %s remaining. Max bid allowed: %s (tried %s).',
            $who,
            format_points($this->remainingBudget($auction, $actualTeamId)),
            format_points($maxBid),
            format_points($amount)
        );
    }
}

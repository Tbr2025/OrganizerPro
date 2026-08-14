<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ActualTeam;
use App\Models\Auction;
use App\Models\AuctionActionLog;
use App\Models\AuctionPlayer;
use App\Models\AuctionPool;
use App\Models\Player;
use App\Services\Auction\AuctionPoolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Dedicated pool management for an auction — separate from the create/edit wizard.
 * Admin builds pools (name + base price + category + order + capacity) and assigns
 * players to them (one pool per player). Pool-based auction then runs normally
 * since nextPlayer() draws from pooled players in sequence -> lot order.
 */
class AuctionPoolController extends Controller
{
    /** Kept out of AuctionActionLog::REVERSIBLE — this is a configuration action,
     *  not a live-auction one, so the panel's UNDO must not pick it up. */
    private const ACTION_AUTO_ASSIGN = 'auto_assign';

    private const ORDER_MODES = ['sequential', 'random', 'odd_even', 'manual'];

    public function __construct(private readonly AuctionPoolService $poolService)
    {
    }

    /** The dedicated Pools management screen. */
    public function index(Auction $auction): View
    {
        $this->authorize('auction.view');

        /*
         * Retention is set on the team, not here, so bring the auction's retained rows in
         * line before this screen reports team budgets — that table counts
         * `auction_players.is_retained`, and a retained player with no row would show as
         * costing their team nothing.
         *
         * Idempotent and never overwrites a price a human set.
         */
        $this->poolService->syncRetainedPlayers($auction);

        $auction->load([
            'tournament',
            'pools' => fn ($q) => $q->orderBy('sequence'),
            'pools.players.player.playerType',
            // Read by each pool row's summary. Eager-loaded, or a pool of three hundred players
            // is three hundred extra queries for two style names apiece.
            'pools.players.player.battingProfile:id,style',
            'pools.players.player.bowlingProfile:id,style',
            'pools.players.team:id,name',
            // The buying team, named on each sold row — "sold" alone did not say to whom.
            'pools.players.soldToTeam:id,name',
            // Unsold holding pools name the pool they collect for.
            'pools.parentPool:id,name',
        ]);
        $isAuctionType = $auction->tournament?->isAuction() ?? true;

        // Players already sitting in a pool for this auction.
        $pooledPlayerIds = AuctionPlayer::where('auction_id', $auction->id)
            ->whereNotNull('auction_pool_id')
            ->pluck('player_id')->all();

        // Available (unassigned) = players with approved registration for this auction's
        // tournament, minus those already pooled. Retained players are flagged for retention price.
        $tournamentId = $auction->tournament_id;
        $available = Player::whereHas('registrations', fn ($q) => $q->where('tournament_id', $tournamentId)->where('status', 'approved'))
            ->when($auction->organization_id, fn ($q) => $q->where('organization_id', $auction->organization_id))
            ->whereNotIn('id', $pooledPlayerIds)
            /*
             * Retained players are deliberately absent from this screen.
             *
             * A pool is a bidding queue — it decides who goes on the block and in what
             * order. A retained player is never bid on: they are already on their team's
             * roster and their retention price is simply deducted from that team's budget.
             * Listing them here asked the organizer to file them into a queue they will
             * never join, and put a second, competing retention-price box on a screen that
             * has nothing to do with retention.
             *
             * Retention lives on the team: Teams -> edit -> squad, which writes
             * `players.retained_value`. That is already the figure the auction reads
             * (see AuctionPoolService::resolveRetainedPrice()), so there is one number and
             * one place to change it.
             */
            ->where(fn ($q) => $q->where('player_mode', '!=', 'retained')->orWhereNull('player_mode'))
            ->with(['playerType', 'actualTeam:id,name'])
            ->orderBy('name')
            ->get(['id', 'name', 'player_type_id', 'organization_id', 'player_mode', 'actual_team_id', 'retained_value']);

        // Is there an auto-assign run that can still be reverted?
        $revertibleAutoAssign = AuctionActionLog::where('auction_id', $auction->id)
            ->where('action', self::ACTION_AUTO_ASSIGN)
            ->pending()
            ->orderByDesc('id')
            ->first();

        // Per-team budget summary — auction tournaments only.
        $teamBudgets = collect();
        if ($isAuctionType && $auction->tournament_id) {
            $teamBudgets = ActualTeam::forTournament($auction->tournament_id)->orderBy('name')->get()
                ->map(function ($t) use ($auction) {
                    $state = $this->poolService->teamPurseState($auction, $t->id);

                    return [
                        'team' => $t,
                        'allocated' => $state['allocated'],
                        'retained' => $state['retained_spent'],
                        'sold' => $state['auction_spent'],
                        'remaining' => $state['remaining'],
                        'retained_count' => $state['retained_count'],
                        'retained_expected' => $state['retained_expected'],
                        // Retained players nobody priced. They currently cost their team
                        // nothing, which is the bug that made this column necessary.
                        'retained_unpriced' => AuctionPlayer::where('auction_id', $auction->id)
                            ->where('is_retained', true)
                            ->where('team_id', $t->id)
                            ->where(fn ($q) => $q->whereNull('retained_price')->orWhere('retained_price', 0))
                            ->count(),
                    ];
                });
        }

        /*
         * The poster designs this auction can be exported onto, if any exist.
         *
         * Empty is the normal state and the screen says nothing when it is — an operator who
         * has never opened the designer should not be shown a menu with nothing in it.
         */
        $posterTemplates = $auction->tournament_id
            ? \App\Models\TournamentTemplate::where('tournament_id', $auction->tournament_id)
                ->whereIn('type', [
                    \App\Models\TournamentTemplate::TYPE_AUCTION_POSTER,
                    \App\Models\TournamentTemplate::TYPE_AUCTION_POSTER_PORTRAIT,
                ])
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get()
            : collect();

        return view('backend.pages.auctions.pools.index', [
            'auction' => $auction,
            'pools' => $auction->pools,
            'posterTemplates' => $posterTemplates,
            'available' => $available,
            'orderModes' => self::ORDER_MODES,
            'isAuctionType' => $isAuctionType,
            'teamBudgets' => $teamBudgets,
            'revertibleAutoAssign' => $revertibleAutoAssign,
            'breadcrumbs' => [
                'title' => __('Manage Pools'),
                'items' => [
                    ['label' => __('Auctions'), 'url' => route('admin.auctions.index')],
                    ['label' => $auction->name, 'url' => route('admin.auctions.show', $auction)],
                ],
            ],
        ]);
    }

    /** Create a new pool. */
    public function store(Request $request, Auction $auction): RedirectResponse
    {
        $this->authorize('auction.edit');

        $data = $this->validatePool($request);

        AuctionPool::create([
            'auction_id' => $auction->id,
            'organization_id' => $auction->organization_id,
            'name' => $data['name'],
            'category' => $data['category'] ?? null,
            'base_price' => $data['base_price'] ?? null,
            'capacity' => $data['capacity'] ?? null,
            'order_mode' => $data['order_mode'],
            'sequence' => (int) AuctionPool::where('auction_id', $auction->id)->max('sequence') + 1,
        ]);

        return back()->with('success', __('Pool created.'));
    }

    /** Update pool settings. */
    public function update(Request $request, Auction $auction, AuctionPool $pool): RedirectResponse
    {
        $this->authorize('auction.edit');
        abort_unless($pool->auction_id === $auction->id, 404);

        $data = $this->validatePool($request);

        $pool->update([
            'name' => $data['name'],
            'category' => $data['category'] ?? null,
            'base_price' => $data['base_price'] ?? null,
            'capacity' => $data['capacity'] ?? null,
            'order_mode' => $data['order_mode'],
        ]);

        return back()->with('success', __('Pool updated.'));
    }

    /** Delete a pool. Its waiting players return to the unassigned bucket (row removed). */
    public function destroy(Request $request, Auction $auction, AuctionPool $pool): RedirectResponse|JsonResponse
    {
        $this->authorize('auction.edit');
        abort_unless($pool->auction_id === $auction->id, 404);

        // A running pool is the one the control panel is drawing from. Deleting it mid-room
        // strands the auction with no queue, and there is a supported way to stop a pool
        // (Close early on the panel) that keeps its history.
        if ($pool->isActive()) {
            $message = __('“:name” is running. Close it on the control panel before deleting it.', ['name' => $pool->name]);

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $message], 422)
                : back()->with('error', $message);
        }

        DB::transaction(function () use ($pool) {
            // Only clear players that haven't been actioned yet; sold/on-auction keep their row.
            $pool->players()->where('status', 'waiting')->delete();
            $pool->delete(); // FK nullOnDelete detaches any surviving (actioned) rows
        });

        $message = __('Pool deleted.');

        return $request->expectsJson()
            ? response()->json(['success' => true, 'message' => $message, 'deleted' => [$pool->id]])
            : back()->with('success', $message);
    }

    /**
     * Delete several pools in one go.
     *
     * Separate from destroy() rather than a loop over it in the client: a caller deleting
     * eight pools over eight requests can be interrupted half way, leaving the organizer
     * with a partial result and no way to tell which half went. One transaction either
     * removes all of them or none.
     *
     * A running pool in the selection is refused rather than skipped. Silently dropping it
     * would report "5 pools deleted" while the one the organizer most needs to know about
     * survived, and on this screen that difference decides whether the auction still works.
     */
    public function bulkDestroy(Request $request, Auction $auction): RedirectResponse|JsonResponse
    {
        $this->authorize('auction.edit');

        $data = $request->validate([
            'pool_ids' => 'required|array|min:1',
            'pool_ids.*' => 'integer',
        ]);

        // Scoped to this auction, so an id from another auction is simply not found rather
        // than deleted — route binding covers {pool}, and this endpoint has no {pool}.
        $pools = AuctionPool::where('auction_id', $auction->id)
            ->whereIn('id', $data['pool_ids'])
            ->get();

        if ($pools->isEmpty()) {
            $message = __('No matching pools to delete.');

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $message], 422)
                : back()->with('error', $message);
        }

        $running = $pools->filter(fn (AuctionPool $p) => $p->isActive());

        if ($running->isNotEmpty()) {
            $message = __('“:name” is running. Close it on the control panel first — nothing was deleted.', [
                'name' => $running->first()->name,
            ]);

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $message], 422)
                : back()->with('error', $message);
        }

        $ids = $pools->pluck('id')->all();

        DB::transaction(function () use ($pools) {
            foreach ($pools as $pool) {
                $pool->players()->where('status', 'waiting')->delete();
                $pool->delete();
            }
        });

        $message = trans_choice('{1} Pool deleted.|[2,*] :count pools deleted.', count($ids), ['count' => count($ids)]);

        return $request->expectsJson()
            ? response()->json(['success' => true, 'message' => $message, 'deleted' => $ids])
            : back()->with('success', $message);
    }

    /** Assign selected players to a pool (one pool per player — moving reassigns). */
    public function assign(Request $request, Auction $auction): RedirectResponse|JsonResponse
    {
        $this->authorize('auction.edit');

        $data = $request->validate([
            'pool_id' => 'required|integer',
            'player_ids' => 'required|array|min:1',
            'player_ids.*' => 'integer',
            'retained_prices' => 'nullable|array',
            'retained_prices.*' => 'nullable|numeric|min:0',
        ]);

        $pool = AuctionPool::where('auction_id', $auction->id)->findOrFail($data['pool_id']);
        $auction->loadMissing('tournament');
        $isAuctionType = $auction->tournament?->isAuction() ?? true;
        $retainedPrices = $data['retained_prices'] ?? [];

        // Only players with approved registration for this auction's tournament are assignable.
        $tournamentId = $auction->tournament_id;
        $eligible = Player::whereHas('registrations', fn ($q) => $q->where('tournament_id', $tournamentId)->where('status', 'approved'))
            ->when($auction->organization_id, fn ($q) => $q->where('organization_id', $auction->organization_id))
            ->whereIn('id', $data['player_ids'])
            ->get(['id', 'user_id', 'player_mode', 'actual_team_id', 'retained_value']);

        if ($eligible->isEmpty()) {
            return back()->with('error', __('No eligible players to assign.'));
        }

        DB::transaction(function () use ($auction, $pool, $eligible, $isAuctionType, $retainedPrices) {
            // player → pool → auction, resolved in one place.
            $base = $this->poolService->resolveBasePrice($auction, $pool);

            /*
             * The pools these players are LEAVING, so their lot order can be closed up.
             *
             * Only the destination was renumbered, so moving players out of a pool left holes in
             * its draw — lot 3 and lot 7 with nothing between them, and a "1 of 15" that counts
             * players who are no longer in it. Assigning from Unassigned is the common case and
             * has no source, which is why this went unnoticed.
             */
            $sourcePoolIds = [];

            foreach ($eligible as $player) {
                $existing = AuctionPlayer::where('auction_id', $auction->id)
                    ->where('player_id', $player->id)->first();

                // Never move a player who is already sold / on auction / closed.
                if ($existing && ! in_array($existing->status, ['waiting'], true)) {
                    continue;
                }

                // A player moved into a pool takes that pool's base price. Keeping the
                // old price meant moving someone from a 10K pool into a 500K marquee
                // pool left them priced at 10K.
                $movedPool = $existing && (int) $existing->auction_pool_id !== (int) $pool->id;

                if ($movedPool && $existing->auction_pool_id) {
                    $sourcePoolIds[(int) $existing->auction_pool_id] = true;
                }
                $price = ($existing && ! $movedPool && $existing->base_price !== null)
                    ? $existing->base_price
                    : $base;

                // Retained players are pre-kept: assigned to their team up front, not
                // drawn for bidding, and their retention price counts against budget.
                $isRetained = $player->player_mode === 'retained';
                $attrs = [
                    'auction_pool_id' => $pool->id,
                    'organization_id' => $auction->organization_id,
                    'base_price' => $price,
                    'current_price' => $price,
                    'starting_price' => $price,
                    'status' => 'waiting',
                    'is_retained' => $isRetained,
                ];

                if ($isRetained) {
                    $attrs['team_id'] = $player->actual_team_id;
                    $attrs['lot_number'] = null; // retained players don't draw a lot
                    if ($isAuctionType) {
                        // A blank field used to be written straight through as 0 — and
                        // because this is an updateOrCreate, re-assigning an already-priced
                        // retained player wiped their price and the team got them free.
                        $attrs['retained_price'] = $this->poolService->resolveRetainedPrice(
                            $auction,
                            $player,
                            $retainedPrices[$player->id] ?? null,
                            $existing
                        );
                    }
                }

                AuctionPlayer::updateOrCreate(
                    ['auction_id' => $auction->id, 'player_id' => $player->id],
                    $attrs
                );

                // "Added initially": put retained players onto their team roster now.
                if ($isRetained && $player->actual_team_id && $player->user_id) {
                    $team = ActualTeam::find($player->actual_team_id);
                    $team?->users()->syncWithoutDetaching([$player->user_id => ['role' => 'Player']]);
                }
            }

            // Only non-retained players get lot numbers.
            $this->poolService->generateLotNumbers($pool);

            // And the pools they came out of, so neither draw has holes in it.
            foreach (array_keys($sourcePoolIds) as $sourceId) {
                if ($source = AuctionPool::where('auction_id', $auction->id)->find($sourceId)) {
                    $this->poolService->generateLotNumbers($source);
                }
            }
        });

        $moved = __('Players assigned to pool.');

        // The pools screen moves players over fetch and re-renders itself; a redirect would
        // hand it a page of HTML to ignore.
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $moved]);
        }

        return back()->with('success', $moved);
    }

    /** Remove a single player from its pool (returns them to the unassigned bucket). */
    public function unassign(Request $request, Auction $auction): RedirectResponse|JsonResponse
    {
        $this->authorize('auction.edit');

        $data = $request->validate(['player_id' => 'required|integer']);

        $ap = AuctionPlayer::where('auction_id', $auction->id)
            ->where('player_id', $data['player_id'])->first();

        if (! $ap) {
            return $this->poolReply($request, false, __('Player is not in this auction.'));
        }
        if ($ap->status !== 'waiting') {
            return $this->poolReply($request, false, __('Cannot unassign a player who is already in play or sold.'));
        }

        $pool = $ap->pool;
        $ap->delete();
        if ($pool) {
            $this->poolService->generateLotNumbers($pool);
        }

        return $this->poolReply($request, true, __('Player removed from pool.'), [$data['player_id']]);
    }

    /**
     * Remove several players from their pools at once.
     *
     * Anyone already in play or sold is reported rather than skipped: "8 removed" when one
     * of them is the player currently on the block would be a quietly wrong answer to the
     * only question the organizer is asking.
     */
    public function bulkUnassign(Request $request, Auction $auction): RedirectResponse|JsonResponse
    {
        $this->authorize('auction.edit');

        $data = $request->validate([
            'player_ids' => 'required|array|min:1',
            'player_ids.*' => 'integer',
        ]);

        $rows = AuctionPlayer::where('auction_id', $auction->id)
            ->whereIn('player_id', $data['player_ids'])
            ->get();

        if ($rows->isEmpty()) {
            return $this->poolReply($request, false, __('None of those players are in this auction.'));
        }

        $inPlay = $rows->firstWhere(fn (AuctionPlayer $ap) => $ap->status !== 'waiting');

        if ($inPlay) {
            return $this->poolReply($request, false, __('“:name” is already in play or sold — nothing was removed.', [
                'name' => $inPlay->player->name ?? ('Player #' . $inPlay->player_id),
            ]));
        }

        $playerIds = $rows->pluck('player_id')->all();
        $poolIds = $rows->pluck('auction_pool_id')->filter()->unique();

        DB::transaction(function () use ($rows, $poolIds) {
            AuctionPlayer::whereIn('id', $rows->pluck('id'))->delete();

            // Lot numbers are positional, so every touched pool has to be redrawn once —
            // after the deletes, not per player, or the intermediate draws are wasted.
            foreach ($poolIds as $poolId) {
                if ($pool = AuctionPool::find($poolId)) {
                    $this->poolService->generateLotNumbers($pool);
                }
            }
        });

        return $this->poolReply(
            $request,
            true,
            trans_choice('{1} Player removed from pool.|[2,*] :count players removed from their pools.', count($playerIds), ['count' => count($playerIds)]),
            $playerIds
        );
    }

    /**
     * One reply shape for the pool-screen mutations.
     *
     * The screen acts over fetch so it keeps its scroll position and any open inline edit
     * forms; the same endpoints still answer a plain form post with a redirect, so nothing
     * depends on JavaScript being reachable.
     */
    private function poolReply(Request $request, bool $ok, string $message, array $affected = []): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(
                ['success' => $ok, 'message' => $message, 'affected' => $affected],
                $ok ? 200 : 422
            );
        }

        return back()->with($ok ? 'success' : 'error', $message);
    }

    /** Auto-group all unassigned approved players into pools by player type. */
    public function autoAssign(Auction $auction): RedirectResponse
    {
        $this->authorize('auction.edit');

        $pooledPlayerIds = AuctionPlayer::where('auction_id', $auction->id)
            ->whereNotNull('auction_pool_id')->pluck('player_id')->all();

        $tournamentId = $auction->tournament_id;
        $players = Player::whereHas('registrations', fn ($q) => $q->where('tournament_id', $tournamentId)->where('status', 'approved'))
            ->when($auction->organization_id, fn ($q) => $q->where('organization_id', $auction->organization_id))
            ->whereNotIn('id', $pooledPlayerIds)
            /*
             * Retained players are never auto-grouped. Their auction row is deliberately
             * pool-less (see AuctionPoolService::syncRetainedPlayers()), and "unassigned"
             * here means `auction_pool_id IS NULL` — so without this filter every sweep
             * would pull the retained rows straight back into a bidding queue they must
             * never be in.
             */
            ->where(fn ($q) => $q->where('player_mode', '!=', 'retained')->orWhereNull('player_mode'))
            ->with('playerType')
            ->get();

        if ($players->isEmpty()) {
            return back()->with('error', __('No unassigned players to auto-group.'));
        }

        $groups = $players->groupBy(fn ($p) => $p->playerType->name ?? __('Uncategorized'));

        // Everything this run touches, so it can be reverted in one action. Auto-assign
        // sweeps every unassigned player into pools at once — without a record of what it
        // did, a mistaken run had to be unpicked by hand.
        $created = ['pools' => [], 'players' => []];

        DB::transaction(function () use ($auction, $groups, &$created) {
            $seq = (int) AuctionPool::where('auction_id', $auction->id)->max('sequence');
            foreach ($groups as $category => $groupPlayers) {
                // Reuse an existing pool with this category name, else create one.
                $pool = AuctionPool::where('auction_id', $auction->id)
                    ->where('name', $category)->first();

                if (! $pool) {
                    $pool = AuctionPool::create([
                        'auction_id' => $auction->id,
                        'organization_id' => $auction->organization_id,
                        'name' => (string) $category,
                        'category' => (string) $category,
                        'base_price' => $auction->base_price,
                        'order_mode' => 'sequential',
                        'sequence' => ++$seq,
                    ]);
                    // Only pools this run created are removed on revert.
                    $created['pools'][] = $pool->id;
                }

                $base = $this->poolService->resolveBasePrice($auction, $pool);
                foreach ($groupPlayers as $player) {
                    $existing = AuctionPlayer::where('auction_id', $auction->id)
                        ->where('player_id', $player->id)
                        ->first();

                    $created['players'][] = [
                        'player_id' => $player->id,
                        // A row this run created is deleted on revert; a row it moved is
                        // put back where it was.
                        'was_new' => $existing === null,
                        'previous_pool_id' => $existing?->auction_pool_id,
                        'previous_lot_number' => $existing?->lot_number,
                    ];

                    AuctionPlayer::updateOrCreate(
                        ['auction_id' => $auction->id, 'player_id' => $player->id],
                        [
                            'auction_pool_id' => $pool->id,
                            'organization_id' => $auction->organization_id,
                            'base_price' => $base,
                            'current_price' => $base,
                            'starting_price' => $base,
                            'status' => 'waiting',
                            // Auto-grouping never set this, so a retained player swept up
                            // here became biddable and their retention cost stopped
                            // counting against the team's budget.
                            'is_retained' => $player->player_mode === 'retained',
                            'team_id' => $player->player_mode === 'retained' ? $player->actual_team_id : null,
                        ]
                    );
                }
                $this->poolService->generateLotNumbers($pool);
            }
        });

        // Record the run so it can be reverted from the pools screen.
        AuctionActionLog::create([
            'auction_id' => $auction->id,
            'action' => self::ACTION_AUTO_ASSIGN,
            'payload' => $created,
            'description' => sprintf(
                'Auto-assigned %d player(s) into %d new pool(s)',
                count($created['players']),
                count($created['pools'])
            ),
            'user_id' => auth()->id(),
        ]);

        return back()->with('success', trans_choice(
            ':count player auto-grouped into pools by type.|:count players auto-grouped into pools by type.',
            count($created['players']),
            ['count' => count($created['players'])]
        ));
    }

    /**
     * Undo the most recent auto-assign run.
     *
     * Rows the run created are deleted, rows it moved go back to the pool and lot they
     * came from, and pools it created are removed once empty. Any player who has since
     * been actioned (on the block, sold, unsold) is deliberately left alone — reverting
     * those would rewrite auction history rather than a configuration mistake.
     */
    public function revertAutoAssign(Auction $auction): RedirectResponse
    {
        $this->authorize('auction.edit');

        $log = AuctionActionLog::where('auction_id', $auction->id)
            ->where('action', self::ACTION_AUTO_ASSIGN)
            ->pending()
            ->orderByDesc('id')
            ->first();

        if (! $log) {
            return back()->with('error', __('There is no auto-assign run left to revert.'));
        }

        $payload = $log->payload ?? [];
        $reverted = 0;
        $skipped = 0;

        DB::transaction(function () use ($auction, $payload, $log, &$reverted, &$skipped) {
            $poolsToRedraw = [];

            foreach ($payload['players'] ?? [] as $entry) {
                $row = AuctionPlayer::where('auction_id', $auction->id)
                    ->where('player_id', $entry['player_id'] ?? 0)
                    ->first();

                if (! $row) {
                    continue;
                }

                // Already in play or resolved — leave it be.
                if ($row->status !== 'waiting') {
                    $skipped++;
                    continue;
                }

                if (! empty($entry['was_new'])) {
                    $row->delete();
                } else {
                    $row->update([
                        'auction_pool_id' => $entry['previous_pool_id'] ?? null,
                        'lot_number' => $entry['previous_lot_number'] ?? null,
                    ]);
                    if (! empty($entry['previous_pool_id'])) {
                        $poolsToRedraw[$entry['previous_pool_id']] = true;
                    }
                }

                $reverted++;
            }

            // Pools this run created are only removed if nothing landed in them since.
            foreach ($payload['pools'] ?? [] as $poolId) {
                $pool = AuctionPool::where('auction_id', $auction->id)->find($poolId);
                if ($pool && $pool->players()->count() === 0) {
                    $pool->delete();
                }
            }

            foreach (array_keys($poolsToRedraw) as $poolId) {
                if ($target = AuctionPool::find($poolId)) {
                    $this->poolService->generateLotNumbers($target);
                }
            }

            $log->update(['undone_at' => now(), 'undone_by' => auth()->id()]);
        });

        return back()->with('success', sprintf(
            '%d player(s) returned to unassigned.%s',
            $reverted,
            $skipped > 0 ? sprintf(' %d left in place — already in play or sold.', $skipped) : ''
        ));
    }

    private function validatePool(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'base_price' => 'nullable|numeric|min:0',
            'capacity' => 'nullable|integer|min:1',
            'order_mode' => 'required|in:' . implode(',', self::ORDER_MODES),
        ]);
    }
}

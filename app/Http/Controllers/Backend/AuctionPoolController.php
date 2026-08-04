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

        $auction->load([
            'tournament',
            'pools' => fn ($q) => $q->orderBy('sequence'),
            'pools.players.player.playerType',
            'pools.players.team:id,name',
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
            ->with(['playerType', 'actualTeam:id,name'])
            ->orderBy('name')
            ->get(['id', 'name', 'player_type_id', 'organization_id', 'player_mode', 'actual_team_id']);

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
                ->map(fn ($t) => [
                    'team' => $t,
                    'allocated' => $this->poolService->allocatedBudget($auction, $t->id),
                    'retained' => $this->poolService->retainedSpent($auction, $t->id),
                    'sold' => $this->poolService->soldSpent($auction, $t->id),
                    'remaining' => $this->poolService->remainingBudget($auction, $t->id),
                ]);
        }

        return view('backend.pages.auctions.pools.index', [
            'auction' => $auction,
            'pools' => $auction->pools,
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
    public function destroy(Auction $auction, AuctionPool $pool): RedirectResponse
    {
        $this->authorize('auction.edit');
        abort_unless($pool->auction_id === $auction->id, 404);

        DB::transaction(function () use ($pool) {
            // Only clear players that haven't been actioned yet; sold/on-auction keep their row.
            $pool->players()->where('status', 'waiting')->delete();
            $pool->delete(); // FK nullOnDelete detaches any surviving (actioned) rows
        });

        return back()->with('success', __('Pool deleted.'));
    }

    /** Assign selected players to a pool (one pool per player — moving reassigns). */
    public function assign(Request $request, Auction $auction): RedirectResponse
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
            ->get(['id', 'user_id', 'player_mode', 'actual_team_id']);

        if ($eligible->isEmpty()) {
            return back()->with('error', __('No eligible players to assign.'));
        }

        DB::transaction(function () use ($auction, $pool, $eligible, $isAuctionType, $retainedPrices) {
            // player → pool → auction, resolved in one place.
            $base = $this->poolService->resolveBasePrice($auction, $pool);

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
                        // decimal(15,2) since the column was widened — no longer rounded
                        // to a whole number.
                        $attrs['retained_price'] = (float) ($retainedPrices[$player->id] ?? 0);
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
        });

        return back()->with('success', __('Players assigned to pool.'));
    }

    /** Remove a single player from its pool (returns them to the unassigned bucket). */
    public function unassign(Request $request, Auction $auction): RedirectResponse
    {
        $this->authorize('auction.edit');

        $data = $request->validate(['player_id' => 'required|integer']);

        $ap = AuctionPlayer::where('auction_id', $auction->id)
            ->where('player_id', $data['player_id'])->first();

        if (! $ap) {
            return back()->with('error', __('Player is not in this auction.'));
        }
        if ($ap->status !== 'waiting') {
            return back()->with('error', __('Cannot unassign a player who is already in play or sold.'));
        }

        $pool = $ap->pool;
        $ap->delete();
        if ($pool) {
            $this->poolService->generateLotNumbers($pool);
        }

        return back()->with('success', __('Player removed from pool.'));
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

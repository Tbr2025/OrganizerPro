<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ActualTeam;
use App\Models\Auction;
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
    private const ORDER_MODES = ['sequential', 'random', 'odd_even', 'manual'];

    public function __construct(private readonly AuctionPoolService $poolService)
    {
    }

    /** The dedicated Pools management screen. */
    public function index(Auction $auction): View
    {
        $this->authorize('auction.view');

        $auction->load(['tournament', 'pools' => fn ($q) => $q->orderBy('sequence'), 'pools.players.player.playerType', 'pools.players.team:id,name']);
        $isAuctionType = $auction->tournament?->isAuction() ?? true;

        // Players already sitting in a pool for this auction.
        $pooledPlayerIds = AuctionPlayer::where('auction_id', $auction->id)
            ->whereNotNull('auction_pool_id')
            ->pluck('player_id')->all();

        // Available (unassigned) = approved players in the auction's org, minus pooled.
        // Retained players (player_mode) are flagged so the UI can collect a retention price.
        $available = Player::where('status', 'approved')
            ->when($auction->organization_id, fn ($q) => $q->where('organization_id', $auction->organization_id))
            ->whereNotIn('id', $pooledPlayerIds)
            ->with(['playerType', 'actualTeam:id,name'])
            ->orderBy('name')
            ->get(['id', 'name', 'player_type_id', 'organization_id', 'player_mode', 'actual_team_id']);

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

        // Org isolation: only approved players of this auction's org are assignable.
        $eligible = Player::where('status', 'approved')
            ->when($auction->organization_id, fn ($q) => $q->where('organization_id', $auction->organization_id))
            ->whereIn('id', $data['player_ids'])
            ->get(['id', 'user_id', 'player_mode', 'actual_team_id']);

        if ($eligible->isEmpty()) {
            return back()->with('error', __('No eligible players to assign.'));
        }

        DB::transaction(function () use ($auction, $pool, $eligible, $isAuctionType, $retainedPrices) {
            $base = $pool->base_price ?? $auction->base_price;

            foreach ($eligible as $player) {
                $existing = AuctionPlayer::where('auction_id', $auction->id)
                    ->where('player_id', $player->id)->first();

                // Never move a player who is already sold / on auction / closed.
                if ($existing && ! in_array($existing->status, ['waiting'], true)) {
                    continue;
                }

                // Retained players are pre-kept: assigned to their team up front, not
                // drawn for bidding, and their retention price counts against budget.
                $isRetained = $player->player_mode === 'retained';
                $attrs = [
                    'auction_pool_id' => $pool->id,
                    'organization_id' => $auction->organization_id,
                    'base_price' => $existing->base_price ?? $base,
                    'current_price' => $existing->current_price ?? $base,
                    'starting_price' => $existing->starting_price ?? $base,
                    'status' => 'waiting',
                    'is_retained' => $isRetained,
                ];

                if ($isRetained) {
                    $attrs['team_id'] = $player->actual_team_id;
                    $attrs['lot_number'] = null; // retained players don't draw a lot
                    if ($isAuctionType) {
                        $attrs['retained_price'] = (int) round((float) ($retainedPrices[$player->id] ?? 0));
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

        $players = Player::where('status', 'approved')
            ->when($auction->organization_id, fn ($q) => $q->where('organization_id', $auction->organization_id))
            ->whereNotIn('id', $pooledPlayerIds)
            ->with('playerType')
            ->get();

        if ($players->isEmpty()) {
            return back()->with('error', __('No unassigned players to auto-group.'));
        }

        $groups = $players->groupBy(fn ($p) => $p->playerType->name ?? __('Uncategorized'));

        DB::transaction(function () use ($auction, $groups) {
            $seq = (int) AuctionPool::where('auction_id', $auction->id)->max('sequence');
            foreach ($groups as $category => $groupPlayers) {
                // Reuse an existing pool with this category name, else create one.
                $pool = AuctionPool::where('auction_id', $auction->id)
                    ->where('name', $category)->first()
                    ?? AuctionPool::create([
                        'auction_id' => $auction->id,
                        'organization_id' => $auction->organization_id,
                        'name' => (string) $category,
                        'category' => (string) $category,
                        'base_price' => $auction->base_price,
                        'order_mode' => 'sequential',
                        'sequence' => ++$seq,
                    ]);

                $base = $pool->base_price ?? $auction->base_price;
                foreach ($groupPlayers as $player) {
                    AuctionPlayer::updateOrCreate(
                        ['auction_id' => $auction->id, 'player_id' => $player->id],
                        [
                            'auction_pool_id' => $pool->id,
                            'organization_id' => $auction->organization_id,
                            'base_price' => $base,
                            'current_price' => $base,
                            'starting_price' => $base,
                            'status' => 'waiting',
                        ]
                    );
                }
                $this->poolService->generateLotNumbers($pool);
            }
        });

        return back()->with('success', __('Players auto-grouped into pools by type.'));
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

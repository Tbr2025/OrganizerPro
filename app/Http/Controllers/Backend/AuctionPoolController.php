<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ActualTeam;
use App\Models\Auction;
use App\Models\AuctionPlayer;
use App\Models\AuctionPool;
use App\Models\AuctionTeamBudget;
use App\Services\Auction\AuctionPoolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuctionPoolController extends Controller
{
    public function __construct(private AuctionPoolService $pools)
    {
    }

    /** Pool management screen for an auction. */
    public function index(Auction $auction)
    {
        $auction->load(['pools.players.player', 'teamBudgets.team']);

        $unassigned = AuctionPlayer::with('player')
            ->where('auction_id', $auction->id)
            ->whereNull('auction_pool_id')
            ->get();

        // Teams in this auction's tournament (for budget allocation).
        $teams = $auction->tournament
            ? ActualTeam::where('tournament_id', $auction->tournament_id)
                ->orWhereHas('tournaments', fn ($q) => $q->where('tournaments.id', $auction->tournament_id))
                ->get()
            : ActualTeam::query()->get();

        return view('backend.pages.auction.pools', [
            'auction' => $auction,
            'unassigned' => $unassigned,
            'teams' => $teams,
            'orderModes' => [
                AuctionPool::MODE_SEQUENTIAL => 'Sequential (1,2,3…)',
                AuctionPool::MODE_RANDOM => 'Random',
                AuctionPool::MODE_ODD_EVEN => 'Odd then Even',
                AuctionPool::MODE_MANUAL => 'Manual',
            ],
        ]);
    }

    public function store(Request $request, Auction $auction): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'capacity' => 'nullable|integer|min:1',
            'order_mode' => 'required|in:sequential,random,odd_even,manual',
            'is_unsold_pool' => 'nullable|boolean',
        ]);

        $pool = AuctionPool::create([
            'auction_id' => $auction->id,
            'organization_id' => $auction->organization_id,
            'name' => $data['name'],
            'capacity' => $data['capacity'] ?? null,
            'order_mode' => $data['order_mode'],
            'sequence' => (int) ($auction->pools()->max('sequence') ?? 0) + 1,
            'is_unsold_pool' => (bool) ($data['is_unsold_pool'] ?? false),
        ]);

        return response()->json(['success' => true, 'pool' => $pool]);
    }

    public function update(Request $request, Auction $auction, AuctionPool $pool): JsonResponse
    {
        abort_unless($pool->auction_id === $auction->id, 404);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'capacity' => 'nullable|integer|min:1',
            'order_mode' => 'required|in:sequential,random,odd_even,manual',
        ]);

        $modeChanged = $pool->order_mode !== $data['order_mode'];
        $pool->update([
            'name' => $data['name'],
            'capacity' => $data['capacity'] ?? null,
            'order_mode' => $data['order_mode'],
        ]);

        // Re-draw lots when the ordering rule changes.
        if ($modeChanged) {
            $this->pools->generateLotNumbers($pool);
        }

        return response()->json(['success' => true]);
    }

    public function destroy(Auction $auction, AuctionPool $pool): JsonResponse
    {
        abort_unless($pool->auction_id === $auction->id, 404);

        // Detach players back to the unassigned bucket, then remove the pool.
        $pool->players()->update(['auction_pool_id' => null, 'lot_number' => null]);
        $pool->delete();

        return response()->json(['success' => true]);
    }

    /** Move the given auction_player ids into a pool (respecting capacity). */
    public function assignPlayers(Request $request, Auction $auction, AuctionPool $pool): JsonResponse
    {
        abort_unless($pool->auction_id === $auction->id, 404);

        $data = $request->validate([
            'auction_player_ids' => 'required|array|min:1',
            'auction_player_ids.*' => 'integer',
        ]);

        $ids = $data['auction_player_ids'];

        if ($pool->capacity !== null) {
            $current = $pool->players()->count();
            $room = max(0, $pool->capacity - $current);
            $ids = array_slice($ids, 0, $room);
        }

        AuctionPlayer::where('auction_id', $auction->id)
            ->whereIn('id', $ids)
            ->update(['auction_pool_id' => $pool->id, 'lot_number' => null]);

        return response()->json(['success' => true, 'assigned' => count($ids)]);
    }

    /** Draw lots for a pool according to its order mode. */
    public function drawLots(Auction $auction, AuctionPool $pool): JsonResponse
    {
        abort_unless($pool->auction_id === $auction->id, 404);

        $this->pools->generateLotNumbers($pool);

        return response()->json([
            'success' => true,
            'lots' => $pool->players()->orderBy('lot_number')
                ->with('player:id,name')
                ->get(['id', 'player_id', 'lot_number']),
        ]);
    }

    /** Upsert per-team budget allocations for the auction. */
    public function allocateBudgets(Request $request, Auction $auction): JsonResponse
    {
        $data = $request->validate([
            'budgets' => 'required|array',
            'budgets.*.actual_team_id' => 'required|integer|exists:actual_teams,id',
            'budgets.*.budget' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($auction, $data) {
            foreach ($data['budgets'] as $row) {
                AuctionTeamBudget::updateOrCreate(
                    ['auction_id' => $auction->id, 'actual_team_id' => $row['actual_team_id']],
                    ['organization_id' => $auction->organization_id, 'budget' => $row['budget']]
                );
            }
        });

        return response()->json(['success' => true]);
    }
}

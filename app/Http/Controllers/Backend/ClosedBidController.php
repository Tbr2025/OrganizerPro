<?php

namespace App\Http\Controllers\Backend;

use App\Events\PlayerOnBidEvent;
use App\Http\Controllers\Controller;
use App\Models\ActualTeam;
use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionPlayer;
use App\Services\Auction\AuctionPoolService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClosedBidController extends Controller
{
    /**
     * Auctions this user may see.
     *
     * This page used to list every auction and every team in the database to anybody who
     * could reach the URL — the routes carry no permission gate of their own, and
     * `organizer.access` only constrains users holding the Organizer role, and only on
     * route-bound models, of which these routes have none.
     */
    private function visibleAuctions()
    {
        $user = Auth::user();
        $query = Auction::orderBy('name');

        if ($user && ! $user->hasRole('Superadmin') && $user->organization_id) {
            $query->where('organization_id', $user->organization_id);
        }

        return $query;
    }

    private function visibleTeams()
    {
        $user = Auth::user();
        $query = ActualTeam::orderBy('name');

        if ($user && ! $user->hasRole('Superadmin') && $user->organization_id) {
            $query->where('organization_id', $user->organization_id);
        }

        return $query;
    }

    /**
     * Show the Closed Bids page
     */
    public function index()
    {
        $this->authorize('auction.view');

        $auctions = $this->visibleAuctions()->get(['id', 'name']);
        $teams = $this->visibleTeams()->get(['id', 'name']);
        $breadcrumbs = ['title' => __('Closed Bids')];

        return view('backend.pages.auctions.closed-bids', compact('auctions', 'teams', 'breadcrumbs'));
    }

    /**
     * Fetch closed bids for AJAX
     */
    public function fetchClosedBids(Request $request)
    {
        $this->authorize('auction.view');

        // Scoped to auctions this user may actually see. Without this the endpoint
        // returned every organization's closed bids to anybody who could reach the URL.
        $query = AuctionPlayer::with(['player', 'soldToTeam', 'auction', 'bids.team'])
            ->where('status', 'closed')
            ->whereIn('auction_id', $this->visibleAuctions()->pluck('id'));

        if ($request->filled('auction_id')) {
            $query->where('auction_id', $request->auction_id);
        }

        if ($request->filled('team_id')) {
            // Buyer is tracked as sold_to_team_id on auction_players (no plain team_id).
            $query->where('sold_to_team_id', $request->team_id);
        }

        $closedBids = $query->orderBy('updated_at', 'desc')->get()
            ->each(function (AuctionPlayer $row) {
                // This page spans several auctions, so each row carries its own unit —
                // one auction can run in points and another in dollars.
                $row->amount_unit = $row->auction?->amountUnitConfig()
                    ?? ['label' => 'Points', 'prefix' => false];
            });

        // Fetch all auctions and teams for dropdowns
        $auctions = Auction::orderBy('name')->get(['id', 'name']);
        $teams = ActualTeam::orderBy('name')->get(['id', 'name']);

        return response()->json([
            'closedBids' => $closedBids,
            'auctions' => $auctions,
            'teams' => $teams,
        ]);
    }

    // public function updateFinalPrice(Request $request, $id)
    // {
    //     $user = Auth::user();
    //     $bid = AuctionPlayer::with('auction')->findOrFail($id);

    //     // Only allow TeamManager to update their own team's bid
    //     if ($user->hasRole('TeamManager') && $bid->sold_to_team_id != $user->team_id) {
    //         return response()->json(['error' => 'Unauthorized'], 403);
    //     }

    //     $request->validate([
    //         'final_price' => 'required|numeric|min:0',
    //     ]);

    //     $newPrice = $request->final_price;

    //     // Step 1: Get auction max budget per team
    //     $maxBudget = $bid->auction->max_budget_per_team;

    //     // Step 2: Sum all previous bids from this team in this auction
    //     // Exclude the current bid itself to prevent double-counting

    //     $spentBudget = AuctionBid::where('auction_id', $bid->auction_id)
    //         ->where('team_id', $bid->sold_to_team_id)
    //         ->sum('amount');

    //     // Step 3: Calculate available balance
    //     $availableBalance = $maxBudget - $spentBudget;

    //     // Step 4: Check if new final price exceeds available balance
    //     if ($newPrice > $availableBalance) {
    //         return response()->json([
    //             'error' => 'Insufficient team balance. Available: ' . number_format($availableBalance / 1000000, 1) . 'M'
    //         ], 400);
    //     }

    //     // Step 5: Update final price
    //     $bid->final_price = $newPrice;
    //     $bid->save();

    //     return response()->json(['success' => true, 'final_price' => $bid->final_price]);
    // }

    public function updateFinalPrice(Request $request, Auction $auction, $playerId)
    {
        $this->authorize('auction.edit');

        $request->validate([
            'final_price' => 'required|numeric|min:0',
        ]);

        $bid = AuctionPlayer::where('auction_id', $auction->id)
            ->where('id', $playerId)
            ->firstOrFail();

        $newPrice = (float) $request->final_price;
        $teamId = (int) $bid->sold_to_team_id;

        // Budget check via the one canonical implementation. This used to sum
        // *every* row in auction_bids for the team, which is a log of all bids
        // ever placed — so it massively over-counted spend and blocked legitimate
        // corrections. AuctionPoolService honours per-team allocations and
        // retained cost, and the squad reserve is applied on top.
        if ($teamId) {
            $pools = app(AuctionPoolService::class);
            $team = ActualTeam::find($teamId);

            // The player being re-priced already counts toward spend, so credit
            // their current price back before testing the new one.
            $headroom = $pools->maxAllowedBid($auction, $teamId) + (float) $bid->final_price;

            if ($newPrice > $headroom) {
                return response()->json([
                    'error' => 'Insufficient team balance. ' . ($team?->name ?? 'This team')
                        . ' can go up to ' . format_points($headroom) . ' on this player.',
                ], 400);
            }
        }

        $bid->final_price = $newPrice;
        $bid->current_price = $newPrice;
        $bid->save();

        // Record the correction as its own bid row (the log is append-only).
        AuctionBid::create([
            'auction_id' => $auction->id,
            'auction_player_id' => $bid->id,
            'team_id' => $teamId ?: null,
            'user_id' => auth()->id(),
            'player_id' => $bid->player_id,
            'amount' => $newPrice,
            'bid_source' => 'offline',
        ]);

        $team = ActualTeam::find($bid->sold_to_team_id);
        broadcast(new PlayerOnBidEvent($bid, $team))->toOthers();

        return response()->json(['success' => true, 'final_price' => $bid->final_price]);
    }
}

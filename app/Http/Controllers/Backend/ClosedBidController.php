<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ActualTeam;
use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionPlayer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClosedBidController extends Controller
{
    /**
     * Show the Closed Bids page
     */
    public function index()
    {
        $auctions = Auction::orderBy('name')->get(['id', 'name']);
        $teams = ActualTeam::orderBy('name')->get(['id', 'name']);

        return view('backend.pages.auctions.closed-bids', compact('auctions', 'teams'));
    }


    /**
     * Fetch closed bids for AJAX
     */
    public function fetchClosedBids(Request $request)
    {
        $user = Auth::user();

        // Query closed bids
        $query = AuctionPlayer::with(['player', 'soldToTeam', 'auction'])
            ->where('status', 'closed');

        if ($user->hasRole('TeamManager')) {
            $query->whereHas('soldToTeam', function ($q) use ($user) {
                $q->where('id', $user->team_id);
            });
        }

        if ($request->filled('auction_id')) {
            $query->where('auction_id', $request->auction_id);
        }

        if ($request->filled('team_id')) {
            $query->where('team_id', $request->team_id);
        }

        $closedBids = $query->orderBy('updated_at', 'desc')->get();

        // Fetch all auctions and teams for dropdowns
        $auctions = Auction::orderBy('name')->get(['id', 'name']);
        $teams = ActualTeam::orderBy('name')->get(['id', 'name']);

        return response()->json([
            'closedBids' => $closedBids,
            'auctions'   => $auctions,
            'teams'      => $teams,
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
        $request->validate([
            'final_price' => 'required|numeric|min:0',
        ]);

        $bid = AuctionPlayer::where('auction_id', $auction->id)
            ->where('id', $playerId)
            ->firstOrFail();

        $newPrice = $request->final_price;

        // Get auction max budget per team
        $maxBudget = $auction->max_budget_per_team;

        // Sum all previous bids from this team in this auction (exclude current player)
        $spentBudget = AuctionBid::where('auction_id', $auction->id)
            ->where('team_id', $bid->sold_to_team_id)
            ->where('auction_player_id', '!=', $bid->id)
            ->sum('amount');

        $availableBalance = $maxBudget - $spentBudget;

        if ($newPrice > $availableBalance) {
            return response()->json([
                'error' => 'Insufficient team balance. Available: ' . number_format($availableBalance / 1000000, 1) . 'M'
            ], 400);
        }

        $bid->final_price = $newPrice;
        $bid->save();

        return response()->json(['success' => true, 'final_price' => $bid->final_price]);
    }
}

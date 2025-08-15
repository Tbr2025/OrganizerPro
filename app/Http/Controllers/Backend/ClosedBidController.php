<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ActualTeam;
use App\Models\Auction;
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


    public function updateFinalPrice(Request $request, $id)
{
    $user = Auth::user();

    $bid = AuctionPlayer::findOrFail($id);

    // Only allow TeamManager to update their own team's bid
    if ($user->hasRole('TeamManager') && $bid->sold_to_team_id != $user->team_id) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    $request->validate([
        'final_price' => 'required|numeric|min:0',
    ]);

    $bid->final_price = $request->final_price;
    $bid->save();

    return response()->json(['success' => true, 'final_price' => $bid->final_price]);
}

}

<?php

namespace App\Http\Controllers\Backend;

use App\Events\BidPlaced;
use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionPlayer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;

class AuctionLiveController extends Controller
{
    public function index(Auction $auction)
    {
        $breadcrumbs = ['title' => __('Live Auction')];
        $currentPlayer = $auction->players()->where('sold', false)->first();

        return view('backend.pages.auctions.live', compact('auction', 'currentPlayer', 'breadcrumbs'));
    }

    public function placeBid(Request $request, Auction $auction)
    {
        $request->validate([
            'player_id' => 'required|integer',
            'bid_amount' => 'required|numeric|min:0',
        ]);

        // Save bid
        $bid = AuctionBid::create([
            'auction_id' => $auction->id,
            'player_id' => $request->player_id,
            'team_id'   => auth()->user()->team_id,
            'user_id'   => auth()->id(),
            'amount'    => $request->amount,
        ]);

        // Broadcast bid
        broadcast(new BidPlaced($auction->id, $bid))->toOthers();

        return response()->json(['status' => 'success']);
    }
}

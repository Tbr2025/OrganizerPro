<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\AuctionPlayer;
use Illuminate\Http\Request;

class LiveBidController extends Controller
{
    public function placeBid(Request $request)
    {
        $request->validate([
            'auction_player_id' => 'required|exists:auction_players,id',
            'team_id' => 'required|exists:teams,id',
            'amount' => 'required|integer|min:1',
        ]);

        $auctionPlayer = AuctionPlayer::findOrFail($request->auction_player_id);

        // Update the player’s current bid
        $auctionPlayer->current_price = $request->amount;
        $auctionPlayer->current_bid_team_id = $request->team_id;
        $auctionPlayer->status = 'on_auction';
        $auctionPlayer->save();

        // Broadcast to all connected clients
        broadcast(new \App\Events\BidPlaced($auctionPlayer))->toOthers();

        return response()->json(['success' => true]);
    }
}

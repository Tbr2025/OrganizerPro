<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Bid;
use Illuminate\Http\Request;

class BidController extends Controller
{
   public function placeBid(Request $request)
    {
        $request->validate([
            'auction_id' => 'required|exists:auctions,id',
            'player_id' => 'required|exists:players,id',
            'team_id' => 'required|exists:teams,id',
            'bid_amount' => 'required|numeric|min:0'
        ]);

        $bid = Bid::create($request->all());

        broadcast(new \App\Events\NewBidPlaced($bid))->toOthers();

        return response()->json(['status' => 'ok']);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use Illuminate\Http\Request;

class PublicAuctionController extends Controller
{
    /**
     * Display the public, real-time auction screen.
     */
    public function showPublicDisplay(Auction $auction)
    {
        // Pass any initial state data needed, similar to the bidding page
        $initialState = [
            'status' => $auction->status,
            'currentPlayer' => $auction->auctionPlayers()->where('status', 'live')->with(['player', 'bids.team'])->first(),
        ];

        return view('public.auction.public-display', compact('auction', 'initialState'));
    }
}

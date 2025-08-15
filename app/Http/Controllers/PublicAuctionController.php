<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use Illuminate\Http\Request;

class PublicAuctionController extends Controller
{


    /**
     * Display the public live auction wall.
     */
    public function showPublicDisplay(Auction $auction)
    {
        return view('public.auction.live', [
            'auction' => $auction
        ]);
    }
    /**
     * Return JSON data for the currently active bidding player.
     */
    public function activePlayer(Auction $auction)
    {
        $player = $auction->auctionPlayers()
            ->with([
                'player',
                'player.playerType',
                'player.battingProfile',
                'player.bowlingProfile',
                'soldToTeam',
                'bids' => function ($q) {
                    $q->orderBy('created_at', 'desc')   // latest first
                        ->where('amount', '<=', 6000000); // only up to ₹6M
                },
                'bids.team'
            ])
            ->where('status', 'on_auction')
            ->first();

        return response()->json([
            'success' => true,
            'auctionPlayer' => $player
        ]);
    }
}

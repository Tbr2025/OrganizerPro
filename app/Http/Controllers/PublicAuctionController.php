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
        // Eager-load the auction's relationships for efficiency
        $auction->load(['organization', 'tournament']);

        // Get the current live player with all their nested details and bids
        $currentPlayer = $auction->auctionPlayers()
            ->where('status', 'live')
            ->with([
                'player.playerType',
                'player.battingProfile',
                'player.bowlingProfile',
                'bids' => fn($query) => $query->latest(), // Get bids, newest first
                'bids.team',
                'currentBidTeam'
            ])
            ->first();

        // Pass all necessary data to the view
        return view('public.auction.public-display', compact('auction', 'currentPlayer'));
    }
}

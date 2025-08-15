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
        // First, get the latest sold player (if any)
        $soldPlayer = $auction->auctionPlayers()
            ->with([
                'player',
                'player.playerType',
                'player.battingProfile',
                'player.bowlingProfile',
                'soldToTeam',
                'bids',
                'bids.team',
            ])
            ->where('status', 'sold')
            ->orderBy('updated_at', 'desc')
            ->first();

        // Then, get the next on_auction player
        $nextPlayer = $auction->auctionPlayers()
            ->with([
                'player',
                'player.playerType',
                'player.battingProfile',
                'player.bowlingProfile',
                'soldToTeam',
                'bids',
                'bids.team',
            ])
            ->where('status', 'on_auction')
            ->orderBy('id') // or your priority
            ->first();

        // Prefer sold player if exists, otherwise next on_auction
        $playerToReturn = $soldPlayer ?? $nextPlayer;

        return response()->json([
            'success' => true,
            'auctionPlayer' => $playerToReturn ? [
                'id' => $playerToReturn->id,
                'player' => $playerToReturn->player,
                'current_price' => $playerToReturn->final_price ?? $playerToReturn->current_price,
                'current_bid_team' => $playerToReturn->current_bid_team,
                'bids' => $playerToReturn->bids,
                'status' => $playerToReturn->status,
                'sold_to_team' => $playerToReturn->soldToTeam ? [
                    'name' => $playerToReturn->soldToTeam->name,
                    'logo_path' => $playerToReturn->soldToTeam->team_logo
                        ? asset('storage/' . $playerToReturn->soldToTeam->team_logo)
                        : null,
                ] : null,
            ] : null,
        ]);
    }
}

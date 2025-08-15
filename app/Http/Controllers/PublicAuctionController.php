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
        // Latest sold player
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

        // Next on_auction player
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
            ->orderBy('id')
            ->first();

        // Keep the same return structure, but include both players
        return response()->json([
            'success' => true,
            'auctionPlayer' => [
                'sold' => $soldPlayer ? [
                    'id' => $soldPlayer->id,
                    'player' => $soldPlayer->player,
                    'current_price' => $soldPlayer->final_price ?? $soldPlayer->current_price,
                    'current_bid_team' => $soldPlayer->current_bid_team,
                    'bids' => $soldPlayer->bids,
                    'status' => $soldPlayer->status,
                    'sold_to_team' => $soldPlayer->soldToTeam ? [
                        'name' => $soldPlayer->soldToTeam->name,
                        'logo_path' => $soldPlayer->soldToTeam->team_logo
                            ? asset('storage/' . $soldPlayer->soldToTeam->team_logo)
                            : null,
                    ] : null,
                ] : null,
                'next' => $nextPlayer ? [
                    'id' => $nextPlayer->id,
                    'player' => $nextPlayer->player,
                    'current_price' => $nextPlayer->final_price ?? $nextPlayer->current_price,
                    'current_bid_team' => $nextPlayer->current_bid_team,
                    'bids' => $nextPlayer->bids,
                    'status' => $nextPlayer->status,
                    'sold_to_team' => $nextPlayer->soldToTeam ? [
                        'name' => $nextPlayer->soldToTeam->name,
                        'logo_path' => $nextPlayer->soldToTeam->team_logo
                            ? asset('storage/' . $nextPlayer->soldToTeam->team_logo)
                            : null,
                    ] : null,
                ] : null,
            ]
        ]);
    }
}

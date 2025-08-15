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
                'soldToTeam', // This is needed for team logo
                'bids' => function ($q) {
                    $q->orderBy('created_at', 'desc') // latest first
                        ->where('amount', '<=', 6000000); // only up to ₹6M
                },
                'bids.team'
            ])
            ->whereIn('status', ['on_auction', 'sold']) // include sold players
            ->orderBy('status', 'desc') // optionally show 'on_auction' first
            ->first();

        return response()->json([
            'success' => true,
            'auctionPlayer' => $player ? [
                'id' => $player->id,
                'player' => $player->player,
                'current_price' => $player->current_price,
                'current_bid_team' => $player->current_bid_team,
                'bids' => $player->bids,
                'status' => $player->status,
                'sold_to_team' => $player->soldToTeam ? [
                    'name' => $player->soldToTeam->name,
                    'logo_path' => $player->soldToTeam->team_logo
                        ? asset('storage/' . $player->soldToTeam->team_logo)
                        : null,
                ] : null,
            ] : null,
        ]);
    }
}

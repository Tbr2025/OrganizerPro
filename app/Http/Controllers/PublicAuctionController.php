<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\ActualTeam;
use Illuminate\Http\Request;

class PublicAuctionController extends Controller
{
    /**
     * Display the public auction results page with all players.
     */
    public function showResults(Auction $auction)
    {
        $auction->load([
            'organization',
            'tournament',
            'auctionPlayers.player.playerType',
            'auctionPlayers.player.battingProfile',
            'auctionPlayers.player.bowlingProfile',
            'auctionPlayers.soldToTeam'
        ]);

        $teams = ActualTeam::where('tournament_id', $auction->tournament_id)
            ->orderBy('name')
            ->get();

        return view('public.auction.results', [
            'auction' => $auction,
            'teams' => $teams
        ]);
    }

    /**
     * Display the public live auction wall.
     */
    public function showPublicDisplay(Auction $auction)
    {
        return view('public.auction.live', [
            'auction' => $auction
        ]);
    }




     public function showPublicDisplaySold(Auction $auction)
    {
        return view('public.auction.sold', [
            'auction' => $auction
        ]);
    }
    /**
     * Return JSON data for the currently active bidding player.
     */
    public function activePlayer(Auction $auction)
    {
        $auctionPlayer = $auction->auctionPlayers()
            ->with([
                'player',
                'player.playerType',
                'player.battingProfile',
                'player.bowlingProfile',
                'soldToTeam',
                'currentBidTeam',
                'bids',
                'bids.team'
            ])
            ->where('status', 'on_auction')
            ->first();

        if (!$auctionPlayer) {
            return response()->json([
                'success' => true,
                'auctionPlayer' => null,
            ]);
        }

        // Build complete player object with all needed data
        $playerData = $auctionPlayer->player;
        $playerData->player_type = $auctionPlayer->player->playerType;
        $playerData->batting_profile = $auctionPlayer->player->battingProfile;
        $playerData->bowling_profile = $auctionPlayer->player->bowlingProfile;

        return response()->json([
            'success' => true,
            'auctionPlayer' => [
                'id' => $auctionPlayer->id,
                'player' => $playerData,
                'base_price' => $auctionPlayer->base_price,
                'current_price' => $auctionPlayer->current_price,
                'current_bid_team' => $auctionPlayer->currentBidTeam ? [
                    'id' => $auctionPlayer->currentBidTeam->id,
                    'name' => $auctionPlayer->currentBidTeam->name,
                ] : null,
                'bids' => $auctionPlayer->bids->map(function($bid) {
                    return [
                        'id' => $bid->id,
                        'amount' => $bid->amount,
                        'team' => $bid->team ? [
                            'id' => $bid->team->id,
                            'name' => $bid->team->name,
                        ] : null,
                    ];
                }),
                'status' => $auctionPlayer->status,
            ],
        ]);
    }



       public function soldPlayer(Auction $auction)
    {
        $player = $auction->auctionPlayers()
            ->with([
                'player',
                'player.playerType',
                'player.battingProfile',
                'player.bowlingProfile',
                'soldToTeam', // This is needed for team logo
                'bids',
                'bids.team'
            ])
            ->whereIn('status', ['sold']) // include sold players
            ->orderBy('updated_at', 'desc') // optionally show 'on_auction' first
            ->first();

        return response()->json([
            'success' => true,
            'auctionPlayer' => $player ? [
                'id' => $player->id,
                'player' => $player->player,
                'current_price' => $player->final_price,
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

    /**
     * Return JSON data for all sold players in the auction.
     */
    public function soldPlayers(Auction $auction)
    {
        $soldPlayers = $auction->auctionPlayers()
            ->with(['player', 'soldToTeam'])
            ->where('status', 'sold')
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(function ($ap) {
                return [
                    'id' => $ap->id,
                    'player' => $ap->player ? [
                        'id' => $ap->player->id,
                        'name' => $ap->player->name,
                    ] : null,
                    'final_price' => $ap->final_price,
                    'sold_to_team' => $ap->soldToTeam ? [
                        'id' => $ap->soldToTeam->id,
                        'name' => $ap->soldToTeam->name,
                    ] : null,
                ];
            });

        return response()->json([
            'success' => true,
            'soldPlayers' => $soldPlayers,
        ]);
    }
}

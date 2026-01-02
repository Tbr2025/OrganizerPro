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
            ->whereIn('status', ['on_auction']) // include sold players
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
}

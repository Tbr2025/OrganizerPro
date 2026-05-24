<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\ActualTeam;
use App\Models\AuctionTemplate;
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

        $teams = ActualTeam::forTournament($auction->tournament_id)
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
        $auction->load('tournament');

        // Fetch auction-specific or default template
        $template = AuctionTemplate::forAuction($auction->id, 'live_display');

        // Resolve element positions
        $positions = $template?->element_positions ?? AuctionTemplate::getDefaultPositions();

        // Resolve background: if template exists, use its bg (even if null = removed)
        // Only fall back to auction/default when there's no template at all
        if ($template) {
            $backgroundUrl = $template->background_url;
        } else {
            $backgroundUrl = $auction->background_image_url ?? asset('images/player-card.jpeg');
        }

        // Resolve sold badge: template sold_badge → null (use HTML fallback)
        $soldBadgeUrl = $template?->sold_badge_url ?? null;

        // Resolve unsold badge
        $unsoldBadgeUrl = $template?->unsold_badge_url ?? null;

        // Resolve canvas dimensions
        $canvasWidth = $template?->canvas_width ?? 1601;
        $canvasHeight = $template?->canvas_height ?? 910;

        return view('public.auction.live', [
            'auction' => $auction,
            'positions' => $positions,
            'backgroundUrl' => $backgroundUrl,
            'soldBadgeUrl' => $soldBadgeUrl,
            'unsoldBadgeUrl' => $unsoldBadgeUrl,
            'canvasWidth' => $canvasWidth,
            'canvasHeight' => $canvasHeight,
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

        // Fetch waiting player names for shuffle animation
        $waitingPlayers = $auction->auctionPlayers()
            ->where('status', 'waiting')
            ->with('player:id,name')
            ->get()
            ->pluck('player.name')
            ->filter()
            ->values();

        if (!$auctionPlayer) {
            // Return the most recently sold or unsold player so the live page
            // can show the correct state instead of stale data
            $lastActionPlayer = $auction->auctionPlayers()
                ->with([
                    'player', 'player.playerType', 'player.battingProfile',
                    'player.bowlingProfile', 'soldToTeam',
                ])
                ->whereIn('status', ['sold', 'unsold', 'skipped'])
                ->orderBy('updated_at', 'desc')
                ->first();

            $lastActionData = null;
            if ($lastActionPlayer) {
                $lpData = $lastActionPlayer->player;
                $lpData->player_type = $lastActionPlayer->player->playerType;
                $lpData->batting_profile = $lastActionPlayer->player->battingProfile;
                $lpData->bowling_profile = $lastActionPlayer->player->bowlingProfile;

                $lastActionData = [
                    'id' => $lastActionPlayer->id,
                    'player' => $lpData,
                    'base_price' => $lastActionPlayer->base_price,
                    'current_price' => $lastActionPlayer->current_price,
                    'final_price' => $lastActionPlayer->final_price,
                    'status' => $lastActionPlayer->status,
                    'sold_to_team' => $lastActionPlayer->soldToTeam ? [
                        'id' => $lastActionPlayer->soldToTeam->id,
                        'name' => $lastActionPlayer->soldToTeam->name,
                        'logo_path' => $lastActionPlayer->soldToTeam->team_logo ?? null,
                    ] : null,
                    'updated_at' => $lastActionPlayer->updated_at->timestamp,
                ];
            }

            return response()->json([
                'success' => true,
                'auctionPlayer' => null,
                'lastActionPlayer' => $lastActionData,
                'last_sold_player' => $lastActionData && $lastActionData['status'] === 'sold' ? $lastActionData : null,
                'auction_status' => $auction->status,
                'open_bid_mode' => $auction->open_bid_mode,
                'waitingPlayers' => $waitingPlayers,
            ]);
        }

        // Build complete player object with all needed data
        $playerData = $auctionPlayer->player;
        $playerData->player_type = $auctionPlayer->player->playerType;
        $playerData->batting_profile = $auctionPlayer->player->battingProfile;
        $playerData->bowling_profile = $auctionPlayer->player->bowlingProfile;

        // Build response data — always include current price for live display
        $responsePlayer = [
            'id' => $auctionPlayer->id,
            'player' => $playerData,
            'base_price' => $auctionPlayer->base_price,
            'current_price' => $auctionPlayer->current_price,
            'status' => $auctionPlayer->status,
            'current_bid_team' => $auctionPlayer->currentBidTeam ? [
                'id' => $auctionPlayer->currentBidTeam->id,
                'name' => $auctionPlayer->currentBidTeam->name,
            ] : null,
        ];

        return response()->json([
            'success' => true,
            'auctionPlayer' => $responsePlayer,
            'auction_status' => $auction->status,
            'open_bid_mode' => $auction->open_bid_mode,
            'bid_type' => $auction->bid_type,
            'bid_rules' => $auction->bid_rules,
            'bid_timer_seconds' => $auction->bid_timer_seconds ?? 30,
            'bid_timer_reset_seconds' => $auction->bid_timer_reset_seconds ?? 15,
            'player_updated_at' => $auctionPlayer->updated_at->timestamp,
            'server_time' => now()->timestamp,
            'waitingPlayers' => $waitingPlayers,
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

        // Public API: no bid amounts exposed
        return response()->json([
            'success' => true,
            'auctionPlayer' => $player ? [
                'id' => $player->id,
                'player' => $player->player,
                'base_price' => $player->base_price,
                'status' => $player->status,
                'sold_to_team' => $player->soldToTeam ? [
                    'name' => $player->soldToTeam->name,
                    'logo_path' => $player->soldToTeam->team_logo
                        ? asset('storage/' . $player->soldToTeam->team_logo)
                        : null,
                ] : null,
            ] : null,
            'auction_status' => $auction->status,
        ]);
    }

    /**
     * Return JSON data for all sold players in the auction.
     */
    public function soldPlayers(Auction $auction)
    {
        $soldPlayers = $auction->auctionPlayers()
            ->with(['player.playerType', 'soldToTeam'])
            ->where('status', 'sold')
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(function ($ap) {
                return [
                    'id' => $ap->id,
                    'player' => $ap->player ? [
                        'id' => $ap->player->id,
                        'name' => $ap->player->name,
                        'player_type' => $ap->player->playerType?->name ?? null,
                    ] : null,
                    'sold_to_team' => $ap->soldToTeam ? [
                        'id' => $ap->soldToTeam->id,
                        'name' => $ap->soldToTeam->name,
                        'logo_path' => $ap->soldToTeam->team_logo ?? null,
                    ] : null,
                    'final_price' => $ap->final_price,
                ];
            });

        return response()->json([
            'success' => true,
            'soldPlayers' => $soldPlayers,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\ActualTeam;
use App\Models\AuctionTemplate;
use Illuminate\View\View;

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
            'auctionPlayers.soldToTeam',
        ]);

        $teams = ActualTeam::forTournament($auction->tournament_id)
            ->orderBy('name')
            ->get();

        return view('public.auction.results', [
            'auction' => $auction,
            'teams' => $teams,
        ]);
    }

    /**
     * Display the public live auction wall.
     */
    public function showPublicDisplay(Auction $auction)
    {
        $auction->load('tournament');

        // The auction's explicit pick wins, then a template bound to it, then the default.
        $template = AuctionTemplate::resolveFor($auction, 'live_display');

        // An HTML-mode template owns the whole screen, so it renders as its own
        // document rather than inside the positioned-element page.
        if ($template?->isHtmlMode()) {
            // The policy goes on THIS response only. On the route it also hit the
            // positioned wall below, whose CDN scripts it blocks.
            $nonce = \App\Http\Middleware\AddTemplateCsp::nonce();

            return response()
                ->view('public.auction.html-template', [
                    'auction' => $auction,
                    'template' => $template,
                    'nonce' => $nonce,
                    'staticTokens' => \App\Services\Auction\TemplateTokenService::staticTokens($auction),
                ])
                ->header('Content-Security-Policy', \App\Http\Middleware\AddTemplateCsp::policy($nonce));
        }

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
            'auction' => $auction,
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

        /*
         * How far through the room is, for the waiting screen.
         *
         * One grouped count rather than a count() per status: this endpoint is polled every
         * two seconds by the wall, every phone watching and any OBS source, so four
         * aggregates here is four queries a tick per viewer.
         */
        $byStatus = $auction->auctionPlayers()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $progress = [
            'sold' => (int) $byStatus->get('sold', 0),
            'unsold' => (int) $byStatus->get('unsold', 0) + (int) $byStatus->get('skipped', 0),
            'waiting' => (int) $byStatus->get('waiting', 0),
            'total' => (int) $byStatus->sum(),
        ];
        // Whether the room has started working, which is what decides between "waiting for
        // the auction" and "waiting for the next player". `status` alone is not enough: an
        // auction is `running` from the moment it is started, before anyone is on the block.
        $progress['done'] = $progress['sold'] + $progress['unsold'];

        if (! $auctionPlayer) {
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
                        // Full URL, consistently across all three payloads below.
                        'logo_path' => $lastActionPlayer->soldToTeam->team_logo_url,
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
                // Server-computed, so every screen announces the restart for the same window.
                'restarting' => $auction->isRestarting(),
                'restart_seconds' => $auction->restartNoticeRemaining(),
                'open_bid_mode' => $auction->open_bid_mode,
                'waitingPlayers' => $waitingPlayers,
                'progress' => $progress,
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

        // A sealed round publishes only that it is running. `current_price` is frozen at
        // the round's floor and `current_bid_team_id` still holds the OPEN-bid leader —
        // both were public before the round opened — so nothing here reveals a sealed
        // amount or who placed it.
        $sealed = app(\App\Services\Auction\ClosedBidService::class)->stateForPublic($auction, $auctionPlayer);

        // Authoritative clock, so the big screen counts down in step with the
        // organizer panel rather than guessing from player_updated_at.
        $timerState = $auction->timerStateFor($auctionPlayer);

        return response()->json([
            'success' => true,
            'auctionPlayer' => $responsePlayer,
            'auction_status' => $auction->status,
            // Server-computed, so every screen announces the restart for the same window.
            'restarting' => $auction->isRestarting(),
            'restart_seconds' => $auction->restartNoticeRemaining(),
            'open_bid_mode' => $auction->open_bid_mode,
            'bid_type' => $auction->bid_type,
            // Counts only — never an amount, never a team-to-amount mapping.
            'closed_bid' => $sealed,
            'bid_timer_seconds' => $timerState['limit'],
            'bid_timer_reset_seconds' => $auction->bid_timer_reset_seconds ?? 15,
            'timer_enabled' => $timerState['applies'],
            'timer_seconds_remaining' => $timerState['remaining'],
            'timer_expired' => $timerState['expired'],
            // Closing calls, so the audience display escalates with the room.
            'final_call' => $timerState['final_call'],
            'final_call_stages' => $timerState['final_call_stages'],
            'amount_unit' => $auction->amountUnitConfig(),
            'player_updated_at' => $auctionPlayer->updated_at->timestamp,
            'server_time' => now()->timestamp,
            'waitingPlayers' => $waitingPlayers,
            'progress' => $progress,
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
                    'logo_path' => $player->soldToTeam->team_logo_url,
                ] : null,
            ] : null,
            'auction_status' => $auction->status,
            // Server-computed, so every screen announces the restart for the same window.
            'restarting' => $auction->isRestarting(),
            'restart_seconds' => $auction->restartNoticeRemaining(),
        ]);
    }

    /**
     * Transparent 1920x1080 overlay for a streaming mixer (OBS browser source).
     *
     * Mirrors the existing match ticker at public/match/live-ticker.blade.php — same
     * transparency, sizing and shortcuts — so the two behave identically in a stream.
     */
    public function liveTicker(Auction $auction): View|\Illuminate\Http\Response
    {
        $auction->load('tournament.organization');

        /*
         * An authored ticker template owns the whole strip.
         *
         * Same treatment the LED wall already gets: the markup renders as its own document
         * with a nonce CSP, so nothing in it can execute and no platform chrome is around
         * for it to collide with. Ticker templates are HTML-only by definition — the
         * positioned editor describes a 1601x910 card, not a lower third — so there is no
         * positioned branch to fall back through here.
         */
        $template = AuctionTemplate::resolveFor($auction, AuctionTemplate::TYPE_TICKER);

        if ($template?->isHtmlMode()) {
            $nonce = \App\Http\Middleware\AddTemplateCsp::nonce();

            return response()
                ->view('public.auction.html-template', [
                    'auction' => $auction,
                    'template' => $template,
                    'nonce' => $nonce,
                    'staticTokens' => \App\Services\Auction\TemplateTokenService::staticTokens($auction),
                ])
                ->header('Content-Security-Policy', \App\Http\Middleware\AddTemplateCsp::policy($nonce));
        }

        // Nothing chosen: the built-in strip, which is what every existing auction expects.
        return view('public.auction.ticker', ['auction' => $auction]);
    }

    /**
     * Everything the ticker needs in one call: the player on the block, recent sales,
     * team purses and pool progress.
     *
     * Deliberately excludes the increment ladder and sealed-bid thresholds — this is a
     * public endpoint and those would reveal the ceiling to anyone watching.
     */
    public function tickerFeed(Auction $auction)
    {
        $pools = app(\App\Services\Auction\AuctionPoolService::class);

        $current = $auction->auctionPlayers()
            ->where('status', 'on_auction')
            ->with(['player.playerType', 'currentBidTeam'])
            ->first();

        $timerState = $auction->timerStateFor($current);

        $recentSales = $auction->auctionPlayers()
            ->where('status', 'sold')
            ->with(['player:id,name', 'soldToTeam:id,name,team_logo'])
            ->orderByDesc('updated_at')
            ->limit(12)
            ->get()
            ->map(fn ($ap) => [
                'id' => $ap->id,
                'player_name' => $ap->player->name ?? 'Player',
                'team_name' => $ap->soldToTeam->name ?? '',
                'team_logo' => $ap->soldToTeam?->team_logo_url,
                'price' => $ap->final_price,
            ]);

        // Purses are not exposed by any other public endpoint, so the ticker needs its
        // own read — figures only, no per-team internals.
        // Only the teams actually taking part — the strip used to list every team in the
        // tournament, including ones with no allocation who were never in this auction.
        $teams = $pools->participatingTeams($auction)
            ->map(function (ActualTeam $team) use ($auction, $pools) {
                $state = $pools->teamPurseState($auction, $team->id);

                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'short_name' => $team->short_name ?: mb_substr($team->name, 0, 3),
                    'logo' => $team->team_logo_url,
                    'remaining' => $state['remaining'] >= 1.0e15 ? null : $state['remaining'],
                    'players' => $state['slots_filled'],
                    'squad_required' => $state['slots_required'],
                ];
            });

        $progress = $pools->poolProgress($auction);

        return response()->json([
            'success' => true,
            'auction_status' => $auction->status,
            // Server-computed, so every screen announces the restart for the same window.
            'restarting' => $auction->isRestarting(),
            'restart_seconds' => $auction->restartNoticeRemaining(),
            'amount_unit' => $auction->amountUnitConfig(),
            'current_player' => $current ? [
                'id' => $current->id,
                'name' => $current->player->name ?? 'Player',
                'role' => $current->player->playerType?->name,
                'image' => $current->player?->image_path ? asset('storage/' . $current->player->image_path) : null,
                'base_price' => $current->base_price,
                'current_price' => $current->current_price,
                'leading_team' => $current->currentBidTeam?->name,
                // The right-hand cell of the lower third is narrow; full team names overflow.
                'leading_team_short' => $current->currentBidTeam?->display_name,
                'lot_number' => $current->lot_number,
                'stats' => $this->careerStats($current->player),
            ] : null,
            'timer' => [
                'enabled' => $timerState['applies'],
                'remaining' => $timerState['remaining'],
                'limit' => $timerState['limit'],
                'final_call' => $timerState['final_call'],
                'final_call_stages' => $timerState['final_call_stages'],
            ],
            'recent_sales' => $recentSales,
            // That a sealed round is running, and nothing else. Counts only — never an
            // amount, never a team-to-amount mapping.
            'closed_bid' => app(\App\Services\Auction\ClosedBidService::class)
                ->stateForPublic($auction, $current),
            'teams' => $teams,
            // Squad bounds for the teams table footer. `max` is null when unconfigured
            // so the display can omit it rather than invent a ceiling.
            'squad' => [
                'min' => $auction->minSquadSize(),
                'max' => $auction->maxSquadSize(),
            ],
            'active_pool' => $progress['active_pool'],
            'stats' => [
                'sold' => $auction->auctionPlayers()->where('status', 'sold')->count(),
                'unsold' => $auction->auctionPlayers()->whereIn('status', ['unsold', 'skipped'])->count(),
                'total' => $auction->auctionPlayers()->count(),
            ],
            'server_time' => now()->timestamp,
        ]);
    }

    /**
     * The self-declared career figures shown on the broadcast strip.
     *
     * These are the numbers a player typed at registration (`players.total_*`) — the
     * only career data that actually exists. Real per-match aggregates live in
     * `player_statistics`, which is effectively empty, and nothing anywhere stores a
     * strike rate or a 50s/100s count, so the strip deliberately shows matches, runs
     * and wickets only.
     *
     * Null is preserved rather than coalesced to 0: 0 is a figure somebody entered and
     * should render, while null means "never filled in" and the cell is dropped. When
     * all three are null the whole strip goes, so the screen never shows an empty frame.
     *
     * @return array{matches: int|null, runs: int|null, wickets: int|null}|null
     */
    private function careerStats(?\App\Models\Player $player): ?array
    {
        if (! $player) {
            return null;
        }

        $stats = [
            'matches' => $player->total_matches !== null ? (int) $player->total_matches : null,
            'runs' => $player->total_runs !== null ? (int) $player->total_runs : null,
            'wickets' => $player->total_wickets !== null ? (int) $player->total_wickets : null,
        ];

        return array_filter($stats, fn ($v) => $v !== null) === [] ? null : $stats;
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
                        'logo_path' => $ap->soldToTeam->team_logo_url,
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

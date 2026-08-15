<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use App\Models\ActualTeam;
use App\Models\AuctionTemplate;
use Illuminate\View\View;

class PublicAuctionController extends Controller
{
    /**
     * How long a broadcast feed may be reused, in seconds.
     *
     * These three endpoints are public, read-only and — this is the point — IDENTICAL for
     * every viewer. The hall projector, the OBS ticker, the organizer's second screen and
     * every phone in the room were each triggering their own full rebuild every two seconds.
     * On live that was ~1,500 of ~2,400 requests in a two-minute sample, against a pool of
     * five PHP workers on two cores, and every page on the site — including /login — had
     * degraded to a ten-to-twenty second response.
     *
     * One second, deliberately: the clients already poll on a two-second cycle and tick their
     * own countdowns between polls, so this adds at most a second of staleness while
     * collapsing all concurrent viewers onto a single build.
     */
    private const FEED_TTL = 1;

    /**
     * Serve a public feed from a short shared cache.
     *
     * Keyed per auction, and the PAYLOAD is cached rather than the response object so the
     * JSON headers are still built fresh. Nothing here is user-specific — the authenticated
     * purse poll is a separate endpoint precisely because this one carries no team data — so
     * one cached copy is correct for every viewer.
     */
    private function cachedFeed(string $name, Auction $auction, \Closure $build): JsonResponse
    {
        $payload = Cache::remember(
            "auction-feed:{$name}:{$auction->id}",
            self::FEED_TTL,
            function () use ($build) {
                $result = $build();

                return $result instanceof JsonResponse ? $result->getData(true) : $result;
            }
        );

        return response()->json($payload);
    }

    public function activePlayer(Auction $auction): JsonResponse
    {
        return $this->cachedFeed('active-player', $auction, fn () => $this->buildActivePlayer($auction));
    }

    public function tickerFeed(Auction $auction): JsonResponse
    {
        return $this->cachedFeed('ticker', $auction, fn () => $this->buildTickerFeed($auction));
    }

    public function soldPlayers(Auction $auction): JsonResponse
    {
        return $this->cachedFeed('sold-players', $auction, fn () => $this->buildSoldPlayers($auction));
    }

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

        /*
         * ?template= lets each physical screen pick its own layout.
         *
         * A projector, a portrait LED wall and a second monitor are different resolutions,
         * and a template is drawn against one fixed canvas — so one stored choice cannot
         * serve them all. Each display opens the same auction with its own template id.
         *
         * Validated against the auction, never trusted: see AuctionTemplate::overrideFor().
         */
        $template = AuctionTemplate::overrideFor($auction, 'live_display', request('template'))
            // Otherwise the auction's explicit pick, then one bound to it, then the default.
            ?? AuctionTemplate::resolveFor($auction, 'live_display');

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
        ] + $this->cardModePayload($auction));
    }

    /**
     * Card mode: `?card={auctionPlayerId}` turns the wall into ONE player's still card.
     *
     * The download of a player card is a screenshot of the wall page itself, so the file and
     * the screen cannot drift apart — one set of positions, one background, one set of CSS.
     * Re-drawing the card in GD from element_positions was the alternative, and it means
     * keeping a second renderer that has to agree with this one forever.
     *
     * `?result=1` keeps the outcome on the card (the SOLD stamp and price); without it the
     * card renders as the player looked before the hammer fell.
     *
     * @return array<string, mixed>  Empty for the wall, which must be untouched by this.
     */
    private function cardModePayload(Auction $auction): array
    {
        $cardId = request('card');

        if (! $cardId) {
            return [];
        }

        $ap = $auction->auctionPlayers()
            ->with(['player.playerType', 'player.battingProfile', 'player.bowlingProfile', 'soldToTeam'])
            ->find($cardId);

        if (! $ap || ! $ap->player) {
            return [];
        }

        // The same shape the live feed hands updatePlayerCard(), so the page renders it by
        // exactly the path it uses for a player on the block.
        $player = $ap->player;
        $player->player_type = $ap->player->playerType;
        $player->batting_profile = $ap->player->battingProfile;
        $player->bowling_profile = $ap->player->bowlingProfile;

        return [
            'cardPayload' => [
                'id' => $ap->id,
                'player' => $player,
                'base_price' => $ap->base_price,
                'current_price' => $ap->final_price ?? $ap->current_price,
                'final_price' => $ap->final_price,
                'status' => $ap->status,
                'sold_to_team' => $ap->soldToTeam ? [
                    'id' => $ap->soldToTeam->id,
                    'name' => $ap->soldToTeam->name,
                    'logo_path' => $ap->soldToTeam->team_logo_url,
                ] : null,
                'current_bid_team' => null,
            ],
            'cardShowResult' => request()->boolean('result'),
            // The in-browser download names the file from this, so a folder of them is
            // readable — same scheme the server-side render uses.
            'cardFilename' => app(\App\Services\Auction\AuctionCardRenderer::class)
                ->filename($ap, request()->boolean('result')),
        ];
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
    private function buildActivePlayer(Auction $auction)
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

        /*
         * A player whose draw is still spinning is still THE player, even though the server has
         * already sold them.
         *
         * `drawLot()` awards in the same request, so the moment the button is pressed nobody is
         * `on_auction` any more — this query found nothing, the feed fell through to its
         * "no player" branch, and the wall dropped the card and the draw together at the exact
         * instant the ring should have started turning.
         */
        if (! $auctionPlayer) {
            $spinning = $auction->auctionPlayers()
                ->whereNotNull('closed_bid_round_id')
                ->whereHas('closedBidRound', fn ($q) => $q->whereNotNull('lot_drawn_at'))
                ->with([
                    'player',
                    'player.playerType',
                    'player.battingProfile',
                    'player.bowlingProfile',
                    'soldToTeam',
                    'currentBidTeam',
                ])
                ->latest('updated_at')
                ->first();

            if ($spinning && app(\App\Services\Auction\ClosedBidService::class)->lotSpinInProgress($spinning)) {
                $auctionPlayer = $spinning;
            }
        }

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
            /*
             * Biddable players only. Icon players are kept by their team before the auction and
             * never go on the block, so counting them makes the wall's "x of y done" measure
             * progress against people nobody will ever bid for.
             */
            ->where('is_retained', false)
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

        /*
         * Which pool is running, named.
         *
         * The wall said how far through the auction the room was and never which pool that
         * counted — so "3 of 17" meant nothing to anybody who had not seen the pools screen. A
         * pool is how a hall follows an evening: marquee players, then the rest.
         */
        $activePool = $auction->pools()
            ->where('status', \App\Models\AuctionPool::STATUS_ACTIVE)
            ->first(['id', 'name']);

        $progress['pool_name'] = $activePool?->name;

        /*
         * What the room is waiting for, in the server's words.
         *
         * Every screen used to word this itself, and none of them could say "the pool has ended"
         * or "the auction has ended" — both of which look identical to "between players" from a
         * feed that only reports the player on the block. Decided once, in
         * AuctionStageService, so the wall, the strip and every manager's phone read the same
         * caption at the same moment.
         */
        $stage = app(\App\Services\Auction\AuctionStageService::class)
            ->for($auction, null, $byStatus->all());

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
                /*
                                 * Whether the sold board is up. On the feed rather than pushed alone, so a wall
                                 * plugged in or reloaded while the board is showing comes up on the board too —
                                 * a broadcast only reaches screens already listening.
                                 */
                'public_board' => $auction->boardShowsOn('wall') ? $auction->public_board : null,
                'break_remaining' => $auction->breakRemaining(),
                // Server-computed, so every screen announces the restart for the same window.
                'restarting' => $auction->isRestarting(),
                'restart_seconds' => $auction->restartNoticeRemaining(),
                'open_bid_mode' => $auction->open_bid_mode,
                'waitingPlayers' => $waitingPlayers,
                'progress' => $progress,
                'stage' => $stage,
                /*
                 * Both clocks from the server, so the wall can work out how long the last result
                 * has been up without touching `Date.now()`. The app runs on Asia/Dubai and the
                 * database on UTC, and a browser subtracting one from the other is four hours out.
                 */
                'server_time' => now()->timestamp,
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
        $closedBids = app(\App\Services\Auction\ClosedBidService::class);
        $sealed = $closedBids->stateForPublic($auction, $auctionPlayer);

        /*
         * While a lot is being drawn, the room is told nothing about the result.
         *
         * `drawLot()` records the draw and awards the player in the SAME request, so from the
         * first millisecond this player is `sold` with a buying team and a final price. The wall
         * read that and painted the stamp, the crest and the result banner while the ring was
         * still supposed to be deciding — the hall had the answer before the draw it was watching
         * had finished.
         *
         * Held here rather than on the screens, because a hold in the browser is not a hold: the
         * name has already been sent, a reload mid-spin shows it, and the network tab always has
         * it. For the length of the spin this player is simply reported as still on the block.
         */
        if ($closedBids->lotSpinInProgress($auctionPlayer)) {
            $responsePlayer['status'] = 'on_auction';
            $responsePlayer['current_bid_team'] = null;
            $responsePlayer['sold_to_team'] = null;
            $responsePlayer['final_price'] = null;
        }

        // Authoritative clock, so the big screen counts down in step with the
        // organizer panel rather than guessing from player_updated_at.
        $timerState = $auction->timerStateFor($auctionPlayer);

        return response()->json([
            'success' => true,
            'auctionPlayer' => $responsePlayer,
            'auction_status' => $auction->status,
            'public_board' => $auction->boardShowsOn('wall') ? $auction->public_board : null,
                'break_remaining' => $auction->breakRemaining(),
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
            'timer_paused' => $timerState['paused'],
            'timer_expired' => $timerState['expired'],
            // Closing calls, so the audience display escalates with the room.
            'final_call' => $timerState['final_call'],
            'final_call_stages' => $timerState['final_call_stages'],
            'amount_unit' => $auction->amountUnitConfig(),
            'player_updated_at' => $auctionPlayer->updated_at->timestamp,
            'server_time' => now()->timestamp,
            'waitingPlayers' => $waitingPlayers,
            'progress' => $progress,
            'stage' => $stage,
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
            'public_board' => $auction->boardShowsOn('wall') ? $auction->public_board : null,
                'break_remaining' => $auction->breakRemaining(),
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
        // Same per-screen override as the wall: an OBS strip and a hall ticker can differ.
        $template = AuctionTemplate::overrideFor($auction, AuctionTemplate::TYPE_TICKER, request('template'))
            ?? AuctionTemplate::resolveFor($auction, AuctionTemplate::TYPE_TICKER);

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
    private function buildTickerFeed(Auction $auction)
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
            'public_board' => $auction->boardShowsOn('ticker') ? $auction->public_board : null,
                'break_remaining' => $auction->breakRemaining(),
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
                /*
                 * Two facts the hall needs beside the name, and neither was on this feed.
                 *
                 * A keeper changes who wants the player, and the travel plan decides whether
                 * they can turn up at all — both were on the organizer's screen and on nothing
                 * anybody watching could see. `travel_plan_label` is the model's own accessor,
                 * the same one the wall, the panel and the downloaded card read, so the four
                 * cannot answer it differently.
                 */
                'is_wicket_keeper' => (bool) $current->player?->is_wicket_keeper,
                'travel_plan_label' => $current->player?->travel_plan_label,
                'stats' => $this->careerStats($current->player),
            ] : null,
            'timer' => [
                'enabled' => $timerState['applies'],
                'remaining' => $timerState['remaining'],
                'limit' => $timerState['limit'],
                'paused' => $timerState['paused'],
                'final_call' => $timerState['final_call'],
                'final_call_stages' => $timerState['final_call_stages'],
            ],
            'recent_sales' => $recentSales,

            /*
             * The last player to be decided, sold or unsold.
             *
             * `recent_sales` carries sales only, and `current_player` is null the moment a lot
             * ends — so between lots the strip had nothing to say about what had just happened.
             * A hall watching a stream sees the player disappear and no result.
             *
             * One row, with the badge the ticker needs: who, what happened, and to whom.
             */
            'last_action' => (function () use ($auction) {
                $last = $auction->auctionPlayers()
                    ->whereIn('status', ['sold', 'unsold', 'skipped'])
                    ->with(['player:id,name', 'soldToTeam:id,name,team_logo'])
                    ->orderByDesc('updated_at')
                    ->first();

                if (! $last) {
                    return null;
                }

                return [
                    'id' => $last->id,
                    'player_name' => $last->player->name ?? 'Player',
                    'status' => $last->status,
                    'team_name' => $last->soldToTeam->name ?? null,
                    'team_logo' => $last->soldToTeam?->team_logo_url,
                    'price' => $last->status === 'sold' ? $last->final_price : null,
                ];
            })(),
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
            /*
             * What the strip says when there is nobody on the block.
             *
             * It used to say "Next player coming up…" in every one of those cases — before the
             * auction had started, between pools, and after the auction had been closed. The
             * server decides the caption now; the strip just prints it.
             */
            'stage' => app(\App\Services\Auction\AuctionStageService::class)->for($auction, $progress),
            /*
             * Counted over the players who can actually be BID ON.
             *
             * `total` was every auction_players row, which includes the icon players kept by
             * their teams before the auction — they never go on the block, so the strip read
             * "25/51 sold" for a room with 31 players to get through. A denominator that
             * counts people nobody will ever bid for is not a measure of progress.
             */
            'stats' => [
                'sold' => $auction->auctionPlayers()->where('status', 'sold')->count(),
                'unsold' => $auction->auctionPlayers()->whereIn('status', ['unsold', 'skipped'])->count(),
                'total' => $auction->auctionPlayers()->where('is_retained', false)->count(),
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
    private function buildSoldPlayers(Auction $auction)
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
                        // The board is a wall of faces; without this it is a spreadsheet.
                        'image' => $ap->player->image_path ? asset('storage/' . $ap->player->image_path) : null,
                    ] : null,
                    'sold_to_team' => $ap->soldToTeam ? [
                        'id' => $ap->soldToTeam->id,
                        'name' => $ap->soldToTeam->name,
                        'logo_path' => $ap->soldToTeam->team_logo_url,
                    ] : null,
                    'final_price' => $ap->final_price,
                ];
            });

        /*
         * The sponsor artwork rides along with the board's own fetch.
         *
         * A board already pulls this endpoint when it goes up, and the reel needs the ads at the
         * same moment — a second request would be a second chance to be slow on a venue uplink,
         * for two lists that are always wanted together.
         */
        $ads = $auction->ads()->where('is_active', true)->get()
            ->groupBy('kind')
            ->map(fn ($rows) => $rows->map(fn ($ad) => [
                'url' => $ad->url,
                'caption' => $ad->caption,
            ])->values());

        return response()->json([
            'success' => true,
            'soldPlayers' => $soldPlayers,
            // Switched off at the auction, the artwork simply is not sent — the screens have
            // nothing to decide and nothing to hide, and the uploads are untouched.
            'adSlides' => $auction->ads_slides_enabled
                ? $ads->get(\App\Models\AuctionAd::KIND_SLIDE, collect())
                : collect(),
            'sponsors' => $auction->ads_sponsors_enabled
                ? $ads->get(\App\Models\AuctionAd::KIND_SPONSOR, collect())
                : collect(),
            // Drawn top-centre on the reel, so a break still carries the event's identity.
            'tournamentLogo' => $auction->tournament?->logo
                ? asset('storage/' . $auction->tournament->logo)
                : null,
        ]);
    }
}

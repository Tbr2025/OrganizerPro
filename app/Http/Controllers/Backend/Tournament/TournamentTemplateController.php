<?php

namespace App\Http\Controllers\Backend\Tournament;

use App\Http\Controllers\Controller;
use App\Models\Matches;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\ActualTeam;
use App\Models\GeneratedPoster;
use App\Models\TournamentRegistration;
use App\Models\TournamentTemplate;
use App\Services\ImageBackgroundRemovalService;
use App\Services\Poster\FixturesPosterService;
use App\Services\Poster\TemplateRenderService;
use App\Services\Poster\TemplatePresetService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TournamentTemplateController extends Controller
{
    public function __construct()
    {
        // Template management (CRUD) is Superadmin only
        // AJAX requests to index/edit are allowed for Admin (used by generate poster page)
        $this->middleware(function ($request, $next) {
            if (! auth()->user()->hasRole('Superadmin')) {
                // Allow AJAX template list queries (used by generate poster page to load templates)
                if (($request->ajax() || $request->has('ajax')) && $request->isMethod('GET')) {
                    return $next($request);
                }
                // Allow AJAX layout queries (used by field visibility toggles)
                if ($request->has('ajax_layout') && $request->isMethod('GET')) {
                    return $next($request);
                }
                abort(403, 'Only Super Admin can manage templates.');
            }
            return $next($request);
        })->only([
            'index', 'create', 'store', 'edit', 'update', 'destroy',
            'duplicate', 'setDefault', 'uploadOverlay', 'deleteOverlay', 'updateSize',
        ]);
    }

    /**
     * Display list of templates for a tournament
     */
    public function index(Tournament $tournament, Request $request)
    {
        // Handle AJAX request for templates by type
        if ($request->ajax() || $request->has('ajax')) {
            $type = $request->get('type');
            $templates = $tournament->templates()
                ->when($type, fn ($q) => $q->where('type', $type))
                ->orderByDesc('is_default')
                ->get()
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'type' => $t->type,
                    'is_default' => $t->is_default,
                    'background_image_url' => $t->background_image_url,
                    // So the generate page can show colour-correction sliders for
                    // exactly the person images this design draws, without a
                    // second round trip when the organizer switches template.
                    'person_image_placeholders' => $t->personImagePlaceholders(),
                ]);

            return response()->json(['templates' => $templates]);
        }

        $templates = $tournament->templates()
            ->orderBy('type')
            ->orderByDesc('is_default')
            ->get()
            ->groupBy('type');

        $templateTypes = TournamentTemplate::TYPES;

        /*
         * The auction screens live in their own table and their own designer.
         *
         * `AuctionTemplate` is scoped to an auction (or to an organization, or global) rather
         * than to a tournament, so it cannot simply become another TournamentTemplate::TYPES
         * entry — the two have different owners, different canvases and different editors.
         * What was missing is that nothing on the tournament's own Templates page said the
         * auction screens existed, so an organizer looking here for the bidding poster found
         * eight poster types and no auction among them.
         *
         * So: surface them from here, per type, linking into the designer that already owns
         * them. Only for auction tournaments — an open tournament has no wall to design.
         */
        $auctionTemplates = null;
        $auction = null;

        if ($tournament->isAuction()) {
            $auction = \App\Models\Auction::where('tournament_id', $tournament->id)
                ->orderByDesc('id')
                ->first();

            $auctionTemplates = \App\Models\AuctionTemplate::query()
                ->visibleTo(auth()->user())
                // This tournament's own, plus the shared ones it falls back to — which is
                // exactly the set the LED wall resolves from, so the page cannot claim a
                // screen is undesigned when the wall would find a template for it.
                ->where(function ($q) use ($auction) {
                    $q->whereNull('auction_id');

                    if ($auction) {
                        $q->orWhere('auction_id', $auction->id);
                    }
                })
                ->orderByDesc('is_default')
                ->get()
                ->groupBy('type');
        }

        return view('backend.pages.tournaments.templates.index', compact(
            'tournament',
            'templates',
            'templateTypes',
            'auction',
            'auctionTemplates'
        ));
    }

    /**
     * Show generate poster page with data selection
     */
    public function generate(Tournament $tournament, Request $request)
    {
        $type = $request->get('type', TournamentTemplate::TYPE_MATCH_POSTER);

        // Load templates for selected type
        $templates = $tournament->templates()
            ->where('type', $type)
            ->orderByDesc('is_default')
            ->get();

        // Load matches with team captain information and awards
        $matches = $tournament->matches()
            ->with([
                'teamA.users' => function ($query) {
                    $query->wherePivot('role', 'captain');
                },
                'teamB.users' => function ($query) {
                    $query->wherePivot('role', 'captain');
                },
                'ground',
                'winner',
                'result',
                'matchAwards.tournamentAward',
                'matchAwards.player',
            ])
            ->orderBy('match_date')
            ->get();

        // Load players belonging to tournament's actual teams (direct + pivot + groups)
        $directTeamIds = $tournament->actualTeams()->pluck('id');
        $pivotTeamIds = DB::table('actual_team_tournament')
            ->where('tournament_id', $tournament->id)
            ->pluck('actual_team_id');
        $groupTeamIds = DB::table('tournament_group_teams')
            ->whereIn('tournament_group_id', $tournament->groups()->pluck('id'))
            ->pluck('actual_team_id');
        $allTeamIds = $directTeamIds->merge($pivotTeamIds)->merge($groupTeamIds)->unique();

        $teamPlayers = Player::whereIn('actual_team_id', $allTeamIds)
            ->with(['actualTeam', 'playerType', 'battingProfile', 'bowlingProfile'])
            ->where('status', 'approved')
            ->get();

        // Also include players assigned to tournament teams via pivot (may have different home team)
        $pivotPlayerIds = DB::table('player_actual_team_tournament')
            ->where('tournament_id', $tournament->id)
            ->whereIn('actual_team_id', $allTeamIds)
            ->pluck('player_id');

        $pivotPlayers = Player::whereIn('id', $pivotPlayerIds)
            ->whereNotIn('id', $teamPlayers->pluck('id'))
            ->with(['actualTeam', 'playerType', 'battingProfile', 'bowlingProfile'])
            ->where('status', 'approved')
            ->get();

        // Build a map of tournament-specific team assignments (pivot overrides home team)
        $tournamentTeamMap = DB::table('player_actual_team_tournament')
            ->where('tournament_id', $tournament->id)
            ->whereIn('actual_team_id', $allTeamIds)
            ->pluck('actual_team_id', 'player_id');

        // Also include players who registered via registration link for this tournament
        $registeredPlayerIds = TournamentRegistration::where('tournament_id', $tournament->id)
            ->where('type', 'player')
            ->where('status', 'approved')
            ->whereNotNull('player_id')
            ->pluck('player_id');

        $allLoadedIds = $teamPlayers->pluck('id')->merge($pivotPlayers->pluck('id'));
        $registeredPlayers = Player::whereIn('id', $registeredPlayerIds)
            ->whereNotIn('id', $allLoadedIds)
            ->with(['actualTeam', 'playerType', 'battingProfile', 'bowlingProfile'])
            ->where('status', 'approved')
            ->get();

        // Also include players linked via actual_team_users (team membership pivot)
        $allLoadedIds = $allLoadedIds->merge($registeredPlayers->pluck('id'));
        $memberUserIds = DB::table('actual_team_users')
            ->whereIn('actual_team_id', $allTeamIds)
            ->where('role', 'Player')
            ->pluck('user_id');
        $memberPlayers = Player::whereIn('user_id', $memberUserIds)
            ->whereNotIn('id', $allLoadedIds)
            ->with(['actualTeam', 'playerType', 'battingProfile', 'bowlingProfile'])
            ->where('status', 'approved')
            ->get();

        // Map member players to their team via actual_team_users
        if ($memberPlayers->isNotEmpty()) {
            $memberTeamMap = DB::table('actual_team_users')
                ->whereIn('actual_team_id', $allTeamIds)
                ->where('role', 'Player')
                ->whereIn('user_id', $memberPlayers->pluck('user_id'))
                ->get()
                ->keyBy('user_id');
            foreach ($memberPlayers as $mp) {
                $entry = $memberTeamMap->get($mp->user_id);
                if ($entry && ! isset($tournamentTeamMap[$mp->id])) {
                    $tournamentTeamMap[$mp->id] = $entry->actual_team_id;
                }
            }
        }

        $players = $teamPlayers->merge($pivotPlayers)->merge($registeredPlayers)->merge($memberPlayers);

        // Exclude players whose tournament registration is rejected or pending
        $excludedPlayerIds = TournamentRegistration::where('tournament_id', $tournament->id)
            ->where('type', 'player')
            ->whereNotNull('player_id')
            ->whereIn('status', ['rejected', 'pending'])
            ->pluck('player_id');

        if ($excludedPlayerIds->isNotEmpty()) {
            $players = $players->whereNotIn('id', $excludedPlayerIds);
        }

        // Load actual teams for the team filter dropdown (welcome_card)
        $teams = ActualTeam::whereIn('id', $allTeamIds)->orderBy('name')->get();

        /*
         * The roster the Playing XI picker offers, grouped by team.
         *
         * Keyed by the player's TOURNAMENT team (the pivot beats their home team) so a player
         * turning out for a different side in this tournament appears under the side they
         * actually play for, matching how the rest of this page resolves teams.
         *
         * Captain and keeper are pre-filled because they are recorded: the captain from the
         * actual_team_users pivot, the keeper from the player's own type. Vice-captain and
         * debut are not recorded anywhere in this schema, so those stay blank for the
         * organizer to set on the poster.
         */
        $captainUserIds = DB::table('actual_team_users')
            ->whereIn('actual_team_id', $allTeamIds)
            ->whereIn('role', ['captain', 'Captain', 'Owner'])
            ->pluck('user_id')
            ->all();

        $xiRoster = [];
        foreach ($players as $p) {
            $teamId = $tournamentTeamMap[$p->id] ?? $p->actual_team_id;
            if (! $teamId) {
                continue;
            }

            $playerType = strtolower((string) ($p->playerType?->type ?? ''));
            $badge = '';
            if ($p->user_id && in_array($p->user_id, $captainUserIds)) {
                $badge = 'C';
            } elseif (str_contains($playerType, 'keeper')) {
                $badge = 'WK';
            }

            $xiRoster[(string) $teamId][] = [
                'id' => $p->id,
                'name' => $p->name,
                // Raw storage path, not an asset() URL: the renderer resolves it through
                // extractStoragePath() and a URL only survives that by accident.
                'image' => $p->image_path,
                'type' => $p->playerType?->type ?? '',
                'badge' => $badge,
            ];
        }

        // Load groups for point table type
        $groups = $tournament->groups;

        // Load saved/generated posters
        $savedPosters = $tournament->generatedPosters()
            ->latest()
            ->limit(50)
            ->get();

        $autoWelcome = $tournament->settings?->auto_send_welcome_cards ?? true;

        /*
         * Every ready-made design, not just this tab's.
         *
         * The type tabs switch with history.replaceState() rather than a page load, so a list
         * filtered here would go stale the moment the organizer changed tab. The view renders
         * them all tagged with their type and shows the matching ones.
         */
        $presets = (new TemplatePresetService())->all();

        /*
         * Which person images each template draws, keyed by template id.
         *
         * The generate page shows one brightness/contrast panel per person image
         * in the SELECTED template — so it needs this for every template in the
         * list up front, the same way loadTemplates() gets it from the ajax
         * listing after a type switch.
         */
        $templatePersonImages = $templates->mapWithKeys(
            fn ($t) => [$t->id => $t->personImagePlaceholders()]
        );

        return view('backend.pages.tournaments.templates.generate', compact(
            'tournament',
            'type',
            'presets',
            'templates',
            'templatePersonImages',
            'xiRoster',
            'matches',
            'players',
            'teams',
            'tournamentTeamMap',
            'groups',
            'savedPosters',
            'autoWelcome'
        ));
    }

    /**
     * Toggle auto-send welcome cards setting (AJAX)
     */
    public function toggleAutoWelcome(Tournament $tournament, Request $request)
    {
        $settings = $tournament->settings ?? $tournament->settings()->create([]);
        $settings->update([
            'auto_send_welcome_cards' => $request->boolean('enabled'),
        ]);

        return response()->json(['success' => true, 'enabled' => $settings->auto_send_welcome_cards]);
    }

    /**
     * Generate poster preview with actual data (AJAX)
     */
    public function generatePreview(Tournament $tournament, Request $request)
    {
        ini_set('memory_limit', '512M');
        $tempFiles = [];
        try {
            /*
             * The two upload fields are the only files this endpoint accepts, and it stores what
             * it is given under a public disk — so they get checked before anything is written.
             */
            $request->validate([
                'player_image_file' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:8192',
                'featured_player_image_file' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:8192',
                'man_of_the_match_image_file' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:8192',
                'best_batsman_image_file' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:8192',
                'best_bowler_image_file' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:8192',
                'sponsor_logo_file' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:8192',
                'sponsor_logo_2_file' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:8192',
                'sponsor_logo_3_file' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:8192',
                'image_adjustments' => 'nullable|array',
                'image_adjustments.*.brightness' => 'nullable|integer|min:-50|max:50',
                'image_adjustments.*.contrast' => 'nullable|integer|min:-50|max:50',
                'image_flips' => 'nullable|array',
                'image_flips.*.horizontal' => 'nullable|boolean',
                'image_flips.*.vertical' => 'nullable|boolean',
            ]);

            $templateId = $request->input('template_id');
            $template = TournamentTemplate::findOrFail($templateId);

            abort_if($template->tournament_id !== $tournament->id, 404);

            $renderService = new TemplateRenderService();

            // Build data from request
            $data = $request->only([
                'player_name', 'jersey_number', 'team_name', 'player_image', 'player_type',
                'batting_style', 'bowling_style', 'team_logo',
                'team_a_name', 'team_b_name', 'team_a_short_name', 'team_b_short_name',
                'team_a_logo', 'team_b_logo',
                'team_a_captain_image', 'team_b_captain_image',
                'team_a_captain_name', 'team_b_captain_name',
                'team_a_score', 'team_b_score', 'winner_name', 'winner_logo',
                'team_a_score_wickets', 'team_b_score_wickets',
                'team_a_runs', 'team_b_runs', 'team_a_wickets', 'team_b_wickets',
                'team_a_overs', 'team_b_overs', 'win_margin', 'toss_result',
                'team_a_score_overs', 'team_b_score_overs',
                'match_date', 'match_time', 'match_day', 'match_date_day', 'match_date_month', 'match_date_weekday',
                'venue', 'ground_name', 'match_stage', 'match_number',
                'award_name', 'achievement_text', 'match_details',
                'man_of_the_match_name', 'man_of_the_match_image',
                'best_batsman_name', 'best_batsman_image', 'best_bowler_name', 'best_bowler_image',
                'result_summary', 'batting_figures', 'bowling_figures',
                'batting_runs', 'batting_balls', 'batting_fours', 'batting_sixes',
                'bowling_overs', 'bowling_runs', 'bowling_maidens', 'bowling_wickets',
                'playing_team_name', 'playing_team_logo',
                'featured_player_name', 'featured_player_image', 'featured_player_role',
            ]);

            // Add tournament info
            $data['tournament_name'] = $tournament->name;
            if ($tournament->settings?->logo) {
                $data['tournament_logo'] = $tournament->settings->logo;
            }

            // For match-based types, fetch actual data from DB (avoids ConvertEmptyStringsToNull losing values)
            if ($request->input('match_id') && in_array($template->type, [
                TournamentTemplate::TYPE_MATCH_POSTER,
                TournamentTemplate::TYPE_MATCH_SUMMARY,
                TournamentTemplate::TYPE_AWARD_POSTER,
                // A line-up poster needs the same crests, date and venue. It ignores the score
                // fields this block also fills, which is cheaper than a second lookup.
                TournamentTemplate::TYPE_PLAYING_XI,
            ])) {
                $match = Matches::with(['teamA', 'teamB', 'winner', 'result', 'ground', 'matchAwards.player', 'matchAwards.tournamentAward'])->find($request->input('match_id'));
                if ($match) {
                    // Resolve teams by batting order: team_a = first batting team
                    $teamABatsFirst = $match->result?->team_a_batting_first ?? true;
                    $firstTeam = $teamABatsFirst ? $match->teamA : $match->teamB;
                    $secondTeam = $teamABatsFirst ? $match->teamB : $match->teamA;
                    $firstKey = $teamABatsFirst ? 'a' : 'b';
                    $secondKey = $teamABatsFirst ? 'b' : 'a';

                    $matchData = [
                        'team_a_name' => $firstTeam?->name,
                        'team_b_name' => $secondTeam?->name,
                        'team_a_short_name' => $firstTeam?->short_name ?? $firstTeam?->name,
                        'team_b_short_name' => $secondTeam?->short_name ?? $secondTeam?->name,
                        'team_a_logo' => $firstTeam?->team_logo,
                        'team_b_logo' => $secondTeam?->team_logo,
                        'team_a_sponsor_logo' => $firstTeam?->sponsor_logo,
                        'team_b_sponsor_logo' => $secondTeam?->sponsor_logo,
                        'match_date' => $match->match_date?->format('M d, Y'),
                        'match_date_day' => $match->match_date?->format('d'),
                        'match_date_month' => $match->match_date ? strtoupper($match->match_date->format('M')) : null,
                        'match_date_weekday' => $match->match_date ? strtoupper($match->match_date->format('D')) : null,
                        'match_day' => $match->match_date?->format('l'),
                        'match_time' => $match->start_time ? \Carbon\Carbon::parse($match->start_time)->format('h:i A') : null,
                        'venue' => $match->ground?->name ?? $match->venue,
                        'ground_name' => $match->ground?->name ?? $match->venue,
                        'match_number' => (string) ($match->match_number ?? $match->id),
                        'match_stage' => $match->stage_display,
                    ];

                    if ($match->result) {
                        $r = $match->result;
                        $matchData['team_a_score'] = $r->{'team_' . $firstKey . '_score_display'};
                        $matchData['team_b_score'] = $r->{'team_' . $secondKey . '_score_display'};
                        $matchData['team_a_score_wickets'] = $r->{'team_' . $firstKey . '_score'} . '/' . $r->{'team_' . $firstKey . '_wickets'};
                        $matchData['team_b_score_wickets'] = $r->{'team_' . $secondKey . '_score'} . '/' . $r->{'team_' . $secondKey . '_wickets'};
                        $matchData['team_a_runs'] = (string) $r->{'team_' . $firstKey . '_score'};
                        $matchData['team_b_runs'] = (string) $r->{'team_' . $secondKey . '_score'};
                        $matchData['team_a_wickets'] = (string) $r->{'team_' . $firstKey . '_wickets'};
                        $matchData['team_b_wickets'] = (string) $r->{'team_' . $secondKey . '_wickets'};
                        $matchData['team_a_overs'] = (string) $r->{'team_' . $firstKey . '_overs'};
                        $matchData['team_b_overs'] = (string) $r->{'team_' . $secondKey . '_overs'};
                        $matchData['result_summary'] = $r->result_summary ?: $r->generateResultSummary();
                        $matchData['win_margin'] = $r->margin ? 'Won by ' . $r->margin . ' ' . $r->result_type : '';
                        // Toss
                        if ($r->toss_won_by) {
                            $tossWinner = $r->toss_won_by == $match->team_a_id ? $match->teamA?->name : $match->teamB?->name;
                            $matchData['toss_result'] = $tossWinner . ' won toss, chose to ' . ($r->toss_decision ?? 'bat');
                        }
                    }

                    if ($match->winner) {
                        $matchData['winner_name'] = $match->winner->name;
                        $matchData['winner_logo'] = $match->winner->team_logo;
                    }

                    // Awards: MOTM, Best Batsman, Best Bowler
                    foreach ($match->matchAwards as $award) {
                        $awardSlug = $award->tournamentAward?->slug;
                        $playerName = $award->player?->name;
                        $playerImage = $award->player?->image_path;

                        if (in_array($awardSlug, ['man-of-the-match', 'player-of-the-match'])) {
                            if ($playerName) {
                                $matchData['man_of_the_match_name'] = $playerName;
                            }
                            if ($playerImage) {
                                $matchData['man_of_the_match_image'] = $playerImage;
                            }
                        } elseif ($awardSlug === 'best-batsman') {
                            if ($playerName) {
                                $matchData['best_batsman_name'] = $playerName;
                            }
                            if ($playerImage) {
                                $matchData['best_batsman_image'] = $playerImage;
                            }
                        } elseif ($awardSlug === 'best-bowler') {
                            if ($playerName) {
                                $matchData['best_bowler_name'] = $playerName;
                            }
                            if ($playerImage) {
                                $matchData['best_bowler_image'] = $playerImage;
                            }
                        }
                    }

                    // DB data always overrides JS data (DB is authoritative for match data)
                    foreach ($matchData as $key => $value) {
                        if ($value !== null) {
                            $data[$key] = $value;
                        }
                    }

                    // Extract scorecard data for match_summary type
                    // Since team_a = first batting team, map directly: innings[0] -> a, innings[1] -> b
                    if ($template->type === TournamentTemplate::TYPE_MATCH_SUMMARY && $match->result && $match->result->scorecard_data) {
                        $scorecard = is_string($match->result->scorecard_data)
                            ? json_decode($match->result->scorecard_data, true)
                            : $match->result->scorecard_data;
                        $scorecardInnings = $scorecard['innings'] ?? $scorecard;

                        if (is_array($scorecardInnings) && count($scorecardInnings) >= 2) {
                            if (! empty($scorecardInnings[0]['batting'])) {
                                $data['batting_table_a'] = collect($scorecardInnings[0]['batting'])->sortByDesc('runs')->take(3)->map(fn ($b) => [
                                    'name' => $b['name'] ?? '', 'runs' => $b['runs'] ?? 0, 'balls' => $b['balls'] ?? 0,
                                    'fours' => $b['fours'] ?? 0, 'sixes' => $b['sixes'] ?? 0,
                                ])->values()->toArray();
                            }
                            if (! empty($scorecardInnings[0]['bowling'])) {
                                $data['bowling_table_b'] = collect($scorecardInnings[0]['bowling'])->sortByDesc('wickets')->sortBy('economy')->take(3)->map(fn ($b) => [
                                    'name' => $b['name'] ?? '', 'overs' => $b['overs'] ?? '0', 'runs' => $b['runs'] ?? 0,
                                    'wickets' => $b['wickets'] ?? 0, 'economy' => $b['economy'] ?? '0.00',
                                ])->values()->toArray();
                            }
                            if (! empty($scorecardInnings[1]['batting'])) {
                                $data['batting_table_b'] = collect($scorecardInnings[1]['batting'])->sortByDesc('runs')->take(3)->map(fn ($b) => [
                                    'name' => $b['name'] ?? '', 'runs' => $b['runs'] ?? 0, 'balls' => $b['balls'] ?? 0,
                                    'fours' => $b['fours'] ?? 0, 'sixes' => $b['sixes'] ?? 0,
                                ])->values()->toArray();
                            }
                            if (! empty($scorecardInnings[1]['bowling'])) {
                                $data['bowling_table_a'] = collect($scorecardInnings[1]['bowling'])->sortByDesc('wickets')->sortBy('economy')->take(3)->map(fn ($b) => [
                                    'name' => $b['name'] ?? '', 'overs' => $b['overs'] ?? '0', 'runs' => $b['runs'] ?? 0,
                                    'wickets' => $b['wickets'] ?? 0, 'economy' => $b['economy'] ?? '0.00',
                                ])->values()->toArray();
                            }
                        }
                        /*
                         * Fall back to the CricHeroes "heroes" for the named performers.
                         *
                         * best_batsman_* / best_bowler_* are filled above from this tournament's own
                         * award records. A match imported from CricHeroes has no such records, but its
                         * scorecard carries `cricheroes_heroes` — so on those matches the performer
                         * lines on a summary poster came out blank while the data sat one key away.
                         *
                         * Fallback only: an award entered here is a decision and always wins over an
                         * imported guess, so nothing is overwritten.
                         *
                         * The bowling figure is the compact "wickets/runs" rather than the
                         * "O - M - R - W" the award path builds, because CricHeroes does not give
                         * maidens and printing a 0 there would be inventing a number. The individual
                         * overs/runs/wickets placeholders are set too, for templates that compose
                         * their own line.
                         */
                        $heroes = is_array($scorecard['cricheroes_heroes'] ?? null) ? $scorecard['cricheroes_heroes'] : [];

                        if (! empty($heroes['best_batter']['name']) && empty($data['best_batsman_name'])) {
                            $b = $heroes['best_batter'];
                            $data['best_batsman_name'] = $b['name'];
                            $data['best_batsman_runs'] = (string) ($b['runs'] ?? '');
                            $data['best_batsman_balls'] = (string) ($b['balls'] ?? '');
                            $data['best_batsman_fours'] = (string) ($b['fours'] ?? '');
                            $data['best_batsman_sixes'] = (string) ($b['sixes'] ?? '');
                            $data['best_batsman_batting_figures'] = sprintf(
                                '%s (%s) %sx4 %sx6',
                                $b['runs'] ?? 0, $b['balls'] ?? 0, $b['fours'] ?? 0, $b['sixes'] ?? 0
                            );
                        }

                        if (! empty($heroes['best_bowler']['name']) && empty($data['best_bowler_name'])) {
                            $w = $heroes['best_bowler'];
                            $data['best_bowler_name'] = $w['name'];
                            $data['best_bowler_overs'] = (string) ($w['overs'] ?? '');
                            $data['best_bowler_wickets'] = (string) ($w['wickets'] ?? '');
                            $data['best_bowler_bowling_runs'] = (string) ($w['runs'] ?? '');
                            $data['best_bowler_bowling_figures'] = sprintf(
                                '%s/%s (%s ov)',
                                $w['wickets'] ?? 0, $w['runs'] ?? 0, $w['overs'] ?? 0
                            );
                        }

                        if (! empty($heroes['player_of_the_match']['name']) && empty($data['man_of_the_match_name'])) {
                            $data['man_of_the_match_name'] = $heroes['player_of_the_match']['name'];
                        }
                    }

                    // Apply innings-based swap: when viewing innings 2, swap team_a/team_b
                    $inningsView = (int) $request->input('innings', 1);
                    if ($inningsView === 2) {
                        $swapKeys = [
                            'team_a_name' => 'team_b_name', 'team_a_short_name' => 'team_b_short_name',
                            'team_a_logo' => 'team_b_logo', 'team_a_score' => 'team_b_score',
                            'team_a_score_wickets' => 'team_b_score_wickets',
                            'team_a_runs' => 'team_b_runs', 'team_a_wickets' => 'team_b_wickets',
                            'team_a_overs' => 'team_b_overs',
                            'batting_table_a' => 'batting_table_b',
                            'bowling_table_a' => 'bowling_table_b',
                        ];
                        foreach ($swapKeys as $keyA => $keyB) {
                            $tmp = $data[$keyA] ?? null;
                            $data[$keyA] = $data[$keyB] ?? null;
                            $data[$keyB] = $tmp;
                        }
                    }
                }
            }

            // Award poster: handle uploaded player image and set defaults
            if ($template->type === TournamentTemplate::TYPE_AWARD_POSTER) {
                // Handle custom player image upload
                if ($request->hasFile('player_image_file')) {
                    $uploadedPath = $request->file('player_image_file')
                        ->store('temp_previews', 'public');
                    $data['player_image'] = $uploadedPath;
                    $tempFiles[] = $uploadedPath;

                    /*
                     * Respect the organizer's answer about this upload.
                     *
                     * The generate page has posted `skip_bg_removal` since the cropper was
                     * added, but nothing here read it — so the "Remove Background" checkbox
                     * changed nothing about the poster, and an already-transparent PNG was run
                     * through removal anyway. Only ever applied to the image they uploaded.
                     */
                    if ($request->has('skip_bg_removal') || $request->has('remove_bg')) {
                        $renderService->overrideBackgroundRemoval(
                            'player_image',
                            $request->has('remove_bg')
                                ? $request->boolean('remove_bg')
                                : ! $request->boolean('skip_bg_removal')
                        );
                    }
                }

                if (empty($data['player_name'])) {
                    $data['player_name'] = 'Player Name';
                }
                if (empty($data['award_name'])) {
                    $data['award_name'] = 'Award';
                }
                // Default player image if not provided
                if (empty($data['player_image'])) {
                    $data['player_image'] = 'defaults/default-player.png';
                }
            }

            // Match Summary: handle uploaded award player images, overriding DB-sourced images.
            if ($template->type === TournamentTemplate::TYPE_MATCH_SUMMARY) {
                $summaryImageFields = [
                    'man_of_the_match_image_file' => 'man_of_the_match_image',
                    'best_batsman_image_file'     => 'best_batsman_image',
                    'best_bowler_image_file'      => 'best_bowler_image',
                ];

                foreach ($summaryImageFields as $fileKey => $dataKey) {
                    if ($request->hasFile($fileKey)) {
                        $uploadedPath = $request->file($fileKey)->store('temp_previews', 'public');
                        $data[$dataKey] = $uploadedPath;
                        $tempFiles[] = $uploadedPath;
                    }
                }

                // Per-image bg removal overrides
                $summaryBgOverrides = [
                    'man_of_the_match_image' => 'remove_bg_motm',
                    'best_batsman_image'     => 'remove_bg_best_batsman',
                    'best_bowler_image'      => 'remove_bg_best_bowler',
                ];

                foreach ($summaryBgOverrides as $placeholder => $requestKey) {
                    if ($request->has($requestKey)) {
                        $renderService->overrideBackgroundRemoval($placeholder, $request->boolean($requestKey));
                    }
                }
            }

            // Handle fixtures_poster type — build fixture_area from upcoming matches
            if ($template->type === TournamentTemplate::TYPE_FIXTURES_POSTER) {
                $fixtureCount = (int) $request->input('fixture_count', 5);
                $fixtureCount = max(1, min($fixtureCount, 100));

                $upcomingMatches = $tournament->matches()
                    ->where('status', 'upcoming')
                    ->where('is_cancelled', false)
                    ->with(['teamA', 'teamB', 'ground'])
                    ->orderBy('match_date')
                    ->orderBy('start_time')
                    ->limit($fixtureCount)
                    ->get();

                $data['fixture_area'] = $upcomingMatches->map(fn ($m) => [
                    'team_a' => $m->teamA?->name ?? 'TBD',
                    'team_b' => $m->teamB?->name ?? 'TBD',
                    'team_a_short' => $m->teamA?->short_name ?? $m->teamA?->name ?? 'TBD',
                    'team_b_short' => $m->teamB?->short_name ?? $m->teamB?->name ?? 'TBD',
                    'team_a_logo' => $m->teamA?->team_logo ?? '',
                    'team_b_logo' => $m->teamB?->team_logo ?? '',
                    'date' => $m->match_date?->format('M d, Y') ?? '',
                    'time' => $m->start_time ? Carbon::parse($m->start_time)->format('h:i A') : '',
                    'venue' => $m->ground?->name ?? $m->venue ?? '',
                    'match_number' => (string) ($m->match_number ?? $m->id),
                ])->toArray();

                // Override fixture layout from generate page selection
                $fixtureLayout = $request->input('fixture_layout');
                if ($fixtureLayout && in_array($fixtureLayout, ['row', 'card'])) {
                    $layoutJson = $template->layout_json;
                    if (is_array($layoutJson)) {
                        foreach ($layoutJson as &$el) {
                            $placeholder = $el['placeholder'] ?? '';
                            $type = $el['type'] ?? '';
                            if ($type === 'fixtureArea' || $placeholder === 'fixture_area') {
                                $el['fixtureConfig'] = $el['fixtureConfig'] ?? [];
                                $el['fixtureConfig']['layout'] = $fixtureLayout;
                                $el['fixtureConfig']['maxRows'] = $fixtureCount;
                            }
                        }
                        unset($el);
                        $template->layout_json = $layoutJson;
                    }
                }
            }

            /*
             * Handle playing_xi — turn the picked eleven into the lineup_area region.
             *
             * The XI arrives from the generate page as a JSON array rather than being read from
             * the database, because this schema has nowhere to read it from: there is no lineup
             * table, and `scorecard_data` only exists once a match has been scored, which is
             * after the moment this poster is for. The names are real (they come from the team's
             * roster picker), they are just not persisted.
             */
            if ($template->type === TournamentTemplate::TYPE_PLAYING_XI) {
                $picked = $request->input('lineup_players');

                if (is_string($picked)) {
                    $picked = json_decode($picked, true) ?: [];
                }

                /*
                 * Fall back to the XI the team actually named for this match.
                 *
                 * When this poster was built there was nowhere to read a line-up from, so the
                 * organizer retyped eleven names for every render. Now that a manager can name
                 * the side (match_lineups), use it — but only when the page sent nothing, so
                 * typing names by hand still overrides a saved XI rather than fighting it.
                 */
                if (empty($picked) && $request->filled('match_id') && $request->filled('lineup_team_id')) {
                    $saved = Matches::find($request->input('match_id'))
                        ?->lineupFor((int) $request->input('lineup_team_id'));

                    if ($saved && $saved->isNotEmpty()) {
                        $picked = $saved->map(fn ($row) => [
                            'name' => $row->player?->name ?? '',
                            'badge' => $row->role ?? '',
                        ])->all();
                    }
                }

                $data['lineup_area'] = collect(is_array($picked) ? $picked : [])
                    ->map(fn ($row, $i) => [
                        'name' => trim((string) ($row['name'] ?? '')),
                        // Only the four a line-up graphic actually shows; anything else is noise
                        // that would render as a chip nobody can read.
                        'badge' => in_array(strtoupper((string) ($row['badge'] ?? '')), ['C', 'VC', 'WK', 'DEBUT'], true)
                            ? strtoupper($row['badge'])
                            : '',
                        'number' => $i + 1,
                    ])
                    ->filter(fn ($row) => $row['name'] !== '')
                    ->values()
                    ->all();

                /*
                 * Whose XI this is, said without the template author having to know whether the
                 * picked side ended up as A or B — that depends on batting order, which for an
                 * unplayed match is a default rather than a fact.
                 */
                /*
                 * A cut-out uploaded for this poster only.
                 *
                 * Stored under temp_previews and cleaned up with the other temp files: a photo
                 * chosen for one poster is not a change to the player's profile picture, and
                 * writing it there would be a surprising side effect of drawing a graphic.
                 */
                if ($request->hasFile('featured_player_image_file')) {
                    $uploadedPath = $request->file('featured_player_image_file')
                        ->store('temp_previews', 'public');
                    $data['featured_player_image'] = $uploadedPath;
                    $tempFiles[] = $uploadedPath;
                }

                // Checked means cut it out; unchecked means use the photo as it is. Absent means
                // fall back to the placeholder default, which for a featured player is removal.
                if ($request->has('remove_bg')) {
                    $renderService->overrideBackgroundRemoval('featured_player_image', $request->boolean('remove_bg'));
                }

                $lineupTeamId = (int) $request->input('lineup_team_id');

                if ($lineupTeamId) {
                    $lineupTeam = ActualTeam::find($lineupTeamId);
                    $opponent = null;

                    if ($match = Matches::with(['teamA', 'teamB'])->find($request->input('match_id'))) {
                        $opponent = $match->team_a_id == $lineupTeamId ? $match->teamB : $match->teamA;
                    }

                    $data['lineup_team_name'] = $lineupTeam?->name ?? '';
                    $data['lineup_team_short_name'] = $lineupTeam?->short_name ?? $lineupTeam?->name ?? '';
                    $data['lineup_team_logo'] = $lineupTeam?->team_logo ?? '';
                    $data['lineup_team_sponsor_logo'] = $lineupTeam?->sponsor_logo ?? '';
                    $data['opponent_team_name'] = $opponent?->name ?? '';
                    $data['opponent_team_short_name'] = $opponent?->short_name ?? $opponent?->name ?? '';
                    $data['opponent_team_logo'] = $opponent?->team_logo ?? '';
                    $data['opponent_team_sponsor_logo'] = $opponent?->sponsor_logo ?? '';
                }
            }

            // Handle point_table type — build table_data from group entries
            if ($template->type === TournamentTemplate::TYPE_POINT_TABLE && $request->input('group_id')) {
                $group = $tournament->groups()->find($request->input('group_id'));
                if ($group) {
                    $entries = $group->pointTableEntries()->with('team')->ranked()->get();
                    $data['table_data'] = $entries->map(fn ($entry) => [
                        'position' => $entry->position,
                        'team_name' => $entry->team?->name ?? 'Unknown',
                        'team_logo' => $entry->team?->team_logo ?? '',
                        'matches_played' => $entry->matches_played,
                        'won' => $entry->won,
                        'lost' => $entry->lost,
                        'tied' => $entry->tied,
                        'net_run_rate' => $entry->net_run_rate,
                        'points' => $entry->points,
                        'qualified' => $entry->qualified,
                    ])->toArray();
                    $data['group_name'] = $group->name;
                    $data['last_updated'] = now()->format('M d, Y H:i');
                }
            }

            /*
             * Per-element overrides from the generate page's Fields panel.
             *
             * Keyed by the element's index in layout_json, because that addresses every element
             * uniquely — a placeholder cannot: a design may place `tournament_name` twice, and a
             * plain caption typed in the editor has no placeholder at all. Each entry may carry a
             * new value, a hidden flag, or both.
             *
             * Where an element is bound to a placeholder the value goes into $data, so the
             * renderer's own formatting and image handling still apply. A static text element has
             * no data key, so its text is rewritten on a COPY of the layout — $template is not
             * saved here, and must not be: this is one poster, not a template edit.
             */
            $elementOverrides = $request->input('element_overrides', []);

            if (is_array($elementOverrides) && $elementOverrides !== []) {
                $layout = $template->layout_json;

                if (is_array($layout)) {
                    foreach ($elementOverrides as $index => $override) {
                        $index = (int) $index;

                        if (! isset($layout[$index]) || ! is_array($override)) {
                            continue;
                        }

                        $placeholder = $layout[$index]['placeholder'] ?? null;

                        // Visibility is a layout choice, allowed on anything — including the
                        // locked fields below, and on images and shapes that have no value.
                        if (filter_var($override['hidden'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                            $layout[$index]['hidden'] = true;
                        }

                        if (! array_key_exists('value', $override)) {
                            continue;
                        }

                        $value = is_scalar($override['value']) ? (string) $override['value'] : '';

                        // A score is read off the match, never typed. See
                        // TournamentTemplate::lockedPlaceholders().
                        if (TournamentTemplate::isLockedPlaceholder($placeholder)) {
                            continue;
                        }

                        if ($placeholder) {
                            $data[$placeholder] = $value;
                        } else {
                            $layout[$index]['text'] = $value;
                        }
                    }

                    $template->layout_json = $layout;
                }
            }

            /*
             * Sponsor / partner logos, uploaded for this poster.
             *
             * Every template type offers these slots (TournamentTemplate::sponsorPlaceholders()),
             * so unlike the player images this is not inside a per-type block. Stored under
             * temp_previews and cleaned up with the rest: a logo chosen for one poster is not a
             * change to the tournament.
             */
            foreach (TournamentTemplate::sponsorPlaceholders() as $sponsorKey) {
                $fileKey = $sponsorKey . '_file';

                if ($request->hasFile($fileKey)) {
                    $uploadedPath = $request->file($fileKey)->store('temp_previews', 'public');
                    $data[$sponsorKey] = $uploadedPath;
                    $tempFiles[] = $uploadedPath;
                }
            }

            // Per-placeholder image adjustments (brightness/contrast).
            // Applies to all poster types — the generate page sends these for any
            // image that has the adjustment sliders visible.
            $imageAdjustments = $request->input('image_adjustments', []);
            if (is_array($imageAdjustments)) {
                foreach ($imageAdjustments as $placeholder => $values) {
                    if (is_array($values)) {
                        $renderService->overrideImageAdjustment($placeholder, $values);
                    }
                }
            }

            // Per-placeholder mirroring, so a head-to-head poster can turn one
            // player to face the other instead of facing off the poster edge.
            $imageFlips = $request->input('image_flips', []);
            if (is_array($imageFlips)) {
                foreach ($imageFlips as $placeholder => $values) {
                    if (is_array($values)) {
                        $renderService->overrideImageFlip($placeholder, [
                            'horizontal' => filter_var($values['horizontal'] ?? false, FILTER_VALIDATE_BOOLEAN),
                            'vertical' => filter_var($values['vertical'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        ]);
                    }
                }
            }

            // Remove hidden fields (toggled off by user)
            $hiddenFields = $request->input('hidden_fields', []);
            if (is_array($hiddenFields)) {
                foreach ($hiddenFields as $field) {
                    unset($data[$field]);
                }
            }

            // Filter empty values (but keep array data like table_data, fixture_area)
            $data = array_filter($data, fn ($v) => is_array($v) ? ! empty($v) : ($v !== null && $v !== ''));

            // Skip blank placeholders when generating from actual data (not editor preview)
            $hasMatchData = $request->input('match_id') || $request->input('player_id') || $request->input('group_id')
                || $template->type === TournamentTemplate::TYPE_FIXTURES_POSTER;
            $base64Image = $renderService->renderToBase64($template, $data, true, (bool) $hasMatchData);

            // Save poster to storage and database (only when explicitly requested)
            $shouldSave = $request->boolean('save_poster', false);
            $savedPoster = null;
            if ($shouldSave) {
                try {
                    $appPrefix = config('settings.app_name') ?: config('app.name');
                    $filename = $appPrefix . '-' . $template->type . '-' . now()->format('YmdHis') . '-' . uniqid() . '.png';
                    $savePath = 'generated_posters/' . $tournament->id . '/' . $filename;
                    $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64Image));
                    Storage::disk('public')->put($savePath, $imageData);

                    // Build a descriptive label
                    $label = match($template->type) {
                        TournamentTemplate::TYPE_MATCH_POSTER, TournamentTemplate::TYPE_MATCH_SUMMARY =>
                            ($data['team_a_name'] ?? '') . ' vs ' . ($data['team_b_name'] ?? ''),
                        TournamentTemplate::TYPE_AWARD_POSTER =>
                            ($data['player_name'] ?? 'Player') . ' - ' . ($data['award_name'] ?? 'Award'),
                        TournamentTemplate::TYPE_WELCOME_CARD =>
                            ($data['player_name'] ?? 'Player') . ' - Welcome',
                        TournamentTemplate::TYPE_POINT_TABLE =>
                            ($data['group_name'] ?? 'Points Table'),
                        TournamentTemplate::TYPE_FIXTURES_POSTER => 'Fixtures',
                        TournamentTemplate::TYPE_PLAYING_XI =>
                            ($data['lineup_team_name'] ?? 'XI') . ' XI v ' . ($data['opponent_team_name'] ?? 'Opponent'),
                        default => ucwords(str_replace('_', ' ', $template->type)),
                    };

                    $savedPoster = GeneratedPoster::create([
                        'tournament_id' => $tournament->id,
                        'user_id' => auth()->id(),
                        'type' => $template->type,
                        'image_path' => $savePath,
                        'label' => $label,
                        'template_id' => $template->id,
                    ]);
                } catch (\Exception $e) {
                    // Non-critical: poster still works via base64
                }
            }

            // Clean up temp uploaded files
            foreach ($tempFiles as $tempPath) {
                Storage::disk('public')->delete($tempPath);
            }

            return response()->json([
                'success' => true,
                'image' => $base64Image,
                'download_url' => $savedPoster ? asset('storage/' . $savedPoster->image_path) : null,
                'poster_id' => $savedPoster?->id,
                'poster_label' => $savedPoster?->label,
                'poster_type' => $template->type,
                'poster_created' => $savedPoster?->created_at?->format('M d, h:i A'),
            ]);
        } catch (\Exception $e) {
            // Clean up temp files on error too
            foreach ($tempFiles as $tempPath) {
                Storage::disk('public')->delete($tempPath);
            }
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a generated poster
     */
    public function deleteGeneratedPoster(Tournament $tournament, GeneratedPoster $poster)
    {
        abort_if($poster->tournament_id !== $tournament->id, 404);

        $poster->deleteImage();
        $poster->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Set a generated poster as a match's public poster image
     */
    public function setMatchPoster(Tournament $tournament, GeneratedPoster $poster, Request $request)
    {
        abort_if($poster->tournament_id !== $tournament->id, 404);

        $request->validate(['match_id' => 'required|exists:matches,id']);

        $match = Matches::where('id', $request->match_id)
            ->where('tournament_id', $tournament->id)
            ->firstOrFail();

        // Copy the generated poster to match_posters directory so it persists independently
        $ext = pathinfo($poster->image_path, PATHINFO_EXTENSION) ?: 'png';
        $filename = 'match-poster-' . $match->id . '-' . now()->format('YmdHis') . '.' . $ext;
        $destPath = 'match_posters/' . $filename;

        if (Storage::disk('public')->exists($poster->image_path)) {
            Storage::disk('public')->copy($poster->image_path, $destPath);
        } else {
            return response()->json(['success' => false, 'error' => 'Poster image file not found.'], 404);
        }

        // Delete old poster image if it exists
        if ($match->poster_image && Storage::disk('public')->exists($match->poster_image)) {
            Storage::disk('public')->delete($match->poster_image);
        }

        $match->update(['poster_image' => $destPath]);

        return response()->json([
            'success' => true,
            'message' => 'Poster set for ' . ($match->teamA?->short_name ?? 'TBA') . ' vs ' . ($match->teamB?->short_name ?? 'TBA'),
        ]);
    }

    /**
     * Get match awards for award poster generation (AJAX)
     */
    public function getMatchAwards(Tournament $tournament, Matches $match)
    {
        $match->load(['teamA', 'teamB', 'result']);

        // Get awards for this match
        $awards = $match->matchAwards()
            ->with(['player.actualTeam', 'tournamentAward'])
            ->get()
            ->map(fn ($award) => [
                'id' => $award->id,
                'award_name' => $award->tournamentAward->name ?? 'Award',
                'player_id' => $award->player_id,
                'player_name' => $award->player->jersey_name ?? $award->player->name ?? 'Unknown',
                'player_image' => $award->player->image_path ?? null,
                'team_name' => $award->player->actualTeam?->name ?? '',
                'team_logo' => $award->player->actualTeam?->team_logo ?? null,
            ]);

        // Build set of player IDs that have awards
        $awardPlayerIds = $awards->pluck('player_id')->filter()->toArray();

        // Get all players from both teams
        $teamAPlayers = Player::where('actual_team_id', $match->team_a_id)
            ->where('status', 'approved')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->jersey_name ?: $p->name ?: 'Player #' . $p->id,
                'image' => $p->image_path ?? null,
                'team_name' => $match->teamA?->name ?? '',
                'team_logo' => $match->teamA?->team_logo ?? null,
                'has_award' => in_array($p->id, $awardPlayerIds),
            ]);

        $teamBPlayers = Player::where('actual_team_id', $match->team_b_id)
            ->where('status', 'approved')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->jersey_name ?: $p->name ?: 'Player #' . $p->id,
                'image' => $p->image_path ?? null,
                'team_name' => $match->teamB?->name ?? '',
                'team_logo' => $match->teamB?->team_logo ?? null,
                'has_award' => in_array($p->id, $awardPlayerIds),
            ]);

        $matchData = [
            'team_a_name' => $match->teamA?->name ?? '',
            'team_a_logo' => $match->teamA?->team_logo_url ?? '',
            'team_b_name' => $match->teamB?->name ?? '',
            'team_b_logo' => $match->teamB?->team_logo_url ?? '',
            'status' => $match->status,
        ];

        // Include score data if match result exists
        if ($match->result) {
            $r = $match->result;
            // Swap teams if team B batted first
            if ($r->team_a_batting_first === false) {
                $matchData['team_a_name'] = $match->teamB?->name ?? '';
                $matchData['team_a_logo'] = $match->teamB?->team_logo_url ?? '';
                $matchData['team_b_name'] = $match->teamA?->name ?? '';
                $matchData['team_b_logo'] = $match->teamA?->team_logo_url ?? '';
                $matchData['team_a_score'] = $r->team_b_score_display;
                $matchData['team_b_score'] = $r->team_a_score_display;
            } else {
                $matchData['team_a_score'] = $r->team_a_score_display;
                $matchData['team_b_score'] = $r->team_b_score_display;
            }
            $matchData['result_summary'] = $r->result_summary ?: $r->generateResultSummary();
        }

        return response()->json([
            'awards' => $awards,
            'players' => [
                'team_a' => $teamAPlayers,
                'team_b' => $teamBPlayers,
            ],
            'match' => $matchData,
        ]);
    }

    /**
     * Show create template form
     */
    public function create(Tournament $tournament, Request $request)
    {
        $type = $request->get('type', TournamentTemplate::TYPE_WELCOME_CARD);
        $placeholders = TournamentTemplate::getDefaultPlaceholders($type);
        $template = null; // No existing template for create

        /*
         * Open on the shape the type is for.
         *
         * The editor defaulted every new template to 1080x1080, so a landscape auction poster
         * began as a square that had to be changed before the first element could be placed —
         * and anything placed before that change moved when it was.
         */
        [$defaultCanvasWidth, $defaultCanvasHeight] = TournamentTemplate::defaultCanvas($type);

        // Use the new Fabric.js editor
        return view('backend.pages.tournaments.templates.editor', compact(
            'tournament',
            'type',
            'template',
            'placeholders',
            'defaultCanvasWidth',
            'defaultCanvasHeight'
        ));
    }

    /**
     * Create a template from a ready-made design.
     *
     * Applying a preset is just a create, so it lives beside store() and under the same
     * Superadmin gate. It never becomes the default template — see TemplatePresetService::apply().
     */
    public function applyPreset(Tournament $tournament, Request $request)
    {
        $service = new TemplatePresetService();

        $validated = $request->validate([
            'preset' => ['required', 'string', \Illuminate\Validation\Rule::in(array_keys($service->all()))],
        ]);

        $template = $service->apply($tournament, $validated['preset']);

        return redirect()
            ->route('admin.tournaments.templates.generate', [
                'tournament' => $tournament->id,
                'type' => $template->type,
            ])
            ->with('success', "\"{$template->name}\" was added. Open the editor to change any of it.");
    }

    /**
     * Store a new template
     */
    public function store(Tournament $tournament, Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:' . implode(',', TournamentTemplate::TYPES),
            'background_image' => 'nullable|image|max:5120',
            'background_image_base64' => 'nullable|string',
            'layout_json' => 'nullable|json',
            'overlay_images' => 'nullable|json',
            'canvas_width' => 'nullable|integer|min:540|max:2160',
            'canvas_height' => 'nullable|integer|min:540|max:3840',
            'is_default' => 'boolean',
        ]);

        // Handle file upload
        if ($request->hasFile('background_image')) {
            $validated['background_image'] = $request->file('background_image')
                ->store('tournament_templates/' . $tournament->id, 'public');
        }
        // Handle base64 background image from editor
        elseif ($request->filled('background_image_base64')) {
            $validated['background_image'] = $this->saveBase64Image(
                $request->input('background_image_base64'),
                $tournament->id
            );
        }
        unset($validated['background_image_base64']);

        // Parse layout JSON
        if (isset($validated['layout_json'])) {
            $validated['layout_json'] = json_decode($validated['layout_json'], true);
        }

        // Parse overlay images JSON
        if (isset($validated['overlay_images'])) {
            $validated['overlay_images'] = json_decode($validated['overlay_images'], true);
        }

        // Set default placeholders if not provided
        $validated['placeholders'] = TournamentTemplate::getDefaultPlaceholders($validated['type']);

        $template = $tournament->templates()->create($validated);

        // Set as default if requested
        if ($request->boolean('is_default')) {
            $template->setAsDefault();
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Template created successfully.',
                'redirect' => route('admin.tournaments.templates.edit', [$tournament, $template]),
            ]);
        }

        return redirect()
            ->route('admin.tournaments.templates.index', $tournament)
            ->with('success', 'Template created successfully.');
    }

    /**
     * Show edit template form
     */
    public function edit(Tournament $tournament, TournamentTemplate $template, Request $request)
    {
        abort_if($template->tournament_id !== $tournament->id, 404);

        // AJAX: return layout placeholders for field toggle UI
        if ($request->has('ajax_layout')) {
            return response()->json([
                'layout' => $template->layout_json ?? [],
            ]);
        }

        $type = $template->type;
        $placeholders = TournamentTemplate::getDefaultPlaceholders($template->type);

        // Use the new Fabric.js editor
        return view('backend.pages.tournaments.templates.editor', compact(
            'tournament',
            'template',
            'type',
            'placeholders'
        ));
    }

    /**
     * Update a template
     */
    public function update(Tournament $tournament, TournamentTemplate $template, Request $request)
    {
        abort_if($template->tournament_id !== $tournament->id, 404);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'background_image' => 'nullable|image|max:5120',
            'background_image_base64' => 'nullable|string',
            'layout_json' => 'nullable|json',
            'overlay_images' => 'nullable|json',
            'canvas_width' => 'nullable|integer|min:540|max:2160',
            'canvas_height' => 'nullable|integer|min:540|max:3840',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        // Handle file upload
        if ($request->hasFile('background_image')) {
            // Delete old file
            if ($template->background_image) {
                Storage::disk('public')->delete($template->background_image);
            }

            $validated['background_image'] = $request->file('background_image')
                ->store('tournament_templates/' . $tournament->id, 'public');
        }
        // Handle base64 background image from editor
        elseif ($request->filled('background_image_base64')) {
            // Delete old file
            if ($template->background_image) {
                Storage::disk('public')->delete($template->background_image);
            }

            $validated['background_image'] = $this->saveBase64Image(
                $request->input('background_image_base64'),
                $tournament->id
            );
        }
        unset($validated['background_image_base64']);

        // Parse layout JSON
        if (isset($validated['layout_json'])) {
            $validated['layout_json'] = json_decode($validated['layout_json'], true);
        }

        // Parse overlay images JSON
        if (isset($validated['overlay_images'])) {
            $validated['overlay_images'] = json_decode($validated['overlay_images'], true);
        }

        $template->update($validated);

        // Set as default if requested
        if ($request->boolean('is_default')) {
            $template->setAsDefault();
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Template saved successfully.',
            ]);
        }

        return redirect()
            ->route('admin.tournaments.templates.index', $tournament)
            ->with('success', 'Template updated successfully.');
    }

    /**
     * Quick update canvas size (AJAX from index page)
     */
    public function updateSize(Tournament $tournament, TournamentTemplate $template, Request $request)
    {
        abort_if($template->tournament_id !== $tournament->id, 404);

        $validated = $request->validate([
            'canvas_width' => 'required|integer|min:540|max:3840',
            'canvas_height' => 'required|integer|min:540|max:3840',
        ]);

        $template->update($validated);

        return response()->json(['success' => true, 'message' => 'Size updated.']);
    }

    /**
     * Delete a template
     */
    /**
     * Set or replace a template's background photo, without opening the drag editor.
     *
     * The photo is COVER-CROPPED to the template's canvas before it is stored, because
     * TemplateRenderService resizes a background with a single imagecopyresampled() from the
     * whole source to the whole canvas — i.e. it stretches. A landscape match photo dropped
     * straight onto a 1080x1080 poster comes out squashed, which looks like a broken renderer
     * rather than a cropping decision nobody made.
     */
    public function updateBackground(Tournament $tournament, TournamentTemplate $template, Request $request)
    {
        abort_if($template->tournament_id !== $tournament->id, 404);

        $request->validate([
            'background_image' => 'required|image|mimes:jpeg,jpg,png,webp|max:8192',
        ]);

        $width = (int) ($template->canvas_width ?: 1080);
        $height = (int) ($template->canvas_height ?: 1080);

        $source = $request->file('background_image');
        $image = match (strtolower($source->getClientOriginalExtension() ?: $source->guessExtension())) {
            'png' => @imagecreatefrompng($source->getRealPath()),
            'webp' => @imagecreatefromwebp($source->getRealPath()),
            default => @imagecreatefromjpeg($source->getRealPath()),
        };

        if (! $image) {
            return back()->with('error', __('That image could not be read.'));
        }

        $srcW = imagesx($image);
        $srcH = imagesy($image);
        $scale = max($width / $srcW, $height / $srcH);
        $scaledW = (int) round($srcW * $scale);
        $scaledH = (int) round($srcH * $scale);

        $scaled = imagecreatetruecolor($scaledW, $scaledH);
        imagecopyresampled($scaled, $image, 0, 0, 0, 0, $scaledW, $scaledH, $srcW, $srcH);

        $canvas = imagecreatetruecolor($width, $height);
        imagecopy($canvas, $scaled, 0, 0, (int) (($scaledW - $width) / 2), (int) (($scaledH - $height) / 2), $width, $height);

        ob_start();
        imagejpeg($canvas, null, 90);
        $bytes = ob_get_clean();

        imagedestroy($image);
        imagedestroy($scaled);
        imagedestroy($canvas);

        $path = 'tournament_templates/' . $tournament->id . '/bg_' . uniqid() . '.jpg';
        Storage::disk('public')->put($path, $bytes);

        /*
         * Replace the old file only when nothing else points at it — duplicating a template
         * copies the path, and templates can be pointed at one shared image deliberately.
         * Same guard as destroy().
         */
        $previous = $template->background_image;
        $template->update(['background_image' => $path]);

        if ($previous && $previous !== $path) {
            $stillInUse = TournamentTemplate::where('background_image', $previous)->exists();

            if (! $stillInUse) {
                Storage::disk('public')->delete($previous);
            }
        }

        return back()->with('success', __('Background updated for ":name".', ['name' => $template->name]));
    }

    public function destroy(Tournament $tournament, TournamentTemplate $template)
    {
        abort_if($template->tournament_id !== $tournament->id, 404);

        /*
         * Only delete the background if this template is the last one using it.
         *
         * duplicate() copies the background_image PATH rather than the file, so a copy and its
         * original point at the same image. Deleting the copy then deleted the picture out from
         * under the original, which kept its path and started rendering on a blank canvas —
         * silently, because a missing background just falls back to a flat colour.
         */
        if ($template->background_image) {
            $stillInUse = TournamentTemplate::where('background_image', $template->background_image)
                ->where('id', '!=', $template->id)
                ->exists();

            if (! $stillInUse) {
                Storage::disk('public')->delete($template->background_image);
            }
        }

        $template->delete();

        /*
         * Back to wherever the delete was pressed, not always the template list.
         *
         * The generate page manages templates inline now, and being thrown to the index after
         * removing one loses the match, side and XI the organizer had already picked. From the
         * index itself back() is the index, so nothing changes there.
         */
        return redirect()
            ->back(fallback: route('admin.tournaments.templates.index', $tournament))
            ->with('success', 'Template deleted successfully.');
    }

    /**
     * Set a template as default
     */
    public function setDefault(Tournament $tournament, TournamentTemplate $template)
    {
        abort_if($template->tournament_id !== $tournament->id, 404);

        $template->setAsDefault();

        return redirect()
            ->back()
            ->with('success', 'Template set as default.');
    }

    /**
     * Preview a template with sample data
     */
    public function preview(Tournament $tournament, TournamentTemplate $template, Request $request)
    {
        ini_set('memory_limit', '512M');
        abort_if($template->tournament_id !== $tournament->id, 404);

        $previewUrl = null;
        $previewError = null;

        // Get all placeholders for this template type
        $placeholders = TournamentTemplate::getDefaultPlaceholders($template->type);
        $imagePlaceholders = ['player_image', 'team_logo', 'playing_team_logo', 'tournament_logo', 'team_a_logo', 'team_b_logo',
                              'team_a_captain_image', 'team_b_captain_image', 'man_of_the_match_image',
                              'team_a_sponsor_logo', 'team_b_sponsor_logo', 'qr_code'];

        // Get custom data from request (text fields)
        $renderService = new TemplateRenderService();
        $textPlaceholders = array_diff($placeholders, $imagePlaceholders);
        $customData = $request->only($textPlaceholders);

        // Handle uploaded images
        $uploadedImages = [];
        $bgRemovalService = new ImageBackgroundRemovalService();

        foreach ($imagePlaceholders as $imageField) {
            if ($request->hasFile($imageField)) {
                $file = $request->file($imageField);
                // Store temporarily for preview
                $path = $file->store('temp_previews', 'public');
                $uploadedImages[] = $path;

                // Remove background for player images
                if (in_array($imageField, ['player_image', 'man_of_the_match_image', 'team_a_captain_image', 'team_b_captain_image'])) {
                    $noBgPath = $bgRemovalService->removeBackground($path);
                    if ($noBgPath) {
                        $uploadedImages[] = $noBgPath;
                        $path = $noBgPath;
                    }
                }

                $customData[$imageField] = $path;
            }
        }

        $sampleData = $renderService->getSampleData($template->type, array_filter($customData));

        // Add tournament-specific data
        $sampleData['tournament_name'] = $tournament->name;
        if ($tournament->settings?->logo) {
            $sampleData['tournament_logo'] = $tournament->settings->logo;
        }

        // Generate rendered preview if template has layout
        if ($template->background_image && ! empty($template->layout_json)) {
            try {
                $previewUrl = $renderService->renderToBase64($template, $sampleData);
            } catch (\Exception $e) {
                $previewError = 'Failed to render preview: ' . $e->getMessage();
                // Fallback to just background image
                $previewUrl = $template->background_image_url;
            }
        } elseif ($template->background_image) {
            // No layout, just show background
            $previewUrl = $template->background_image_url;
        }

        // Clean up temporary uploaded images
        foreach ($uploadedImages as $path) {
            Storage::disk('public')->delete($path);
        }

        return view('backend.pages.tournaments.templates.preview', compact(
            'tournament',
            'template',
            'previewUrl',
            'sampleData',
            'previewError'
        ));
    }

    /**
     * Get sample value for a placeholder
     */
    private function getSampleValue(string $placeholder, Tournament $tournament): string
    {
        return match ($placeholder) {
            'tournament_name' => $tournament->name,
            'tournament_logo' => $tournament->settings?->logo ? asset('storage/' . $tournament->settings->logo) : '[Logo]',
            'player_name' => 'John Doe',
            'jersey_name' => 'J. DOE',
            'jersey_number' => '10',
            'team_name' => 'Sample Team FC',
            'team_logo' => '[Team Logo]',
            'playing_team_name' => 'Playing Team FC',
            'playing_team_logo' => '[Playing Team Logo]',
            'team_a_name', 'team_a_short_name' => 'Team Alpha',
            'team_b_name', 'team_b_short_name' => 'Team Beta',
            'team_a_logo', 'team_b_logo' => '[Team Logo]',
            'team_a_score' => '150/6 (20.0)',
            'team_b_score' => '145/8 (20.0)',
            'team_a_score_wickets' => '150/6',
            'team_b_score_wickets' => '145/8',
            'team_a_runs' => '150',
            'team_b_runs' => '145',
            'team_a_wickets' => '6',
            'team_b_wickets' => '8',
            'team_a_overs' => '20.0',
            'team_b_overs' => '20.0',
            'match_date' => now()->format('M d, Y'),
            'match_time' => '3:00 PM',
            'match_day' => now()->format('l'),
            'venue', 'ground_name' => 'City Sports Ground',
            'match_stage' => 'Group Stage',
            'match_number' => '1',
            'result_summary' => 'Team Alpha won by 5 runs',
            'winner_name' => 'Team Alpha',
            'win_margin' => 'Won by 5 runs',
            'toss_result' => 'Team Alpha won toss, chose to bat',
            'man_of_the_match_name' => 'John Doe',
            'player_image', 'man_of_the_match_image' => '[Player Image]',
            'player_type' => 'All Rounder',
            'batting_style' => 'Right Handed',
            'bowling_style' => 'Right Arm Medium',
            'award_name' => 'Man of the Match',
            'achievement_text' => '75 runs off 45 balls',
            'description' => $tournament->description ?? 'Cricket Tournament',
            'start_date' => $tournament->start_date?->format('M d, Y') ?? 'TBA',
            'end_date' => $tournament->end_date?->format('M d, Y') ?? 'TBA',
            'location' => 'City Sports Complex',
            'registration_link' => route('public.tournament.show', $tournament->slug),
            'contact_phone' => '+1 234 567 8900',
            'contact_email' => 'info@example.com',
            'title' => 'Champions',
            'season' => 'Season 1',
            'year' => now()->year,
            'group_name' => 'Group A',
            'last_updated' => now()->format('M d, Y H:i'),
            'lineup_team_name' => 'Team Alpha',
            'lineup_team_short_name' => 'ALP',
            'lineup_team_logo' => '[Team Logo]',
            'opponent_team_name' => 'Team Beta',
            'opponent_team_short_name' => 'BET',
            'opponent_team_logo' => '[Team Logo]',
            'featured_player_name' => 'John Doe',
            'featured_player_role' => 'All Rounder',
            'featured_player_image' => '[Player Image]',
            default => "[$placeholder]",
        };
    }

    /**
     * Duplicate a template
     */
    public function duplicate(Tournament $tournament, TournamentTemplate $template)
    {
        abort_if($template->tournament_id !== $tournament->id, 404);

        // Find next copy number
        $baseName = preg_replace('/\s*\(Copy(?:\s+\d+)?\)\s*$/', '', $template->name);
        $existingCopies = TournamentTemplate::where('tournament_id', $tournament->id)
            ->where('name', 'like', $baseName . ' (Copy%')
            ->count();
        $copyNum = $existingCopies + 1;
        $newName = $baseName . ' (Copy ' . $copyNum . ')';

        $newTemplate = $template->replicate();
        $newTemplate->name = $newName;
        $newTemplate->is_default = false;

        // Copy background image if exists
        if ($template->background_image && Storage::disk('public')->exists($template->background_image)) {
            $ext = pathinfo($template->background_image, PATHINFO_EXTENSION);
            $newPath = 'tournament_templates/' . uniqid('bg_') . '.' . $ext;
            Storage::disk('public')->copy($template->background_image, $newPath);
            $newTemplate->background_image = $newPath;
        }

        // Copy every uploaded-image file the template references and remap the
        // paths in BOTH overlay_images AND layout_json. Uploaded-image layers embed
        // an `imagePath`; previously only plain-string overlay entries were copied,
        // so object-form overlays (and the layout_json copies) kept the ORIGINAL
        // paths and rendered blank on the duplicate — the "missing layers" bug.
        $pathMap = [];
        $copyPath = function (?string $old) use (&$pathMap) {
            if (! $old || ! is_string($old)) {
                return $old;
            }
            if (array_key_exists($old, $pathMap)) {
                return $pathMap[$old];
            }
            if (! Storage::disk('public')->exists($old)) {
                return $pathMap[$old] = $old;
            }
            $ext = pathinfo($old, PATHINFO_EXTENSION);
            $dir = str_contains($old, '/overlays/') ? 'tournament_templates/overlays/' : 'tournament_templates/';
            $newPath = $dir . uniqid('ov_') . ($ext ? '.' . $ext : '');
            Storage::disk('public')->copy($old, $newPath);
            return $pathMap[$old] = $newPath;
        };

        // overlay_images: array of string paths (legacy) or objects with imagePath.
        if (is_array($template->overlay_images)) {
            $newOverlays = [];
            foreach ($template->overlay_images as $overlay) {
                if (is_string($overlay)) {
                    $newOverlays[] = $copyPath($overlay);
                } elseif (is_array($overlay) && isset($overlay['imagePath'])) {
                    $overlay['imagePath'] = $copyPath($overlay['imagePath']);
                    $newOverlays[] = $overlay;
                } else {
                    $newOverlays[] = $overlay;
                }
            }
            $newTemplate->overlay_images = $newOverlays;
        }

        // layout_json: remap any element (e.g. uploadedImage) that embeds imagePath.
        if (is_array($template->layout_json)) {
            $layout = $template->layout_json;
            foreach ($layout as &$el) {
                if (is_array($el) && ! empty($el['imagePath'])) {
                    $el['imagePath'] = $copyPath($el['imagePath']);
                }
            }
            unset($el);
            $newTemplate->layout_json = $layout;
        }

        $newTemplate->save();

        return redirect()
            ->route('admin.tournaments.templates.edit', [$tournament, $newTemplate])
            ->with('success', 'Template duplicated as "' . $newName . '".');
    }

    /**
     * Render template preview with sample data (AJAX)
     */
    public function renderPreview(Tournament $tournament, TournamentTemplate $template, Request $request)
    {
        ini_set('memory_limit', '512M');
        abort_if($template->tournament_id !== $tournament->id, 404);

        try {
            $renderService = new TemplateRenderService();

            // Get custom data from request or use defaults
            $customData = $request->input('data', []);
            $sampleData = $renderService->getSampleData($template->type, $customData);

            // Add tournament-specific data
            $sampleData['tournament_name'] = $tournament->name;
            if ($tournament->settings?->logo) {
                $sampleData['tournament_logo'] = $tournament->settings->logo;
            }

            // Render to base64
            $base64Image = $renderService->renderToBase64($template, $sampleData);

            return response()->json([
                'success' => true,
                'image' => $base64Image,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to render template: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download rendered template
     */
    public function download(Tournament $tournament, TournamentTemplate $template, Request $request)
    {
        abort_if($template->tournament_id !== $tournament->id, 404);

        try {
            $renderService = new TemplateRenderService();

            // Get custom data from request (supports both query params and nested 'data' array)
            $customData = $request->input('data', []);
            if (empty($customData)) {
                $customData = $request->only([
                    'player_name', 'jersey_name', 'jersey_number', 'team_name',
                    'team_a_name', 'team_b_name', 'team_a_score', 'team_b_score',
                    'match_date', 'match_time', 'venue', 'match_stage', 'result_summary',
                    'winner_name', 'man_of_the_match_name',
                ]);
            }
            $sampleData = $renderService->getSampleData($template->type, array_filter($customData));

            // Add tournament-specific data
            $sampleData['tournament_name'] = $tournament->name;
            if ($tournament->settings?->logo) {
                $sampleData['tournament_logo'] = $tournament->settings->logo;
            }

            // Render and save
            $filename = TemplateRenderService::posterFilename('template-' . $template->id);
            $path = $renderService->renderAndSave($template, $sampleData, $filename);

            $fullPath = Storage::disk('public')->path($path);

            return response()->download($fullPath, $filename, [
                'Content-Type' => 'image/png',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to generate template: ' . $e->getMessage());
        }
    }

    /**
     * Upload an overlay image for template
     */
    public function uploadOverlay(Tournament $tournament, Request $request)
    {
        $request->validate([
            'overlay_image' => 'required|file|mimes:png,jpg,jpeg,gif,svg,webp|max:5120',
        ]);

        $path = $request->file('overlay_image')
            ->store('tournament_templates/' . $tournament->id . '/overlays', 'public');

        return response()->json([
            'success' => true,
            'path' => $path,
            'url' => asset('storage/' . $path),
        ]);
    }

    /**
     * Delete an overlay image
     */
    public function deleteOverlay(Tournament $tournament, Request $request)
    {
        $request->validate([
            'path' => 'required|string',
        ]);

        $path = $request->input('path');

        // Security check: ensure the path belongs to this tournament
        if (! str_contains($path, 'tournament_templates/' . $tournament->id)) {
            return response()->json(['success' => false, 'error' => 'Invalid path'], 403);
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Save a base64 encoded image to storage
     */
    private function saveBase64Image(string $base64Data, int $tournamentId): string
    {
        // Remove data URI prefix if present
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $matches)) {
            $extension = $matches[1];
            $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
        } else {
            $extension = 'png';
        }

        // Decode the base64 data
        $imageData = base64_decode($base64Data);

        // Generate unique filename
        $filename = 'bg_' . time() . '_' . uniqid() . '.' . $extension;
        $path = 'tournament_templates/' . $tournamentId . '/' . $filename;

        // Save to storage
        Storage::disk('public')->put($path, $imageData);

        return $path;
    }

    /**
     * Generate fixtures poster (programmatic GD-based)
     */
    public function generateFixturesPoster(Tournament $tournament, Request $request)
    {
        $matchCount = (int) $request->input('fixture_count', 5);
        $theme = $request->input('theme', 'dark');

        // Clamp match count
        $matchCount = max(1, min($matchCount, 100));

        $service = new FixturesPosterService();
        $path = $service->generate($tournament, $matchCount, $theme);

        return response()->json([
            'success' => true,
            'image_url' => Storage::url($path),
            'image' => Storage::url($path),
            'path' => $path,
        ]);
    }
}

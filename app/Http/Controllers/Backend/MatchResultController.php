<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Ball;
use App\Models\Matches;
use App\Models\MatchAward;
use App\Models\MatchResult;
use App\Models\Player;
use App\Models\TournamentAward;
use App\Services\CricHeroes\CricHeroesScraper;
use App\Services\Tournament\PointTableService;
use App\Traits\CalculatesMatchBallStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MatchResultController extends Controller
{
    use CalculatesMatchBallStats;

    public function __construct(
        private readonly PointTableService $pointTableService
    ) {}

    public function edit(Matches $match): RedirectResponse
    {
        // Result entry now lives on the unified match-management page (Result tab).
        return redirect()->route('admin.matches.summary.edit', ['match' => $match, 'tab' => 'result']);
    }

    /**
     * Calculate match statistics from ball-by-ball data
     */
    // calculateBallStats() / calculateInningsStats() now live in
    // App\Traits\CalculatesMatchBallStats (shared with MatchSummaryController).

    public function update(Request $request, Matches $match): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['match.edit']);

        $validated = $request->validate([
            // Team A
            'team_a_score' => 'required|integer|min:0',
            'team_a_wickets' => 'required|integer|min:0|max:10',
            'team_a_overs' => 'required|numeric|min:0',
            'team_a_extras' => 'nullable|integer|min:0',

            // Team B
            'team_b_score' => 'required|integer|min:0',
            'team_b_wickets' => 'required|integer|min:0|max:10',
            'team_b_overs' => 'required|numeric|min:0',
            'team_b_extras' => 'nullable|integer|min:0',

            // Result
            'result_type' => 'required|in:runs,wickets,tie,no_result,super_over,dls',
            'winner_team_id' => 'nullable|exists:actual_teams,id',
            'margin' => 'nullable|integer|min:0',
            'result_summary' => 'nullable|string|max:500',

            // Toss
            'toss_won_by' => 'nullable|exists:actual_teams,id',
            'toss_decision' => 'nullable|in:bat,bowl',

            // Batting order (explicit toggle; overrides toss-derived value)
            'team_a_batting_first' => 'nullable|boolean',

            // Notes
            'match_notes' => 'nullable|string|max:2000',
        ]);

        // Validate winner_team_id based on result_type
        if (in_array($validated['result_type'], ['runs', 'wickets', 'super_over', 'dls'])) {
            if (empty($validated['winner_team_id'] ?? null)) {
                return redirect()->back()->withErrors(['winner_team_id' => 'Winner is required for this result type.']);
            }
        }

        // Batting order: use the explicit toggle if submitted, else derive from toss.
        if ($request->has('team_a_batting_first') && $request->input('team_a_batting_first') !== null) {
            $validated['team_a_batting_first'] = $request->boolean('team_a_batting_first');
        } else {
            $validated['team_a_batting_first'] = MatchResult::deriveTeamABattingFirst(
                $validated['toss_won_by'] ?? null,
                $validated['toss_decision'] ?? null,
                $match->team_a_id
            );
        }

        // Preserve existing scorecard_data when updating
        $existing = MatchResult::where('match_id', $match->id)->first();
        if ($existing?->scorecard_data) {
            $validated['scorecard_data'] = $existing->scorecard_data;
        }

        // Create or update result
        $result = MatchResult::updateOrCreate(
            ['match_id' => $match->id],
            $validated
        );

        // Generate result summary if not provided
        if (empty($validated['result_summary'])) {
            $result->update(['result_summary' => $result->generateResultSummary()]);
        }

        // Update match status and winner
        $match->update([
            'status' => 'completed',
            'winner_team_id' => $validated['winner_team_id'] ?? null,
        ]);

        // Clear cached summary poster so it regenerates with latest data
        if ($match->summary && $match->summary->summary_poster) {
            $match->summary->update(['summary_poster' => null]);
        }

        // Update point table if group stage match
        if ($match->isGroupStage()) {
            $this->pointTableService->updateFromMatchResult($match);
        }

        // Process award selections from CricHeroes import
        $this->processAwardSelections($request, $match);

        return redirect()->back()->with('success', __('Match result saved successfully.'));
    }

    public function quickUpdate(Request $request, Matches $match): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['match.edit']);

        $validated = $request->validate([
            'team_a_score' => 'required|string', // Format: "150/6 (18.2)"
            'team_b_score' => 'required|string',
            'winner' => 'nullable|in:team_a,team_b,tie,no_result',
        ]);

        // Parse scores
        $teamAData = $this->parseScore($validated['team_a_score']);
        $teamBData = $this->parseScore($validated['team_b_score']);

        // Determine result type and margin
        $resultType = 'runs';
        $margin = 0;
        $winnerId = null;

        if ($validated['winner'] === 'tie') {
            $resultType = 'tie';
        } elseif ($validated['winner'] === 'no_result') {
            $resultType = 'no_result';
        } elseif ($validated['winner'] === 'team_a') {
            $winnerId = $match->team_a_id;
            if ($teamBData['wickets'] < 10) {
                $resultType = 'wickets';
                $margin = 10 - $teamBData['wickets'];
            } else {
                $resultType = 'runs';
                $margin = $teamAData['runs'] - $teamBData['runs'];
            }
        } elseif ($validated['winner'] === 'team_b') {
            $winnerId = $match->team_b_id;
            if ($teamAData['wickets'] < 10) {
                $resultType = 'wickets';
                $margin = 10 - $teamAData['wickets'];
            } else {
                $resultType = 'runs';
                $margin = $teamBData['runs'] - $teamAData['runs'];
            }
        }

        $result = MatchResult::updateOrCreate(
            ['match_id' => $match->id],
            [
                'team_a_score' => $teamAData['runs'],
                'team_a_wickets' => $teamAData['wickets'],
                'team_a_overs' => $teamAData['overs'],
                'team_b_score' => $teamBData['runs'],
                'team_b_wickets' => $teamBData['wickets'],
                'team_b_overs' => $teamBData['overs'],
                'result_type' => $resultType,
                'winner_team_id' => $winnerId,
                'margin' => $margin,
            ]
        );

        $result->update(['result_summary' => $result->generateResultSummary()]);

        $match->update([
            'status' => 'completed',
            'winner_team_id' => $winnerId,
        ]);

        if ($match->isGroupStage()) {
            $this->pointTableService->updateFromMatchResult($match);
        }

        return redirect()->back()->with('success', __('Match result saved.'));
    }

    /**
     * Fetch scorecard data from CricHeroes via Browsershot scraping.
     */
    public function fetchCricHeroesData(Request $request, Matches $match): JsonResponse
    {
        $this->checkAuthorization(Auth::user(), ['match.edit']);

        $request->validate(['url' => 'required|url']);

        // Save the URL to the match for future reference
        $match->update(['cricheroes_match_url' => $request->url]);

        try {
            $scraper = new CricHeroesScraper();
            $data = $scraper->fetch($request->url);

            // Auto-save scorecard data if result already exists;
            // otherwise it will be saved when the result form is submitted
            $existingResult = MatchResult::where('match_id', $match->id)->first();
            if ($existingResult) {
                $scorecardData = $existingResult->scorecard_data;
                if (is_string($scorecardData)) {
                    $scorecardData = json_decode($scorecardData, true) ?? [];
                }
                $scorecardData = is_array($scorecardData) ? $scorecardData : [];

                if (!empty($data['scorecard'])) {
                    $scorecardData = $data['scorecard'];
                }

                // Store heroes data alongside scorecard
                if (!empty($data['heroes'])) {
                    if (is_array($scorecardData) && !isset($scorecardData[0])) {
                        $scorecardData['cricheroes_heroes'] = $data['heroes'];
                    } else {
                        // scorecard is innings array format, wrap it
                        $scorecardData = [
                            'innings' => $scorecardData,
                            'cricheroes_heroes' => $data['heroes'],
                        ];
                    }
                }

                $existingResult->update(['scorecard_data' => $scorecardData]);
            }

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * One-click sync: fetch scores from CricHeroes and save the full result.
     */
    public function syncCricHeroesScore(Matches $match): JsonResponse
    {
        $this->checkAuthorization(Auth::user(), ['match.edit']);

        if (!$match->cricheroes_match_url) {
            return response()->json([
                'success' => false,
                'message' => 'No CricHeroes URL saved for this match.',
            ], 422);
        }

        try {
            $scraper = new CricHeroesScraper();
            $data = $scraper->fetch($match->cricheroes_match_url);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch from CricHeroes: ' . $e->getMessage(),
            ], 500);
        }

        if (empty($data['teams']) || count($data['teams']) < 2) {
            return response()->json([
                'success' => false,
                'message' => 'Could not find team data on CricHeroes.',
            ], 422);
        }

        $result = $this->applyParsedScorecard($match, $data);

        return response()->json([
            'success' => true,
            'message' => 'Scores synced successfully from CricHeroes!',
            'data' => [
                'team_a_score' => $result->team_a_score . '/' . $result->team_a_wickets . ' (' . $result->team_a_overs . ')',
                'team_b_score' => $result->team_b_score . '/' . $result->team_b_wickets . ' (' . $result->team_b_overs . ')',
                'result_summary' => $result->result_summary,
            ],
        ]);
    }

    /**
     * Import a CricHeroes scorecard PDF: parse it (same canonical shape as the
     * scraper) and apply it to the match. Replaces the unreliable URL scraping.
     */
    public function importScorecardPdf(Request $request, Matches $match): JsonResponse
    {
        $this->checkAuthorization(Auth::user(), ['match.edit']);

        $request->validate([
            'scorecard_pdf' => 'required|file|mimes:pdf|max:20480',
            'swap_teams' => 'nullable|boolean',
        ]);

        try {
            $data = (new \App\Services\Scorecard\ScorecardPdfParser())
                ->parse($request->file('scorecard_pdf')->getRealPath());
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Could not parse the scorecard PDF: ' . $e->getMessage(),
            ], 422);
        }

        if (empty($data['teams']) || count($data['teams']) < 2) {
            return response()->json([
                'success' => false,
                'message' => 'Could not read both teams from the PDF. Is this a CricHeroes scorecard export?',
            ], 422);
        }

        $result = $this->applyParsedScorecard($match, $data, $request->boolean('swap_teams'));

        // Auto-assign Best Batsman / Best Bowler awards from the parsed performers.
        $this->assignAwardsFromParsedHeroes($match, $data['heroes'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Scorecard imported from PDF.',
            'data' => [
                'team_a_score' => $result->team_a_score . '/' . $result->team_a_wickets . ' (' . $result->team_a_overs . ')',
                'team_b_score' => $result->team_b_score . '/' . $result->team_b_wickets . ' (' . $result->team_b_overs . ')',
                'result_summary' => $result->result_summary,
                'parsed_teams' => array_map(
                    fn ($t) => $t['name'] . ' ' . $t['runs'] . '/' . $t['wickets'] . ' (' . $t['overs'] . ' ov)',
                    $data['teams']
                ),
                'toss' => $data['toss'],
                'best_batter' => $data['heroes']['best_batter'] ?? null,
                'best_bowler' => $data['heroes']['best_bowler'] ?? null,
            ],
        ]);
    }

    /**
     * Map a parsed scorecard array (the shape produced by CricHeroesScraper::fetch()
     * and ScorecardPdfParser::parse()) onto the match's MatchResult. $swapTeams
     * flips the team A/B assignment when the auto name-match guesses wrong.
     */
    private function applyParsedScorecard(Matches $match, array $data, bool $swapTeams = false): MatchResult
    {
        $match->load(['teamA', 'teamB']);
        $teamAName = $match->teamA->name ?? '';
        $teamBName = $match->teamB->name ?? '';

        $mapping = $this->mapCricHeroesTeams($data['teams'], $teamAName, $teamBName);
        if ($swapTeams) {
            $mapping = ['a' => $mapping['b'], 'b' => $mapping['a']];
        }
        $teamAData = $data['teams'][$mapping['a']];
        $teamBData = $data['teams'][$mapping['b']];

        // Resolve a parsed team name to the local team id — via the chosen team
        // mapping first (reliable even when local names differ from the PDF),
        // then by fuzzy-matching the local names as a fallback.
        $resolveTeamId = function (?string $name) use ($data, $mapping, $match, $teamAName, $teamBName) {
            if (!$name) {
                return null;
            }
            foreach ($data['teams'] as $i => $t) {
                if ($this->fuzzyMatch($name, $t['name'] ?? '')) {
                    return $i === $mapping['a'] ? $match->team_a_id : $match->team_b_id;
                }
            }
            if ($this->fuzzyMatch($name, $teamAName)) {
                return $match->team_a_id;
            }
            if ($this->fuzzyMatch($name, $teamBName)) {
                return $match->team_b_id;
            }

            return null;
        };

        // Toss
        $tossDecision = $data['toss']['decision'] ?? null;
        $tossWonBy = $resolveTeamId($data['toss']['winner'] ?? null);

        // Result
        $resultType = $data['result']['type'] ?? 'runs';
        $margin = $data['result']['margin'] ?? 0;
        $pdfSummary = $data['result']['summary'] ?? null;
        $winnerId = $resultType === 'tie' ? null : $resolveTeamId($data['result']['winner'] ?? null);

        $resultData = [
            'team_a_score' => $teamAData['runs'],
            'team_a_wickets' => $teamAData['wickets'],
            'team_a_overs' => $teamAData['overs'],
            'team_a_extras' => $teamAData['extras'] ?? null,
            'team_b_score' => $teamBData['runs'],
            'team_b_wickets' => $teamBData['wickets'],
            'team_b_overs' => $teamBData['overs'],
            'team_b_extras' => $teamBData['extras'] ?? null,
            'toss_won_by' => $tossWonBy,
            'toss_decision' => $tossDecision,
            'team_a_batting_first' => MatchResult::deriveTeamABattingFirst($tossWonBy, $tossDecision, $match->team_a_id),
            'result_type' => $resultType,
            'winner_team_id' => $winnerId,
            'margin' => $margin,
        ];

        if (!empty($data['scorecard'])) {
            $scorecardData = $data['scorecard'];
            if (!empty($data['heroes'])) {
                if (is_array($scorecardData) && !isset($scorecardData['innings'])) {
                    $scorecardData = ['innings' => $scorecardData, 'cricheroes_heroes' => $data['heroes']];
                } else {
                    $scorecardData['cricheroes_heroes'] = $data['heroes'];
                }
            }
            $resultData['scorecard_data'] = $scorecardData;
        }

        $result = MatchResult::updateOrCreate(['match_id' => $match->id], $resultData);

        // Prefer a summary built from the local team names; fall back to the PDF's
        // text when the winner couldn't be mapped to a local team.
        $generated = $result->generateResultSummary();
        $result->update(['result_summary' => $generated ?: $pdfSummary]);

        if ($winnerId || $resultType === 'tie') {
            $match->update(['status' => 'completed', 'winner_team_id' => $winnerId]);
            if ($match->isGroupStage()) {
                $this->pointTableService->updateFromMatchResult($match);
            }
        }

        return $result;
    }

    /**
     * Create Best Batsman / Best Bowler (and POTM if present) match awards from
     * the parsed scorecard's best performers. Each winner is matched to a roster
     * player by name; if not found, the name is stored as a custom award winner.
     */
    private function assignAwardsFromParsedHeroes(Matches $match, ?array $heroes): void
    {
        if (empty($heroes)) {
            return;
        }
        $tournament = $match->tournament;
        if (!$tournament) {
            return;
        }

        $awards = $tournament->awards()->matchLevel()->active()->get();
        if ($awards->isEmpty()) {
            foreach ([['name' => 'Best Batsman', 'icon' => "\xF0\x9F\x8F\x8F", 'order' => 2], ['name' => 'Best Bowler', 'icon' => "\xF0\x9F\x8E\xAF", 'order' => 3]] as $a) {
                TournamentAward::create([
                    'tournament_id' => $tournament->id,
                    'name' => $a['name'],
                    'icon' => $a['icon'],
                    'is_match_level' => true,
                    'is_active' => true,
                    'order' => $a['order'],
                    'template_settings' => TournamentAward::getDefaultTemplateSettings($a['name']),
                ]);
            }
            $awards = $tournament->awards()->matchLevel()->active()->get();
        }

        $match->loadMissing(['teamA.players.player', 'teamB.players.player']);
        $players = collect();
        foreach (['teamA', 'teamB'] as $rel) {
            if ($match->$rel) {
                $players = $players->merge($match->$rel->players->pluck('player')->filter());
            }
        }

        $map = [
            'best_batter' => ['slugs' => ['best-batsman'], 'remarks' => fn ($h) => ($h['runs'] ?? 0) . ' (' . ($h['balls'] ?? 0) . '), ' . ($h['fours'] ?? 0) . 'x4, ' . ($h['sixes'] ?? 0) . 'x6'],
            'best_bowler' => ['slugs' => ['best-bowler'], 'remarks' => fn ($h) => ($h['wickets'] ?? 0) . '/' . ($h['runs'] ?? 0) . ' (' . ($h['overs'] ?? '') . ' ov)'],
            'player_of_the_match' => ['slugs' => ['man-of-the-match', 'player-of-the-match'], 'remarks' => fn ($h) => ''],
        ];

        foreach ($map as $key => $cfg) {
            $hero = $heroes[$key] ?? null;
            if (empty($hero['name'])) {
                continue;
            }
            $award = $awards->first(fn ($a) => in_array($a->slug, $cfg['slugs']));
            if (!$award) {
                continue;
            }
            $player = $players->first(fn ($p) => $p && ($this->fuzzyMatch($p->name, $hero['name'])
                || ($p->jersey_name && $this->fuzzyMatch($p->jersey_name, $hero['name']))));

            $match->matchAwards()->updateOrCreate(
                ['tournament_award_id' => $award->id],
                [
                    'player_id' => $player?->id,
                    'custom_player_name' => $player ? null : $hero['name'],
                    'remarks' => ($cfg['remarks'])($hero),
                ]
            );
        }
    }

    private function mapCricHeroesTeams(array $teams, string $teamAName, string $teamBName): array
    {
        $aIdx = null;
        $bIdx = null;

        for ($i = 0; $i < count($teams); $i++) {
            if ($this->fuzzyMatch($teams[$i]['name'], $teamAName)) $aIdx = $i;
            if ($this->fuzzyMatch($teams[$i]['name'], $teamBName)) $bIdx = $i;
        }

        if ($aIdx === null && $bIdx === null) {
            $aIdx = 0;
            $bIdx = 1;
        } elseif ($aIdx === null) {
            $aIdx = $bIdx === 0 ? 1 : 0;
        } elseif ($bIdx === null) {
            $bIdx = $aIdx === 0 ? 1 : 0;
        }

        return ['a' => $aIdx, 'b' => $bIdx];
    }

    private function fuzzyMatch(string $str1, string $str2): bool
    {
        $a = strtolower(trim($str1));
        $b = strtolower(trim($str2));

        return $a === $b || str_contains($a, $b) || str_contains($b, $a);
    }

    /**
     * Process award selections submitted alongside the match result.
     */
    private function processAwardSelections(Request $request, Matches $match): void
    {
        $awardMap = [
            'award_motm_name' => ['slugs' => ['man-of-the-match', 'player-of-the-match'], 'name' => 'Man of the Match', 'icon' => "\xF0\x9F\x8F\x86", 'order' => 1],
            'award_best_batter_name' => ['slugs' => ['best-batsman'], 'name' => 'Best Batsman', 'icon' => "\xF0\x9F\x8F\x8F", 'order' => 2],
            'award_best_bowler_name' => ['slugs' => ['best-bowler'], 'name' => 'Best Bowler', 'icon' => "\xF0\x9F\x8E\xAF", 'order' => 3],
        ];

        $hasAnyAward = false;
        foreach (array_keys($awardMap) as $field) {
            if ($request->filled($field)) {
                $hasAnyAward = true;
                break;
            }
        }
        if (!$hasAnyAward && !$request->has('extra_awards')) {
            return;
        }

        $match->load(['teamA.players.player', 'teamB.players.player']);
        $tournament = $match->tournament;

        // Collect all players from both teams
        $allPlayers = collect();
        if ($match->teamA) {
            $allPlayers = $allPlayers->merge($match->teamA->players->pluck('player')->filter());
        }
        if ($match->teamB) {
            $allPlayers = $allPlayers->merge($match->teamB->players->pluck('player')->filter());
        }

        // Ensure tournament has default awards
        $tournamentAwards = $tournament->awards()->matchLevel()->active()->get();
        if ($tournamentAwards->isEmpty()) {
            foreach ($awardMap as $info) {
                TournamentAward::create([
                    'tournament_id' => $tournament->id,
                    'name' => $info['name'],
                    'icon' => $info['icon'],
                    'is_match_level' => true,
                    'is_active' => true,
                    'order' => $info['order'],
                    'template_settings' => TournamentAward::getDefaultTemplateSettings($info['name']),
                ]);
            }
            $tournamentAwards = $tournament->awards()->matchLevel()->active()->get();
        }

        foreach ($awardMap as $field => $info) {
            $playerName = $request->input($field);
            if (!$playerName) {
                continue;
            }

            $slugs = $info['slugs'];

            // Find matching tournament award by slug
            $tournamentAward = $tournamentAwards->first(function ($a) use ($slugs) {
                return in_array($a->slug, $slugs);
            });
            if (!$tournamentAward) {
                continue;
            }

            // Skip if this award type is already assigned for this match
            $exists = $match->matchAwards()
                ->where('tournament_award_id', $tournamentAward->id)
                ->exists();
            if ($exists) {
                continue;
            }

            // Fuzzy-match player name to a player in the teams
            $player = $allPlayers->first(function ($p) use ($playerName) {
                return $this->fuzzyMatch($p->name, $playerName)
                    || ($p->jersey_name && $this->fuzzyMatch($p->jersey_name, $playerName));
            });

            if (!$player) {
                // Create new Player from CricHeroes data
                $player = Player::create([
                    'name' => $playerName,
                    'status' => 'approved',
                ]);

                // Try to download image from CricHeroes scorecard data
                $imageUrl = $this->findPlayerImageFromScorecard($match, $playerName);
                if ($imageUrl) {
                    $imagePath = CricHeroesScraper::downloadPlayerImage($imageUrl, $playerName);
                    if ($imagePath) {
                        $player->update(['image_path' => $imagePath]);
                    }
                }

                // Determine team from scorecard data and attach
                $teamId = $this->resolveTeamFromScorecard($match, $playerName);
                if ($teamId) {
                    $player->actualTeamAssignments()->attach($teamId, [
                        'tournament_id' => $tournament->id,
                    ]);
                }
            }

            $remarks = $this->buildRemarksFromScorecard($match, $playerName, $field);

            $match->matchAwards()->create([
                'tournament_award_id' => $tournamentAward->id,
                'player_id' => $player->id,
                'custom_player_name' => $playerName,
                'remarks' => $remarks,
            ]);
        }

        // Process extra/custom awards
        $extraAwards = $request->input('extra_awards', []);
        if (!is_array($extraAwards)) {
            return;
        }

        $maxOrder = $tournamentAwards->max('order') ?? 5;
        foreach ($extraAwards as $extra) {
            $awardName = trim($extra['name'] ?? '');
            $playerName = trim($extra['player'] ?? '');
            if (!$awardName || !$playerName) {
                continue;
            }

            // Find or create the tournament award by name
            $tournamentAward = $tournamentAwards->first(function ($a) use ($awardName) {
                return $this->fuzzyMatch($a->name, $awardName);
            });
            if (!$tournamentAward) {
                $maxOrder++;
                $tournamentAward = TournamentAward::create([
                    'tournament_id' => $tournament->id,
                    'name' => $awardName,
                    'icon' => '',
                    'is_match_level' => true,
                    'is_active' => true,
                    'order' => $maxOrder,
                    'template_settings' => TournamentAward::getDefaultTemplateSettings($awardName),
                ]);
                $tournamentAwards->push($tournamentAward);
            }

            // Skip if already assigned
            $exists = $match->matchAwards()
                ->where('tournament_award_id', $tournamentAward->id)
                ->exists();
            if ($exists) {
                continue;
            }

            $player = $allPlayers->first(function ($p) use ($playerName) {
                return $this->fuzzyMatch($p->name, $playerName)
                    || ($p->jersey_name && $this->fuzzyMatch($p->jersey_name, $playerName));
            });

            if (!$player) {
                $player = Player::create([
                    'name' => $playerName,
                    'status' => 'approved',
                ]);

                $imageUrl = $this->findPlayerImageFromScorecard($match, $playerName);
                if ($imageUrl) {
                    $imagePath = CricHeroesScraper::downloadPlayerImage($imageUrl, $playerName);
                    if ($imagePath) {
                        $player->update(['image_path' => $imagePath]);
                    }
                }

                $teamId = $this->resolveTeamFromScorecard($match, $playerName);
                if ($teamId) {
                    $player->actualTeamAssignments()->attach($teamId, [
                        'tournament_id' => $tournament->id,
                    ]);
                }
            }

            $match->matchAwards()->create([
                'tournament_award_id' => $tournamentAward->id,
                'player_id' => $player->id,
                'custom_player_name' => $playerName,
            ]);
        }
    }

    /**
     * Clear imported CricHeroes scorecard data for a match.
     */
    public function clearScorecardData(Matches $match): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['match.edit']);

        if ($match->result) {
            $match->result->update(['scorecard_data' => null]);
        }

        return redirect()->back()->with('success', __('Scorecard data cleared successfully.'));
    }

    /**
     * Find a player's image URL from the stored scorecard data.
     */
    private function findPlayerImageFromScorecard(Matches $match, string $playerName): ?string
    {
        $scorecard = $match->result?->scorecard_data;
        if (!$scorecard) return null;

        if (is_string($scorecard)) {
            $scorecard = json_decode($scorecard, true) ?? [];
        }

        $innings = $scorecard['innings'] ?? $scorecard;
        if (!is_array($innings)) return null;

        foreach ($innings as $inn) {
            if (!is_array($inn)) continue;
            foreach (['batting', 'bowling'] as $type) {
                foreach ($inn[$type] ?? [] as $entry) {
                    if ($this->fuzzyMatch($entry['name'] ?? '', $playerName) && !empty($entry['image_url'])) {
                        return $entry['image_url'];
                    }
                }
            }
        }
        return null;
    }

    /**
     * Resolve a player's team ID from scorecard data.
     */
    private function resolveTeamFromScorecard(Matches $match, string $playerName): ?int
    {
        $scorecard = $match->result?->scorecard_data;
        if (!$scorecard) return null;

        if (is_string($scorecard)) {
            $scorecard = json_decode($scorecard, true) ?? [];
        }

        $innings = $scorecard['innings'] ?? $scorecard;
        if (!is_array($innings)) return null;

        foreach ($innings as $inn) {
            if (!is_array($inn)) continue;
            $teamName = $inn['team_name'] ?? '';
            foreach (['batting', 'bowling'] as $type) {
                foreach ($inn[$type] ?? [] as $entry) {
                    if ($this->fuzzyMatch($entry['name'] ?? '', $playerName)) {
                        if ($match->teamA && $this->fuzzyMatch($match->teamA->name, $teamName)) {
                            return $match->team_a_id;
                        }
                        if ($match->teamB && $this->fuzzyMatch($match->teamB->name, $teamName)) {
                            return $match->team_b_id;
                        }
                    }
                }
            }
        }
        return null;
    }

    /**
     * Build remarks from scorecard stats for an award.
     */
    private function buildRemarksFromScorecard(Matches $match, string $playerName, string $awardField): ?string
    {
        $scorecard = $match->result?->scorecard_data;
        if (!$scorecard) return null;

        if (is_string($scorecard)) {
            $scorecard = json_decode($scorecard, true) ?? [];
        }

        // Also check heroes data for pre-built stats
        $heroes = $scorecard['cricheroes_heroes'] ?? null;

        if ($awardField === 'award_best_batter_name' && $heroes && !empty($heroes['best_batter'])) {
            $bat = $heroes['best_batter'];
            $parts = [];
            if (!empty($bat['runs'])) $parts[] = $bat['runs'] . ' runs';
            if (!empty($bat['balls'])) $parts[] = $bat['balls'] . ' balls';
            if (!empty($bat['fours'])) $parts[] = $bat['fours'] . 'x4';
            if (!empty($bat['sixes'])) $parts[] = $bat['sixes'] . 'x6';
            return !empty($parts) ? implode(', ', $parts) : null;
        }

        if ($awardField === 'award_best_bowler_name' && $heroes && !empty($heroes['best_bowler'])) {
            $bowl = $heroes['best_bowler'];
            $parts = [];
            if (!empty($bowl['wickets'])) $parts[] = $bowl['wickets'] . '/' . ($bowl['runs'] ?? 0);
            if (!empty($bowl['overs'])) $parts[] = '(' . $bowl['overs'] . ' ov)';
            return !empty($parts) ? implode(' ', $parts) : null;
        }

        // Fallback: search scorecard batting/bowling for this player's stats
        $innings = $scorecard['innings'] ?? $scorecard;
        if (!is_array($innings)) return null;

        foreach ($innings as $inn) {
            if (!is_array($inn)) continue;
            // Check batting
            foreach ($inn['batting'] ?? [] as $entry) {
                if ($this->fuzzyMatch($entry['name'] ?? '', $playerName) && !empty($entry['runs'])) {
                    $parts = [$entry['runs'] . ' runs'];
                    if (!empty($entry['balls'])) $parts[] = $entry['balls'] . ' balls';
                    if (!empty($entry['fours'])) $parts[] = $entry['fours'] . 'x4';
                    if (!empty($entry['sixes'])) $parts[] = $entry['sixes'] . 'x6';
                    return implode(', ', $parts);
                }
            }
            // Check bowling
            foreach ($inn['bowling'] ?? [] as $entry) {
                if ($this->fuzzyMatch($entry['name'] ?? '', $playerName) && !empty($entry['wickets'])) {
                    $parts = [$entry['wickets'] . '/' . ($entry['runs'] ?? 0)];
                    if (!empty($entry['overs'])) $parts[] = '(' . $entry['overs'] . ' ov)';
                    return implode(' ', $parts);
                }
            }
        }

        return null;
    }

    /**
     * Parse score string like "150/6 (18.2)" into components
     */
    private function parseScore(string $score): array
    {
        $runs = 0;
        $wickets = 0;
        $overs = 0;

        // Match pattern: "150/6 (18.2)" or "150/6" or "150"
        if (preg_match('/(\d+)\/(\d+)\s*\(?([\d.]+)?\)?/', $score, $matches)) {
            $runs = (int) $matches[1];
            $wickets = (int) $matches[2];
            $overs = isset($matches[3]) ? (float) $matches[3] : 0;
        } elseif (preg_match('/(\d+)/', $score, $matches)) {
            $runs = (int) $matches[1];
        }

        return [
            'runs' => $runs,
            'wickets' => $wickets,
            'overs' => $overs,
        ];
    }
}

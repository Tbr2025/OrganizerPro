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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MatchResultController extends Controller
{
    public function __construct(
        private readonly PointTableService $pointTableService
    ) {}

    public function edit(Matches $match): View
    {
        $this->checkAuthorization(Auth::user(), ['match.edit']);

        $match->load(['tournament', 'teamA', 'teamB', 'result', 'matchAwards.player', 'matchAwards.tournamentAward']);

        $result = $match->result ?? new MatchResult();

        // Auto-calculate scores from ball data
        $ballStats = $this->calculateBallStats($match);

        // If we have ball data from BOTH innings and result is empty, pre-fill from ball stats
        if ($ballStats['hasBallData'] && $ballStats['bothInningsComplete'] && !$match->result) {
            $result->team_a_score = $ballStats['teamA']['runs'];
            $result->team_a_wickets = $ballStats['teamA']['wickets'];
            $result->team_a_overs = $ballStats['teamA']['overs'];
            $result->team_a_extras = $ballStats['teamA']['extras'];

            $result->team_b_score = $ballStats['teamB']['runs'];
            $result->team_b_wickets = $ballStats['teamB']['wickets'];
            $result->team_b_overs = $ballStats['teamB']['overs'];
            $result->team_b_extras = $ballStats['teamB']['extras'];

            // Auto-determine winner
            if ($ballStats['teamA']['runs'] > $ballStats['teamB']['runs']) {
                $result->winner_team_id = $match->team_a_id;
                $result->result_type = 'runs';
                $result->margin = $ballStats['teamA']['runs'] - $ballStats['teamB']['runs'];
            } elseif ($ballStats['teamB']['runs'] > $ballStats['teamA']['runs']) {
                $result->winner_team_id = $match->team_b_id;
                $result->result_type = 'wickets';
                $result->margin = 10 - $ballStats['teamB']['wickets'];
            } else {
                $result->result_type = 'tie';
            }
        }
        // If only first innings is complete, pre-fill Team A data only
        elseif ($ballStats['hasBallData'] && $ballStats['firstInningsComplete'] && !$ballStats['secondInningsStarted'] && !$match->result) {
            $result->team_a_score = $ballStats['teamA']['runs'];
            $result->team_a_wickets = $ballStats['teamA']['wickets'];
            $result->team_a_overs = $ballStats['teamA']['overs'];
            $result->team_a_extras = $ballStats['teamA']['extras'];
        }

        return view('backend.pages.matches.result.edit', [
            'match' => $match,
            'result' => $result,
            'ballStats' => $ballStats,
            'breadcrumbs' => [
                'title' => __('Match Result'),
                'items' => [
                    ['label' => __('Matches'), 'url' => route('admin.matches.index')],
                    ['label' => $match->name, 'url' => route('admin.matches.show', $match)],
                ],
            ],
        ]);
    }

    /**
     * Calculate match statistics from ball-by-ball data
     */
    private function calculateBallStats(Matches $match): array
    {
        $balls = Ball::where('match_id', $match->id)
            ->orderBy('over')
            ->orderBy('ball_in_over')
            ->get();

        if ($balls->isEmpty()) {
            return [
                'hasBallData' => false,
                'bothInningsComplete' => false,
                'firstInningsComplete' => false,
                'teamA' => ['runs' => 0, 'wickets' => 0, 'overs' => 0, 'extras' => 0],
                'teamB' => ['runs' => 0, 'wickets' => 0, 'overs' => 0, 'extras' => 0],
            ];
        }

        // Get team A player IDs (batting team for first innings)
        $teamAPlayerIds = $match->teamA?->players?->pluck('id')->toArray() ?? [];
        $teamBPlayerIds = $match->teamB?->players?->pluck('id')->toArray() ?? [];

        // Calculate Team A stats (first innings - Team A batting)
        $teamABalls = $balls->filter(fn($b) => in_array($b->batsman_id, $teamAPlayerIds));
        $teamAStats = $this->calculateInningsStats($teamABalls);

        // Calculate Team B stats (second innings - Team B batting)
        $teamBBalls = $balls->filter(fn($b) => in_array($b->batsman_id, $teamBPlayerIds));
        $teamBStats = $this->calculateInningsStats($teamBBalls);

        // Check if first innings is complete (Team A batted)
        $firstInningsComplete = $teamABalls->isNotEmpty();

        // Check if second innings has started (Team B batted)
        $secondInningsStarted = $teamBBalls->isNotEmpty();

        // Both innings complete if both teams have batted
        $bothInningsComplete = $firstInningsComplete && $secondInningsStarted;

        return [
            'hasBallData' => true,
            'bothInningsComplete' => $bothInningsComplete,
            'firstInningsComplete' => $firstInningsComplete,
            'secondInningsStarted' => $secondInningsStarted,
            'teamA' => $teamAStats,
            'teamB' => $teamBStats,
        ];
    }

    /**
     * Calculate innings statistics from balls collection
     */
    private function calculateInningsStats($balls): array
    {
        if ($balls->isEmpty()) {
            return ['runs' => 0, 'wickets' => 0, 'overs' => 0, 'extras' => 0];
        }

        $totalRuns = $balls->sum('runs') + $balls->sum('extra_runs');
        $totalWickets = $balls->where('is_wicket', 1)->count();
        $totalExtras = $balls->sum('extra_runs');

        // Calculate overs (legal deliveries only)
        $legalBalls = $balls->filter(fn($b) => !in_array($b->extra_type, ['wide', 'no_ball']))->count();
        $completedOvers = floor($legalBalls / 6);
        $ballsInOver = $legalBalls % 6;
        $overs = $completedOvers + ($ballsInOver / 10); // Format: 10.3 means 10 overs 3 balls

        return [
            'runs' => $totalRuns,
            'wickets' => $totalWickets,
            'overs' => round($overs, 1),
            'extras' => $totalExtras,
        ];
    }

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

            // Notes
            'match_notes' => 'nullable|string|max:2000',
        ]);

        // Validate winner_team_id based on result_type
        if (in_array($validated['result_type'], ['runs', 'wickets', 'super_over', 'dls'])) {
            if (empty($validated['winner_team_id'] ?? null)) {
                return redirect()->back()->withErrors(['winner_team_id' => 'Winner is required for this result type.']);
            }
        }

        // Auto-derive batting order from toss data
        $validated['team_a_batting_first'] = MatchResult::deriveTeamABattingFirst(
            $validated['toss_won_by'] ?? null,
            $validated['toss_decision'] ?? null,
            $match->team_a_id
        );

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

        $match->load(['teamA', 'teamB']);
        $teamAName = $match->teamA->name ?? '';
        $teamBName = $match->teamB->name ?? '';

        // Map CricHeroes teams to our teams using fuzzy matching
        $mapping = $this->mapCricHeroesTeams($data['teams'], $teamAName, $teamBName);
        $teamAData = $data['teams'][$mapping['a']];
        $teamBData = $data['teams'][$mapping['b']];

        // Determine toss data
        $tossWonBy = null;
        $tossDecision = null;
        if (!empty($data['toss'])) {
            $tossDecision = $data['toss']['decision'] ?? null;
            $tossWinnerName = $data['toss']['winner'] ?? '';
            if ($this->fuzzyMatch($tossWinnerName, $teamAName)) {
                $tossWonBy = $match->team_a_id;
            } elseif ($this->fuzzyMatch($tossWinnerName, $teamBName)) {
                $tossWonBy = $match->team_b_id;
            }
        }

        // Determine result
        $resultType = 'runs';
        $margin = 0;
        $winnerId = null;
        $resultSummary = null;

        if (!empty($data['result'])) {
            $r = $data['result'];
            $resultType = $r['type'] ?? 'runs';
            $margin = $r['margin'] ?? 0;
            $resultSummary = $r['summary'] ?? null;

            if ($resultType === 'tie') {
                $winnerId = null;
            } elseif (!empty($r['winner'])) {
                if ($this->fuzzyMatch($r['winner'], $teamAName)) {
                    $winnerId = $match->team_a_id;
                } elseif ($this->fuzzyMatch($r['winner'], $teamBName)) {
                    $winnerId = $match->team_b_id;
                }
            }
        }

        $resultData = [
            'team_a_score' => $teamAData['runs'],
            'team_a_wickets' => $teamAData['wickets'],
            'team_a_overs' => $teamAData['overs'],
            'team_b_score' => $teamBData['runs'],
            'team_b_wickets' => $teamBData['wickets'],
            'team_b_overs' => $teamBData['overs'],
            'toss_won_by' => $tossWonBy,
            'toss_decision' => $tossDecision,
            'team_a_batting_first' => MatchResult::deriveTeamABattingFirst($tossWonBy, $tossDecision, $match->team_a_id),
            'result_type' => $resultType,
            'winner_team_id' => $winnerId,
            'margin' => $margin,
            'result_summary' => $resultSummary,
        ];

        if (!empty($data['scorecard'])) {
            $scorecardData = $data['scorecard'];
            // Include heroes data in scorecard_data
            if (!empty($data['heroes'])) {
                if (is_array($scorecardData) && !isset($scorecardData['innings'])) {
                    $scorecardData = ['innings' => $scorecardData, 'cricheroes_heroes' => $data['heroes']];
                } else {
                    $scorecardData['cricheroes_heroes'] = $data['heroes'];
                }
            }
            $resultData['scorecard_data'] = $scorecardData;
        }

        $result = MatchResult::updateOrCreate(
            ['match_id' => $match->id],
            $resultData
        );

        // Generate summary if not provided by CricHeroes
        if (empty($resultSummary)) {
            $result->update(['result_summary' => $result->generateResultSummary()]);
        }

        // Update match status
        if ($winnerId || $resultType === 'tie') {
            $match->update([
                'status' => 'completed',
                'winner_team_id' => $winnerId,
            ]);

            if ($match->isGroupStage()) {
                $this->pointTableService->updateFromMatchResult($match);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Scores synced successfully from CricHeroes!',
            'data' => [
                'team_a_score' => $teamAData['runs'] . '/' . $teamAData['wickets'] . ' (' . $teamAData['overs'] . ')',
                'team_b_score' => $teamBData['runs'] . '/' . $teamBData['wickets'] . ' (' . $teamBData['overs'] . ')',
                'result_summary' => $result->result_summary,
            ],
        ]);
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
                continue;
            }

            $match->matchAwards()->create([
                'tournament_award_id' => $tournamentAward->id,
                'player_id' => $player->id,
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
                continue;
            }

            $match->matchAwards()->create([
                'tournament_award_id' => $tournamentAward->id,
                'player_id' => $player->id,
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

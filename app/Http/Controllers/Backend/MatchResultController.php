<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Ball;
use App\Models\Matches;
use App\Models\MatchResult;
use App\Services\Tournament\PointTableService;
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
            if (!$validated['winner_team_id']) {
                return redirect()->back()->withErrors(['winner_team_id' => 'Winner is required for this result type.']);
            }
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
            'winner_team_id' => $validated['winner_team_id'],
        ]);

        // Update point table if group stage match
        if ($match->isGroupStage()) {
            $this->pointTableService->updateFromMatchResult($match);
        }

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

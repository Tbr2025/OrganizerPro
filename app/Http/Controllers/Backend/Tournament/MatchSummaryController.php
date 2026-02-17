<?php

namespace App\Http\Controllers\Backend\Tournament;

use App\Http\Controllers\Controller;
use App\Models\Ball;
use App\Models\Matches;
use App\Models\MatchSummary;
use App\Models\MatchResult;
use App\Models\MatchAward;
use App\Models\Player;
use App\Models\TournamentAward;
use App\Services\Poster\MatchSummaryPosterService;
use App\Services\Notification\TournamentNotificationService;
use App\Services\Tournament\PlayerStatisticService;
use App\Services\Tournament\PointTableService;
use Illuminate\Http\Request;

class MatchSummaryController extends Controller
{
    protected MatchSummaryPosterService $posterService;
    protected TournamentNotificationService $notificationService;
    protected PlayerStatisticService $playerStatisticService;
    protected PointTableService $pointTableService;

    public function __construct(
        MatchSummaryPosterService $posterService,
        TournamentNotificationService $notificationService,
        PlayerStatisticService $playerStatisticService,
        PointTableService $pointTableService
    ) {
        $this->posterService = $posterService;
        $this->notificationService = $notificationService;
        $this->playerStatisticService = $playerStatisticService;
        $this->pointTableService = $pointTableService;
    }

    /**
     * Show summary editor
     */
    public function edit(Matches $match)
    {
        $match->load(['teamA.players', 'teamB.players', 'result', 'matchAwards.player', 'matchAwards.tournamentAward']);

        // Auto-create result from ball data if both innings are complete
        if (!$match->result) {
            $this->autoCreateResultFromBalls($match);
            $match->refresh();
        }

        $summary = $match->getOrCreateSummary();
        $tournament = $match->tournament;
        $awards = $match->matchAwards()->with('player', 'tournamentAward')->get();
        $tournamentAwards = $tournament->awards()->matchLevel()->active()->get();

        // Get players from winning team only for award assignment
        $players = collect();
        $winnerTeam = null;

        if ($match->winner_team_id) {
            // Only show winning team players
            if ($match->winner_team_id === $match->team_a_id && $match->teamA) {
                $winnerTeam = $match->teamA;
            } elseif ($match->winner_team_id === $match->team_b_id && $match->teamB) {
                $winnerTeam = $match->teamB;
            }

            if ($winnerTeam) {
                $players = $winnerTeam->users->pluck('player')->filter();
            }
        } else {
            // If no winner yet (tie or incomplete), show all players
            if ($match->teamA) {
                $players = $players->merge($match->teamA->users->pluck('player')->filter());
            }
            if ($match->teamB) {
                $players = $players->merge($match->teamB->users->pluck('player')->filter());
            }
        }

        return view('backend.pages.matches.summary-editor', compact(
            'match',
            'summary',
            'tournament',
            'awards',
            'tournamentAwards',
            'players',
            'winnerTeam'
        ));
    }

    /**
     * Auto-create match result from ball-by-ball data
     */
    private function autoCreateResultFromBalls(Matches $match): void
    {
        // Get team player IDs
        $teamAPlayerIds = $match->teamA?->players?->pluck('id')->toArray() ?? [];
        $teamBPlayerIds = $match->teamB?->players?->pluck('id')->toArray() ?? [];

        // Get all balls
        $allBalls = Ball::where('match_id', $match->id)->get();

        if ($allBalls->isEmpty()) {
            return;
        }

        // Separate balls by innings
        $innings1Balls = $allBalls->filter(fn($b) => in_array($b->batsman_id, $teamAPlayerIds));
        $innings2Balls = $allBalls->filter(fn($b) => in_array($b->batsman_id, $teamBPlayerIds));

        // Need both innings to have been played
        if ($innings1Balls->isEmpty() || $innings2Balls->isEmpty()) {
            return;
        }

        // Calculate stats for both innings
        $teamAStats = $this->calculateInningsStats($innings1Balls);
        $teamBStats = $this->calculateInningsStats($innings2Balls);

        // Determine winner
        $winnerId = null;
        $resultType = 'runs';
        $margin = 0;

        if ($teamAStats['runs'] > $teamBStats['runs']) {
            $winnerId = $match->team_a_id;
            $resultType = 'runs';
            $margin = $teamAStats['runs'] - $teamBStats['runs'];
        } elseif ($teamBStats['runs'] > $teamAStats['runs']) {
            $winnerId = $match->team_b_id;
            $resultType = 'wickets';
            $margin = 10 - $teamBStats['wickets'];
        } else {
            $resultType = 'tie';
        }

        // Create match result
        $result = MatchResult::create([
            'match_id' => $match->id,
            'team_a_score' => $teamAStats['runs'],
            'team_a_wickets' => $teamAStats['wickets'],
            'team_a_overs' => $teamAStats['overs'],
            'team_a_extras' => $teamAStats['extras'],
            'team_b_score' => $teamBStats['runs'],
            'team_b_wickets' => $teamBStats['wickets'],
            'team_b_overs' => $teamBStats['overs'],
            'team_b_extras' => $teamBStats['extras'],
            'result_type' => $resultType,
            'winner_team_id' => $winnerId,
            'margin' => $margin,
        ]);

        // Generate result summary
        $result->update(['result_summary' => $result->generateResultSummary()]);

        // Update match status
        $match->update([
            'status' => 'completed',
            'winner_team_id' => $winnerId,
        ]);

        // Update player statistics
        $this->playerStatisticService->updateFromMatch($match);

        // Update point table
        $this->pointTableService->updateFromMatchResult($match);
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
        $overs = $completedOvers + ($ballsInOver / 10);

        return [
            'runs' => $totalRuns,
            'wickets' => $totalWickets,
            'overs' => round($overs, 1),
            'extras' => $totalExtras,
        ];
    }

    /**
     * Update summary
     */
    public function update(Matches $match, Request $request)
    {
        $validated = $request->validate([
            'highlights' => 'nullable|array',
            'highlights.*' => 'string|max:500',
            'commentary' => 'nullable|string|max:5000',
        ]);

        $summary = $match->getOrCreateSummary();
        $summary->update([
            'highlights' => array_filter($validated['highlights'] ?? []),
            'commentary' => $validated['commentary'],
        ]);

        return redirect()
            ->back()
            ->with('success', 'Match summary updated successfully.');
    }

    /**
     * Add a highlight
     */
    public function addHighlight(Matches $match, Request $request)
    {
        $validated = $request->validate([
            'highlight' => 'required|string|max:500',
        ]);

        $summary = $match->getOrCreateSummary();
        $summary->addHighlight($validated['highlight']);

        return redirect()
            ->back()
            ->with('success', 'Highlight added successfully.');
    }

    /**
     * Remove a highlight
     */
    public function removeHighlight(Matches $match, Request $request)
    {
        $validated = $request->validate([
            'index' => 'required|integer|min:0',
        ]);

        $summary = $match->getOrCreateSummary();
        $summary->removeHighlight($validated['index']);

        return redirect()
            ->back()
            ->with('success', 'Highlight removed successfully.');
    }

    /**
     * Assign an award
     */
    public function assignAward(Matches $match, Request $request)
    {
        $validated = $request->validate([
            'tournament_award_id' => 'required|exists:tournament_awards,id',
            'player_id' => 'required|exists:players,id',
            'remarks' => 'nullable|string|max:500',
        ]);

        // Check if award already assigned
        $exists = $match->matchAwards()
            ->where('tournament_award_id', $validated['tournament_award_id'])
            ->exists();

        if ($exists) {
            return redirect()
                ->back()
                ->with('error', 'This award has already been assigned for this match.');
        }

        $match->matchAwards()->create($validated);

        return redirect()
            ->back()
            ->with('success', 'Award assigned successfully.');
    }

    /**
     * Remove an award
     */
    public function removeAward(Matches $match, MatchAward $award)
    {
        abort_if($award->match_id !== $match->id, 404);

        $award->delete();

        return redirect()
            ->back()
            ->with('success', 'Award removed successfully.');
    }

    /**
     * Generate summary poster
     */
    public function generatePoster(Matches $match, Request $request)
    {
        try {
            $template = $request->input('template', 'classic');

            $posterPath = $this->posterService->generate($match, $template);

            $summary = $match->getOrCreateSummary();
            $summary->update([
                'summary_poster' => $posterPath,
                'poster_template' => $template,
            ]);

            return redirect()
                ->back()
                ->with('success', 'Summary poster generated successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Failed to generate poster: ' . $e->getMessage());
        }
    }

    /**
     * Send summary to team members
     */
    public function send(Matches $match)
    {
        $summary = $match->summary;

        if (!$summary || !$summary->summary_poster) {
            return redirect()
                ->back()
                ->with('error', 'Please generate the summary poster first.');
        }

        $sentCount = $this->notificationService->sendMatchSummary($match);

        if ($sentCount > 0) {
            return redirect()
                ->back()
                ->with('success', "Summary sent to {$sentCount} recipients.");
        }

        return redirect()
            ->back()
            ->with('error', 'No recipients found or summary already sent.');
    }

    /**
     * Download summary poster
     */
    public function downloadPoster(Matches $match)
    {
        $summary = $match->summary;

        if (!$summary || !$summary->summary_poster) {
            abort(404, 'Poster not found.');
        }

        $path = storage_path('app/public/' . $summary->summary_poster);

        if (!file_exists($path)) {
            abort(404, 'Poster file not found.');
        }

        $filename = "match-summary-{$match->id}.png";

        return response()->download($path, $filename);
    }

    /**
     * Preview summary poster
     */
    public function previewPoster(Matches $match)
    {
        try {
            $posterPath = $this->posterService->generate($match);

            return response()->json([
                'success' => true,
                'preview_url' => asset('storage/' . $posterPath),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Recalculate all statistics for the tournament
     */
    public function recalculateStatistics(Matches $match)
    {
        $tournament = $match->tournament;

        // Recalculate player statistics
        $this->playerStatisticService->recalculateForTournament($tournament);

        // Recalculate point table
        $this->pointTableService->recalculatePointTable($tournament);

        return redirect()
            ->back()
            ->with('success', 'Statistics and point table recalculated successfully.');
    }

    /**
     * Create default awards for tournament
     */
    public function createDefaultAwards(Matches $match)
    {
        $tournament = $match->tournament;

        // Check if awards already exist
        $existingCount = $tournament->awards()->matchLevel()->count();
        if ($existingCount > 0) {
            return redirect()
                ->back()
                ->with('info', 'Awards already exist for this tournament.');
        }

        // Default cricket awards
        $defaultAwards = [
            ['name' => 'Man of the Match', 'icon' => '🏆', 'order' => 1],
            ['name' => 'Best Batsman', 'icon' => '🏏', 'order' => 2],
            ['name' => 'Best Bowler', 'icon' => '🎯', 'order' => 3],
            ['name' => 'Best Fielder', 'icon' => '🧤', 'order' => 4],
            ['name' => 'Best Catch', 'icon' => '👐', 'order' => 5],
        ];

        foreach ($defaultAwards as $award) {
            TournamentAward::create([
                'tournament_id' => $tournament->id,
                'name' => $award['name'],
                'icon' => $award['icon'],
                'is_match_level' => true,
                'is_active' => true,
                'order' => $award['order'],
                'template_settings' => TournamentAward::getDefaultTemplateSettings($award['name']),
            ]);
        }

        return redirect()
            ->back()
            ->with('success', 'Default awards created successfully. You can now assign awards to players.');
    }
}

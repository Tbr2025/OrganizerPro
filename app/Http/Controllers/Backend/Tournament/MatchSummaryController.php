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
use App\Services\Poster\TemplateRenderService;
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

        // Get players from BOTH teams for award assignment (any player can win an award)
        $teamAPlayers = collect();
        $teamBPlayers = collect();

        if ($match->teamA) {
            $teamAPlayers = $match->teamA->players->pluck('player')->filter()->values();
        }
        if ($match->teamB) {
            $teamBPlayers = $match->teamB->players->pluck('player')->filter()->values();
        }

        return view('backend.pages.matches.summary-editor', compact(
            'match',
            'summary',
            'tournament',
            'awards',
            'tournamentAwards',
            'teamAPlayers',
            'teamBPlayers'
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
            $templateId = $request->input('template_id');

            if (!$templateId) {
                return redirect()->back()->with('error', 'Please select a template.');
            }

            $tournament = $match->tournament;
            $tournamentTemplate = $tournament->templates()->find($templateId);

            if (!$tournamentTemplate || !$tournamentTemplate->background_image) {
                return redirect()->back()->with('error', 'Template not found or has no background image.');
            }

            $match->load(['teamA', 'teamB', 'winner', 'result', 'ground', 'matchAwards.player', 'matchAwards.tournamentAward']);
            $matchData = $this->buildMatchData($match, $tournament);

            // Apply innings-based team swap
            $innings = (int) $request->input('innings', 1);
            $matchData = $this->applyInningsSwap($match, $matchData, $innings);

            $renderService = app(TemplateRenderService::class);
            $posterPath = $renderService->renderAndSave($tournamentTemplate, $matchData, 'match-summary-' . $match->id . '-' . time() . '.png');

            $summary = $match->getOrCreateSummary();
            $summary->update([
                'summary_poster' => $posterPath,
                'poster_template' => 'tournament_' . $templateId,
            ]);

            // If sent via AJAX, return file download
            if ($request->expectsJson() || $request->input('download')) {
                return response()->download(
                    storage_path('app/public/' . $posterPath),
                    "match-summary-{$match->id}.png"
                );
            }

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

    /**
     * Generate match poster from summary page
     */
    public function generateMatchPoster(Matches $match, Request $request)
    {
        $request->validate([
            'template_id' => 'required|integer',
            'innings' => 'nullable|integer|in:1,2',
        ]);

        try {
            $tournament = $match->tournament;
            $template = $tournament->templates()->findOrFail($request->input('template_id'));
            $match->load(['teamA', 'teamB', 'winner', 'result', 'ground', 'matchAwards.player', 'matchAwards.tournamentAward']);

            $matchData = $this->buildMatchData($match, $tournament);

            // Apply innings-based team swap
            $innings = (int) $request->input('innings', 1);
            $matchData = $this->applyInningsSwap($match, $matchData, $innings);

            $renderService = app(TemplateRenderService::class);
            $path = $renderService->renderAndSave($template, $matchData, 'match-poster-' . $match->id . '-' . time() . '.png');

            return response()->download(
                storage_path('app/public/' . $path),
                "match-poster-{$match->id}.png"
            );
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to generate poster: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Generate award poster from summary page
     */
    public function generateAwardPoster(Matches $match, Request $request)
    {
        $request->validate([
            'template_id' => 'required|integer',
            'innings' => 'nullable|integer|in:1,2',
            'award_id' => 'required|integer',
        ]);

        try {
            $tournament = $match->tournament;
            $template = $tournament->templates()->findOrFail($request->input('template_id'));
            $match->load(['teamA', 'teamB', 'winner', 'result', 'ground', 'matchAwards.player', 'matchAwards.tournamentAward']);

            $matchData = $this->buildMatchData($match, $tournament);

            // Apply innings-based team swap
            $innings = (int) $request->input('innings', 1);
            $matchData = $this->applyInningsSwap($match, $matchData, $innings);

            // Add award-specific data
            $matchAward = $match->matchAwards()->with('player', 'tournamentAward')->findOrFail($request->input('award_id'));
            $matchData['award_name'] = $matchAward->tournamentAward?->name ?? 'Award';
            $matchData['player_name'] = $matchAward->player?->name ?? 'Player';
            $matchData['player_image'] = $matchAward->player?->image_path ?? 'defaults/default-player.png';

            $renderService = app(TemplateRenderService::class);
            $path = $renderService->renderAndSave($template, $matchData, 'award-poster-' . $match->id . '-' . time() . '.png');

            return response()->download(
                storage_path('app/public/' . $path),
                "award-poster-{$match->id}.png"
            );
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to generate poster: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Build match data array for template rendering
     */
    private function buildMatchData(Matches $match, $tournament): array
    {
        $data = [
            'tournament_name' => $tournament->name,
            'team_a_name' => $match->teamA?->name,
            'team_b_name' => $match->teamB?->name,
            'team_a_short_name' => $match->teamA?->short_name ?? $match->teamA?->name,
            'team_b_short_name' => $match->teamB?->short_name ?? $match->teamB?->name,
            'team_a_logo' => $match->teamA?->team_logo,
            'team_b_logo' => $match->teamB?->team_logo,
            'match_date' => $match->match_date?->format('M d, Y'),
            'match_time' => $match->start_time ? \Carbon\Carbon::parse($match->start_time)->format('h:i A') : null,
            'venue' => $match->ground?->name ?? $match->venue,
            'ground_name' => $match->ground?->name ?? $match->venue,
            'match_number' => (string) ($match->match_number ?? $match->id),
            'match_stage' => $match->stage_display,
        ];

        if ($tournament->settings?->logo) {
            $data['tournament_logo'] = $tournament->settings->logo;
        }

        if ($match->result) {
            $r = $match->result;
            $data['team_a_score'] = $r->team_a_score_display;
            $data['team_b_score'] = $r->team_b_score_display;
            $data['team_a_score_wickets'] = $r->team_a_score . '/' . $r->team_a_wickets;
            $data['team_b_score_wickets'] = $r->team_b_score . '/' . $r->team_b_wickets;
            $data['team_a_runs'] = (string) $r->team_a_score;
            $data['team_b_runs'] = (string) $r->team_b_score;
            $data['team_a_wickets'] = (string) $r->team_a_wickets;
            $data['team_b_wickets'] = (string) $r->team_b_wickets;
            $data['team_a_overs'] = (string) $r->team_a_overs;
            $data['team_b_overs'] = (string) $r->team_b_overs;
            $data['result_summary'] = $r->result_summary ?: $r->generateResultSummary();
            $data['win_margin'] = $r->margin ? 'Won by ' . $r->margin . ' ' . $r->result_type : '';
            if ($r->toss_won_by) {
                $tossWinner = $r->toss_won_by == $match->team_a_id ? $match->teamA?->name : $match->teamB?->name;
                $data['toss_result'] = $tossWinner . ' won toss, chose to ' . ($r->toss_decision ?? 'bat');
            }
        }

        if ($match->winner) {
            $data['winner_name'] = $match->winner->name;
            $data['winner_logo'] = $match->winner->team_logo;
        }

        foreach ($match->matchAwards as $award) {
            $awardSlug = $award->tournamentAward?->slug;
            $playerName = $award->player?->name;
            $playerImage = $award->player?->image_path;
            if (in_array($awardSlug, ['man-of-the-match', 'player-of-the-match'])) {
                if ($playerName) $data['man_of_the_match_name'] = $playerName;
                if ($playerImage) $data['man_of_the_match_image'] = $playerImage;
            } elseif ($awardSlug === 'best-batsman') {
                if ($playerName) $data['best_batsman_name'] = $playerName;
            } elseif ($awardSlug === 'best-bowler') {
                if ($playerName) $data['best_bowler_name'] = $playerName;
            }
        }

        return $data;
    }

    /**
     * Apply innings-based team data swap
     */
    private function applyInningsSwap(Matches $match, array $data, int $innings): array
    {
        $shouldSwap = $match->result && $match->result->team_a_batting_first === false;

        if ($innings === 2) {
            $shouldSwap = $match->result && $match->result->team_a_batting_first !== false;
        }

        if ($shouldSwap) {
            $swapKeys = [
                'team_a_name' => 'team_b_name', 'team_a_short_name' => 'team_b_short_name',
                'team_a_logo' => 'team_b_logo', 'team_a_score' => 'team_b_score',
                'team_a_score_wickets' => 'team_b_score_wickets',
                'team_a_runs' => 'team_b_runs', 'team_a_wickets' => 'team_b_wickets',
                'team_a_overs' => 'team_b_overs',
            ];
            foreach ($swapKeys as $keyA => $keyB) {
                $tmp = $data[$keyA] ?? null;
                $data[$keyA] = $data[$keyB] ?? null;
                $data[$keyB] = $tmp;
            }
        }

        return $data;
    }
}

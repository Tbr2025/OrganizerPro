<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
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

        return view('backend.pages.matches.result.edit', [
            'match' => $match,
            'result' => $result,
            'breadcrumbs' => [
                'title' => __('Match Result'),
                'items' => [
                    ['label' => __('Matches'), 'url' => route('admin.matches.index')],
                    ['label' => $match->name, 'url' => route('admin.matches.show', $match)],
                ],
            ],
        ]);
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

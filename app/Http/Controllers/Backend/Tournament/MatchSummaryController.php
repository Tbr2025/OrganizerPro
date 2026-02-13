<?php

namespace App\Http\Controllers\Backend\Tournament;

use App\Http\Controllers\Controller;
use App\Models\Matches;
use App\Models\MatchSummary;
use App\Models\MatchAward;
use App\Models\Player;
use App\Services\Poster\MatchSummaryPosterService;
use App\Services\Notification\TournamentNotificationService;
use Illuminate\Http\Request;

class MatchSummaryController extends Controller
{
    protected MatchSummaryPosterService $posterService;
    protected TournamentNotificationService $notificationService;

    public function __construct(
        MatchSummaryPosterService $posterService,
        TournamentNotificationService $notificationService
    ) {
        $this->posterService = $posterService;
        $this->notificationService = $notificationService;
    }

    /**
     * Show summary editor
     */
    public function edit(Matches $match)
    {
        $summary = $match->getOrCreateSummary();
        $tournament = $match->tournament;
        $awards = $match->matchAwards()->with('player', 'tournamentAward')->get();
        $tournamentAwards = $tournament->awards()->matchLevel()->active()->get();

        // Get players from both teams for award assignment
        $players = collect();
        if ($match->teamA) {
            $players = $players->merge($match->teamA->users->pluck('player')->filter());
        }
        if ($match->teamB) {
            $players = $players->merge($match->teamB->users->pluck('player')->filter());
        }

        return view('backend.pages.matches.summary-editor', compact(
            'match',
            'summary',
            'tournament',
            'awards',
            'tournamentAwards',
            'players'
        ));
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
    public function generatePoster(Matches $match)
    {
        try {
            $posterPath = $this->posterService->generate($match);

            $summary = $match->getOrCreateSummary();
            $summary->update(['summary_poster' => $posterPath]);

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
}

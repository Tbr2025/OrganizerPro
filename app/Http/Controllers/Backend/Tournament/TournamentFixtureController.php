<?php

namespace App\Http\Controllers\Backend\Tournament;

use App\Http\Controllers\Controller;
use App\Models\Ground;
use App\Models\Matches;
use App\Models\Tournament;
use App\Models\TournamentTemplate;
use App\Services\Poster\MatchPosterService;
use App\Services\Tournament\FixtureGeneratorService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TournamentFixtureController extends Controller
{
    public function __construct(
        private readonly FixtureGeneratorService $fixtureService,
        private readonly MatchPosterService $posterService
    ) {}

    public function index(Tournament $tournament, Request $request): View
    {
        $this->checkAuthorization(Auth::user(), ['tournament.view']);

        $stage = $request->get('stage');
        $groupId = $request->get('group_id');

        $query = $tournament->matches()
            ->with(['teamA', 'teamB', 'ground', 'group', 'result'])
            ->orderBy('match_date')
            ->orderBy('match_number');

        if ($stage) {
            $query->where('stage', $stage);
        }

        if ($groupId) {
            $query->where('tournament_group_id', $groupId);
        }

        $matches = $query->get();

        // Group matches by stage
        $groupedMatches = $matches->groupBy('stage');

        $grounds = Ground::where(function ($q) use ($tournament) {
            $q->where('organization_id', $tournament->organization_id)
              ->orWhereNull('organization_id');
        })->active()->get();

        $teams = $tournament->actualTeams;

        return view('backend.pages.tournaments.fixtures.index', [
            'tournament' => $tournament,
            'matches' => $matches,
            'groupedMatches' => $groupedMatches,
            'groups' => $tournament->groups,
            'grounds' => $grounds,
            'teams' => $teams,
            'breadcrumbs' => [
                'title' => __('Fixtures'),
                'items' => [
                    ['label' => __('Tournaments'), 'url' => route('admin.tournaments.index')],
                    ['label' => $tournament->name, 'url' => route('admin.tournaments.dashboard', $tournament)],
                ],
            ],
        ]);
    }

    public function generateGroupStage(Tournament $tournament): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        // Check prerequisites
        if ($tournament->groups()->count() === 0) {
            return redirect()->back()->with('error', __('Please create groups first.'));
        }

        $totalTeams = 0;
        foreach ($tournament->groups as $group) {
            if ($group->teams()->count() < 2) {
                return redirect()->back()->with('error', __('Each group must have at least 2 teams.'));
            }
            $totalTeams += $group->teams()->count();
        }

        if ($totalTeams === 0) {
            return redirect()->back()->with('error', __('Please add teams to groups first.'));
        }

        // Check if fixtures already exist
        if ($tournament->matches()->where('stage', 'group')->count() > 0) {
            return redirect()->back()->with('error', __('Group stage fixtures already exist. Delete them first to regenerate.'));
        }

        try {
            $fixtures = $this->fixtureService->generateGroupStageFixtures($tournament);
            return redirect()->back()->with('success', __(':count group stage matches generated.', ['count' => $fixtures->count()]));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to generate fixtures: ') . $e->getMessage());
        }
    }

    public function generateKnockouts(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        $stage = $request->input('stage', 'semi_final');

        // Validate stage
        if (!in_array($stage, ['quarter_final', 'semi_final', 'final', 'third_place'])) {
            return redirect()->back()->with('error', __('Invalid stage.'));
        }

        // Check if this stage fixtures already exist
        if ($tournament->matches()->where('stage', $stage)->count() > 0) {
            return redirect()->back()->with('error', __(':stage fixtures already exist.', ['stage' => ucfirst(str_replace('_', ' ', $stage))]));
        }

        try {
            $fixtures = $this->fixtureService->generateKnockoutFixtures($tournament, $stage);
            return redirect()->back()->with('success', __(':count :stage matches generated.', [
                'count' => $fixtures->count(),
                'stage' => str_replace('_', ' ', $stage),
            ]));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to generate fixtures: ') . $e->getMessage());
        }
    }

    public function reschedule(Request $request, Tournament $tournament, Matches $match): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        $validated = $request->validate([
            'match_date' => 'required|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'ground_id' => 'nullable|exists:grounds,id',
        ]);

        $ground = isset($validated['ground_id']) ? Ground::find($validated['ground_id']) : null;

        $this->fixtureService->rescheduleMatch(
            $match,
            Carbon::parse($validated['match_date']),
            $ground
        );

        if (isset($validated['start_time'])) {
            $match->update(['start_time' => $validated['start_time']]);
        }
        if (isset($validated['end_time'])) {
            $match->update(['end_time' => $validated['end_time']]);
        }

        // Reset poster sent status since date changed
        $match->update(['poster_sent' => false, 'poster_sent_at' => null]);

        return redirect()->back()->with('success', __('Match rescheduled successfully.'));
    }

    public function cancel(Request $request, Tournament $tournament, Matches $match): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        $reason = $request->input('reason');

        $this->fixtureService->cancelMatch($match, $reason);

        return redirect()->back()->with('success', __('Match cancelled.'));
    }

    public function generatePoster(Request $request, Tournament $tournament, Matches $match): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        try {
            // Use default match poster template if one is set
            $template = $tournament->getTemplate(TournamentTemplate::TYPE_MATCH_POSTER);

            if ($template) {
                $enhancedService = new \App\Services\Poster\EnhancedMatchPosterService();
                $path = $enhancedService->generateFromTemplate($match, $template);
            } elseif ($match->isHighStakes()) {
                $path = $this->posterService->generateFinalsPosters($match);
            } else {
                $path = $this->posterService->generate($match);
            }

            return redirect()->back()->with('success', __('Match poster generated successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to generate poster: ') . $e->getMessage());
        }
    }

    public function deleteGroupStage(Tournament $tournament): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        // Only allow deletion if no matches have been completed
        $completedCount = $tournament->matches()
            ->where('stage', 'group')
            ->where('status', 'completed')
            ->count();

        if ($completedCount > 0) {
            return redirect()->back()->with('error', __('Cannot delete fixtures - :count matches have already been completed.', ['count' => $completedCount]));
        }

        $deleted = $tournament->matches()->where('stage', 'group')->delete();

        return redirect()->back()->with('success', __(':count group stage matches deleted.', ['count' => $deleted]));
    }

    public function bulkGeneratePosters(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        $query = $tournament->matches()->where('is_cancelled', false);

        // If not forcing regeneration, only generate missing posters
        if (!$request->boolean('regenerate')) {
            $query->whereNull('poster_image');
        }

        $matches = $query->get();

        // Use default match poster template if one is set
        $template = $tournament->getTemplate(TournamentTemplate::TYPE_MATCH_POSTER);
        $enhancedService = $template ? new \App\Services\Poster\EnhancedMatchPosterService() : null;

        $generated = 0;
        $failed = 0;
        foreach ($matches as $match) {
            try {
                if ($template) {
                    $enhancedService->generateFromTemplate($match, $template);
                } elseif ($match->isHighStakes()) {
                    $this->posterService->generateFinalsPosters($match);
                } else {
                    $this->posterService->generate($match);
                }
                $generated++;
            } catch (\Exception $e) {
                $failed++;
                \Log::error("Failed to generate poster for match {$match->id}: " . $e->getMessage());
            }
        }

        $message = $generated . ' poster(s) generated.';
        if ($failed > 0) {
            $message .= ' ' . $failed . ' failed.';
        }
        if ($generated === 0 && $failed === 0) {
            $message = 'All matches already have posters. Use "Regenerate All" to recreate them.';
        }

        return redirect()->back()->with('success', $message);
    }

    public function store(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        // Handle tournament location as venue text
        $venue = null;
        $groundId = $request->input('ground_id');
        if ($groundId && str_starts_with($groundId, 'location:')) {
            $venue = substr($groundId, 9);
            $request->merge(['ground_id' => null]);
        }

        $validated = $request->validate([
            'team_a_id' => 'required|exists:actual_teams,id',
            'team_b_id' => 'required|exists:actual_teams,id|different:team_a_id',
            'stage' => 'required|in:group,league,quarter_final,semi_final,final,third_place,qualifier_1,eliminator,qualifier_2',
            'date' => 'nullable|date',
            'start_time' => 'nullable|date_format:H:i',
            'ground_id' => 'nullable|exists:grounds,id',
            'group_id' => 'nullable|exists:tournament_groups,id',
            'overs' => 'nullable|integer|min:1|max:50',
        ]);

        if ($venue) {
            $validated['venue'] = $venue;
        }

        try {
            $match = $this->fixtureService->createCustomMatch($tournament, $validated);
            return redirect()->back()->with('success', __('Match created successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to create match: ') . $e->getMessage());
        }
    }

    public function update(Request $request, Tournament $tournament, Matches $match): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        // Handle tournament location as venue text
        $venue = null;
        $groundId = $request->input('ground_id');
        if ($groundId && str_starts_with($groundId, 'location:')) {
            $venue = substr($groundId, 9);
            $request->merge(['ground_id' => null]);
        }

        $validated = $request->validate([
            'team_a_id' => 'nullable|exists:actual_teams,id',
            'team_b_id' => 'nullable|exists:actual_teams,id',
            'stage' => 'nullable|in:group,league,quarter_final,semi_final,final,third_place,qualifier_1,eliminator,qualifier_2',
            'date' => 'nullable|date',
            'start_time' => 'nullable|date_format:H:i',
            'ground_id' => 'nullable|exists:grounds,id',
            'group_id' => 'nullable|exists:tournament_groups,id',
            'overs' => 'nullable|integer|min:1|max:50',
        ]);

        if ($venue) {
            $validated['venue'] = $venue;
            $validated['ground_id'] = null;
        }

        // Ensure team_a and team_b are different if both provided
        if (isset($validated['team_a_id']) && isset($validated['team_b_id']) && $validated['team_a_id'] === $validated['team_b_id']) {
            return redirect()->back()->with('error', __('Team A and Team B must be different.'));
        }

        try {
            $this->fixtureService->updateMatch($match, $validated);
            return redirect()->back()->with('success', __('Match updated successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to update match: ') . $e->getMessage());
        }
    }

    public function destroy(Tournament $tournament, Matches $match): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        if ($match->isCompleted()) {
            return redirect()->back()->with('error', __('Cannot delete a completed match.'));
        }

        try {
            $this->fixtureService->deleteMatch($match);
            return redirect()->back()->with('success', __('Match deleted successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to delete match: ') . $e->getMessage());
        }
    }

    public function generateIplPlayoffs(Tournament $tournament): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        // Check if IPL playoff fixtures already exist
        $existingIplMatches = $tournament->matches()
            ->whereIn('stage', ['qualifier_1', 'eliminator', 'qualifier_2'])
            ->count();

        if ($existingIplMatches > 0) {
            return redirect()->back()->with('error', __('IPL playoff fixtures already exist.'));
        }

        try {
            $fixtures = $this->fixtureService->generateIplPlayoffs($tournament);
            return redirect()->back()->with('success', __(':count IPL playoff matches generated.', ['count' => $fixtures->count()]));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to generate IPL playoffs: ') . $e->getMessage());
        }
    }

    /**
     * Export fixtures as CSV
     */
    public function exportCsv(Tournament $tournament)
    {
        $matches = $tournament->matches()
            ->with(['teamA', 'teamB', 'ground', 'winner', 'result'])
            ->orderBy('match_date')
            ->orderBy('start_time')
            ->get();

        $filename = str_replace(' ', '_', $tournament->name) . '_fixtures.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($matches) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['#', 'Match', 'Team A', 'Team B', 'Date', 'Time', 'Venue', 'Stage', 'Status', 'Team A Score', 'Team B Score', 'Winner', 'Result']);

            foreach ($matches as $match) {
                fputcsv($file, [
                    $match->match_number ?? $match->id,
                    'Match #' . ($match->match_number ?? $match->id),
                    $match->teamA?->name ?? 'TBD',
                    $match->teamB?->name ?? 'TBD',
                    $match->match_date?->format('d M Y') ?? '',
                    $match->start_time ? Carbon::parse($match->start_time)->format('h:i A') : '',
                    $match->ground?->name ?? $match->venue ?? '',
                    $match->stage_display ?? $match->stage ?? '',
                    ucfirst($match->status ?? 'upcoming'),
                    $match->result?->team_a_score_display ?? '',
                    $match->result?->team_b_score_display ?? '',
                    $match->winner?->name ?? '',
                    $match->result?->result_summary ?? '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}

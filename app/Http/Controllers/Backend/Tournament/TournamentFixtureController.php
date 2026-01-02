<?php

namespace App\Http\Controllers\Backend\Tournament;

use App\Http\Controllers\Controller;
use App\Models\Ground;
use App\Models\Matches;
use App\Models\Tournament;
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

        $grounds = Ground::where('organization_id', $tournament->organization_id)->active()->get();

        return view('backend.pages.tournaments.fixtures.index', [
            'tournament' => $tournament,
            'matches' => $matches,
            'groupedMatches' => $groupedMatches,
            'groups' => $tournament->groups,
            'grounds' => $grounds,
            'breadcrumbs' => [
                'title' => __('Fixtures'),
                'items' => [
                    ['label' => __('Tournaments'), 'url' => route('admin.tournaments.index')],
                    ['label' => $tournament->name, 'url' => route('admin.tournaments.show', $tournament)],
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

    public function generatePoster(Tournament $tournament, Matches $match): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        try {
            if ($match->isFinal() || $match->isSemiFinal()) {
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

    public function bulkGeneratePosters(Tournament $tournament): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        $matches = $tournament->matches()
            ->whereNull('poster_image')
            ->where('is_cancelled', false)
            ->get();

        $generated = 0;
        foreach ($matches as $match) {
            try {
                if ($match->isFinal() || $match->isSemiFinal()) {
                    $this->posterService->generateFinalsPosters($match);
                } else {
                    $this->posterService->generate($match);
                }
                $generated++;
            } catch (\Exception $e) {
                // Log error but continue
                \Log::error("Failed to generate poster for match {$match->id}: " . $e->getMessage());
            }
        }

        return redirect()->back()->with('success', __(':count match posters generated.', ['count' => $generated]));
    }
}

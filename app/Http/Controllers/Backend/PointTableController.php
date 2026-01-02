<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Services\Poster\PointTablePosterService;
use App\Services\Tournament\PointTableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PointTableController extends Controller
{
    public function __construct(
        private readonly PointTableService $pointTableService,
        private readonly PointTablePosterService $posterService
    ) {}

    public function index(Tournament $tournament, Request $request): View
    {
        $this->checkAuthorization(Auth::user(), ['tournament.view']);

        $groupId = $request->get('group_id');

        if ($groupId) {
            $entries = $this->pointTableService->getPointTable($tournament, $groupId);
            $selectedGroup = $tournament->groups()->find($groupId);
        } else {
            $entries = collect();
            $selectedGroup = null;
        }

        $pointTableByGroups = $this->pointTableService->getPointTableByGroups($tournament);

        return view('backend.pages.tournaments.point-table.index', [
            'tournament' => $tournament,
            'entries' => $entries,
            'selectedGroup' => $selectedGroup,
            'pointTableByGroups' => $pointTableByGroups,
            'groups' => $tournament->groups,
            'breadcrumbs' => [
                'title' => __('Point Table'),
                'items' => [
                    ['label' => __('Tournaments'), 'url' => route('admin.tournaments.index')],
                    ['label' => $tournament->name, 'url' => route('admin.tournaments.show', $tournament)],
                ],
            ],
        ]);
    }

    public function recalculate(Tournament $tournament): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        try {
            $this->pointTableService->recalculatePointTable($tournament);
            return redirect()->back()->with('success', __('Point table recalculated successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to recalculate: ') . $e->getMessage());
        }
    }

    public function generatePoster(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        $groupId = $request->get('group_id');

        try {
            if ($groupId) {
                $group = $tournament->groups()->findOrFail($groupId);
                $path = $this->posterService->generate($group);
                return redirect()->back()->with('success', __('Point table poster generated for :group.', ['group' => $group->name]));
            } else {
                $paths = $this->posterService->generateAllGroups($tournament);
                return redirect()->back()->with('success', __(':count point table posters generated.', ['count' => count($paths)]));
            }
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to generate poster: ') . $e->getMessage());
        }
    }

    public function initialize(Tournament $tournament): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        try {
            $this->pointTableService->initializePointTable($tournament);
            return redirect()->back()->with('success', __('Point table initialized successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to initialize: ') . $e->getMessage());
        }
    }

    public function updateQualified(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        $validated = $request->validate([
            'qualified_team_ids' => 'array',
            'qualified_team_ids.*' => 'exists:actual_teams,id',
        ]);

        // Reset all qualified flags
        $tournament->pointTableEntries()->update(['qualified' => false]);

        // Set qualified for selected teams
        if (!empty($validated['qualified_team_ids'])) {
            $tournament->pointTableEntries()
                ->whereIn('actual_team_id', $validated['qualified_team_ids'])
                ->update(['qualified' => true]);
        }

        return redirect()->back()->with('success', __('Qualified teams updated.'));
    }
}

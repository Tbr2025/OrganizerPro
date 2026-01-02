<?php

namespace App\Http\Controllers\Backend\Tournament;

use App\Http\Controllers\Controller;
use App\Models\ActualTeam;
use App\Models\Tournament;
use App\Models\TournamentGroup;
use App\Services\Tournament\PointTableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TournamentGroupController extends Controller
{
    public function __construct(
        private readonly PointTableService $pointTableService
    ) {}

    public function index(Tournament $tournament): View
    {
        $this->checkAuthorization(Auth::user(), ['tournament.view']);

        $groups = $tournament->groups()->with(['teams', 'pointTableEntries.team'])->get();
        $availableTeams = ActualTeam::where('tournament_id', $tournament->id)
            ->whereDoesntHave('groups', function ($query) use ($tournament) {
                $query->where('tournament_id', $tournament->id);
            })
            ->get();

        return view('backend.pages.tournaments.groups.index', [
            'tournament' => $tournament,
            'groups' => $groups,
            'availableTeams' => $availableTeams,
            'breadcrumbs' => [
                'title' => __('Groups'),
                'items' => [
                    ['label' => __('Tournaments'), 'url' => route('admin.tournaments.index')],
                    ['label' => $tournament->name, 'url' => route('admin.tournaments.show', $tournament)],
                ],
            ],
        ]);
    }

    public function store(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $maxOrder = $tournament->groups()->max('order') ?? 0;

        $group = $tournament->groups()->create([
            'name' => $validated['name'],
            'order' => $maxOrder + 1,
        ]);

        return redirect()->back()->with('success', __('Group created successfully.'));
    }

    public function update(Request $request, Tournament $tournament, TournamentGroup $group): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'order' => 'nullable|integer|min:0',
        ]);

        $group->update($validated);

        return redirect()->back()->with('success', __('Group updated successfully.'));
    }

    public function destroy(Tournament $tournament, TournamentGroup $group): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        // Check if group has matches
        if ($group->matches()->count() > 0) {
            return redirect()->back()->with('error', __('Cannot delete group with existing matches.'));
        }

        $group->delete();

        return redirect()->back()->with('success', __('Group deleted successfully.'));
    }

    public function addTeam(Request $request, Tournament $tournament, TournamentGroup $group): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        $validated = $request->validate([
            'actual_team_id' => 'required|exists:actual_teams,id',
        ]);

        // Check if team is already in a group
        $existingGroup = TournamentGroup::whereHas('teams', function ($query) use ($validated) {
            $query->where('actual_team_id', $validated['actual_team_id']);
        })->where('tournament_id', $tournament->id)->first();

        if ($existingGroup) {
            return redirect()->back()->with('error', __('Team is already in :group.', ['group' => $existingGroup->name]));
        }

        // Add team to group
        $maxOrder = $group->groupTeams()->max('order') ?? 0;
        $group->teams()->attach($validated['actual_team_id'], ['order' => $maxOrder + 1]);

        // Initialize point table entry
        $this->pointTableService->initializePointTable($tournament);

        return redirect()->back()->with('success', __('Team added to group successfully.'));
    }

    public function removeTeam(Tournament $tournament, TournamentGroup $group, ActualTeam $team): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        // Check if team has played matches in this group
        $hasMatches = $group->matches()
            ->where(function ($query) use ($team) {
                $query->where('team_a_id', $team->id)
                    ->orWhere('team_b_id', $team->id);
            })
            ->exists();

        if ($hasMatches) {
            return redirect()->back()->with('error', __('Cannot remove team that has played matches in this group.'));
        }

        $group->teams()->detach($team->id);

        // Remove point table entry
        $tournament->pointTableEntries()
            ->where('tournament_group_id', $group->id)
            ->where('actual_team_id', $team->id)
            ->delete();

        return redirect()->back()->with('success', __('Team removed from group.'));
    }

    public function autoCreate(Tournament $tournament): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        $settings = $tournament->settings;
        $numberOfGroups = $settings->number_of_groups ?? 2;

        // Delete existing empty groups
        $tournament->groups()->whereDoesntHave('teams')->delete();

        $existingCount = $tournament->groups()->count();

        for ($i = $existingCount; $i < $numberOfGroups; $i++) {
            $letter = chr(65 + $i); // A, B, C, D...
            $tournament->groups()->create([
                'name' => "Pool {$letter}",
                'order' => $i,
            ]);
        }

        return redirect()->back()->with('success', __(':count groups created.', ['count' => $numberOfGroups - $existingCount]));
    }

    public function reorderTeams(Request $request, Tournament $tournament, TournamentGroup $group): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        $validated = $request->validate([
            'team_ids' => 'required|array',
            'team_ids.*' => 'exists:actual_teams,id',
        ]);

        foreach ($validated['team_ids'] as $index => $teamId) {
            $group->groupTeams()
                ->where('actual_team_id', $teamId)
                ->update(['order' => $index]);
        }

        return redirect()->back()->with('success', __('Teams reordered successfully.'));
    }
}

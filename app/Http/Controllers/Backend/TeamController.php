<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Models\PlayerType;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TeamController extends Controller
{
    public function index()
    {
        $teams = Team::with(['tournament', 'admin'])->latest()->paginate(20);

        return view('backend.pages.teams.index', [
            'teams' => $teams,
            'breadcrumbs' => [
                'title' => __('Teams'),
            ],
        ]);
    }

    public function create()
    {
        $tournaments = Tournament::pluck('name', 'id');
        $admins = User::role(['Organizer', 'Superadmin'])->pluck('name', 'id');
        $teams = Team::all();

        return view('backend.pages.teams.create', [
            'tournaments' => $tournaments,
            'admins' => $admins,
            'breadcrumbs' => [
                'title' => __('New Team'),
                'items' => [
                    ['label' => __('Teams'), 'url' => route('admin.teams.index')],
                ],
            ],
            'teams' => $teams,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'short_name' => 'nullable|string|max:50',
            'admin_id' => 'nullable|exists:users,id',
            'logo' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('logo')) {
            $validated['logo'] = $request->file('logo')->store('logos', 'public');
        }



        Team::create($validated);

        return redirect()->route('admin.teams.index')->with('success', 'Team created successfully.');
    }

    public function show(Team $team)
    {
        $availablePlayers = Player::whereDoesntHave('teams', function ($query) use ($team) {
            $query->where('teams.id', $team->id);
        })->get();

        $playerTypes = PlayerType::all(); // ✅ fetch player roles

        return view('backend.pages.teams.show', [
            'team' => $team,
            'availablePlayers' => $availablePlayers,
            'playerTypes' => $playerTypes, // ✅ pass to view
            'breadcrumbs' => [
                'title' => $team->name,
                'items' => [
                    ['label' => __('Teams'), 'url' => route('admin.teams.index')],
                ],
            ],
        ]);
    }

    public function destroy(Team $team)
    {
        $team->delete();
        return back()->with('success', 'Team deleted successfully.');
    }

    public function update(Request $request, Team $team)
    {
        $this->authorize('team.edit');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'admin_id' => 'nullable|exists:users,id',

            'short_name' => 'nullable|string|max:50',
            'logo' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('logo')) {
            $validated['logo'] = $request->file('logo')->store('teams', 'public');
        }

        $team->update($validated);

        return redirect()->route('admin.teams.index')->with('success', __('Team updated successfully.'));
    }
}

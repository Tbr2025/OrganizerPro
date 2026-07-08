<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Models\PlayerType;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use App\Services\LogoProcessingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        $query = Team::with(['tournament', 'admin']);

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('short_name', 'like', "%{$search}%");
            });
        }

        // Tournament filter
        if ($request->filled('tournament_id')) {
            $query->where('tournament_id', $request->tournament_id);
        }

        // Sort
        $sort = $request->get('sort', 'latest');
        $query = match ($sort) {
            'oldest' => $query->oldest(),
            'name_asc' => $query->orderBy('name', 'asc'),
            'name_desc' => $query->orderBy('name', 'desc'),
            default => $query->latest(),
        };

        $teams = $query->paginate(20);
        $tournaments = Tournament::forUser(auth()->user())->orderBy('name')->pluck('name', 'id');

        return view('backend.pages.teams.index', [
            'teams' => $teams,
            'tournaments' => $tournaments,
            'filters' => $request->only(['search', 'tournament_id', 'sort']),
            'breadcrumbs' => [
                'title' => __('Teams'),
            ],
        ]);
    }

    public function create()
    {
        $tournaments = Tournament::forUser(auth()->user())->pluck('name', 'id');
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
            'logo' => 'nullable|image|max:6144',
        ]);

        if ($request->hasFile('logo')) {
            $validated['logo'] = LogoProcessingService::processLogo($request->file('logo'), 'team-logos');
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
            'logo' => 'nullable|image|max:6144',
        ]);

        if ($request->hasFile('logo')) {
            $validated['logo'] = LogoProcessingService::processLogo($request->file('logo'), 'team-logos', $team->logo);
        }

        $team->update($validated);

        return redirect()->route('admin.teams.index')->with('success', __('Team updated successfully.'));
    }
}

<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TournamentController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        // Base query with eager loading for stats
        $baseQuery = Tournament::with(['organization', 'zone', 'settings']);

        // Apply role-based scoping
        if (!$user->hasRole('Superadmin')) {
            if ($user->organization_id) {
                $baseQuery->where('organization_id', $user->organization_id);
            } else {
                $baseQuery->whereRaw('1 = 0');
            }
        }

        // Get stats counts for dashboard (before filtering)
        $statsQuery = clone $baseQuery;
        $stats = [
            'total' => (clone $statsQuery)->count(),
            'draft' => (clone $statsQuery)->where('status', 'draft')->count(),
            'registration' => (clone $statsQuery)->where('status', 'registration')->count(),
            'ongoing' => (clone $statsQuery)->whereIn('status', ['ongoing', 'active'])->count(),
            'completed' => (clone $statsQuery)->where('status', 'completed')->count(),
        ];

        // Get total pending registrations across all tournaments
        $tournamentIds = (clone $statsQuery)->pluck('id');
        $stats['pending_registrations'] = TournamentRegistration::whereIn('tournament_id', $tournamentIds)
            ->where('status', 'pending')
            ->count();

        // Clone for main query
        $query = clone $baseQuery;

        // Apply status filter
        $statusFilter = $request->input('status');
        if ($statusFilter && $statusFilter !== 'all') {
            if ($statusFilter === 'ongoing') {
                $query->whereIn('status', ['ongoing', 'active']);
            } else {
                $query->where('status', $statusFilter);
            }
        }

        // Apply search filter
        $searchTerm = $request->input('search');
        if ($searchTerm) {
            $query->where(function ($q) use ($searchTerm, $user) {
                $q->where('name', 'like', '%' . $searchTerm . '%')
                    ->orWhere('location', 'like', '%' . $searchTerm . '%');

                if ($user->hasRole('Superadmin')) {
                    $q->orWhereHas('organization', function ($orgQuery) use ($searchTerm) {
                        $orgQuery->where('name', 'like', '%' . $searchTerm . '%');
                    });
                }
            });
        }

        // Load additional relationships for enhanced cards
        $query->with([
            'settings',
            'champion',
            'runnerUp',
            'registrations' => function ($q) {
                $q->where('status', 'pending');
            },
        ])
        ->withCount([
            'matches',
            'matches as completed_matches_count' => function ($q) {
                $q->where('status', 'completed');
            },
            'registrations as pending_registrations_count' => function ($q) {
                $q->where('status', 'pending');
            },
        ]);

        // Get teams count via groups
        $tournaments = $query->latest()->paginate(12);

        // Append teams count for each tournament
        foreach ($tournaments as $tournament) {
            $tournament->teams_count = DB::table('tournament_group_teams')
                ->join('tournament_groups', 'tournament_group_teams.tournament_group_id', '=', 'tournament_groups.id')
                ->where('tournament_groups.tournament_id', $tournament->id)
                ->count();
        }

        return view('backend.pages.tournaments.index', [
            'tournaments' => $tournaments,
            'stats' => $stats,
            'currentStatus' => $statusFilter ?? 'all',
            'breadcrumbs' => [
                'title' => __('Tournaments'),
            ],
        ]);
    }

    /**
     * Show tournament dashboard - central hub for managing a tournament
     */
    public function dashboard(Tournament $tournament)
    {
        $tournament->load([
            'settings',
            'organization',
            'zone',
            'champion',
            'runnerUp',
            'groups.teams',
        ]);

        // Get various counts and stats
        $stats = [
            'teams_count' => DB::table('tournament_group_teams')
                ->join('tournament_groups', 'tournament_group_teams.tournament_group_id', '=', 'tournament_groups.id')
                ->where('tournament_groups.tournament_id', $tournament->id)
                ->count(),
            'groups_count' => $tournament->groups()->count(),
            'total_matches' => $tournament->matches()->count(),
            'completed_matches' => $tournament->matches()->where('status', 'completed')->count(),
            'upcoming_matches' => $tournament->matches()->where('status', 'upcoming')->count(),
            'live_matches' => $tournament->matches()->where('status', 'live')->count(),
            'pending_registrations' => $tournament->registrations()->where('status', 'pending')->count(),
            'approved_registrations' => $tournament->registrations()->where('status', 'approved')->count(),
            'unscheduled_matches' => $tournament->matches()->whereNull('match_date')->count(),
        ];

        // Get recent activity (last 5 matches with results)
        $recentMatches = $tournament->matches()
            ->with(['teamA', 'teamB', 'winner'])
            ->where('status', 'completed')
            ->orderByDesc('match_date')
            ->limit(5)
            ->get();

        // Get upcoming matches (next 5)
        $upcomingMatches = $tournament->matches()
            ->with(['teamA', 'teamB', 'ground'])
            ->where('status', 'upcoming')
            ->whereNotNull('match_date')
            ->orderBy('match_date')
            ->limit(5)
            ->get();

        // Get pending registrations
        $pendingRegistrations = $tournament->registrations()
            ->where('status', 'pending')
            ->latest()
            ->limit(5)
            ->get();

        return view('backend.pages.tournaments.dashboard', [
            'tournament' => $tournament,
            'stats' => $stats,
            'recentMatches' => $recentMatches,
            'upcomingMatches' => $upcomingMatches,
            'pendingRegistrations' => $pendingRegistrations,
            'breadcrumbs' => [
                ['label' => 'Tournaments', 'url' => route('admin.tournaments.index')],
                ['label' => $tournament->name],
            ],
        ]);
    }
    public function create()
    {
        // 1. Get the currently authenticated user
        $user = Auth::user();

        // Safety check: if no user is logged in, deny access.
        if (!$user) {
            abort(403, 'Unauthorized action.');
        }

        // 2. Check if the user has the 'Superadmin' role
        if ($user->hasRole('Superadmin')) {
            // Superadmin can see ALL organizations to choose from.
            $organizations = Organization::all();
        } else {
            // For any other user, scope the data by their organization_id.

            // Safety check: ensure the non-admin user is actually assigned to an organization.
            if (!$user->organization_id) {
                // If not, they can't create a tournament. Redirect back with an informative error.
                return redirect()->back()->with('error', 'You are not assigned to an organization and cannot create a tournament.');
            }

            // Get ONLY the organization that belongs to this user.
            // We use ->get() to ensure the result is a collection, so the view's loop works.
            $organizations = Organization::where('id', $user->organization_id)->get();
        }

        // 3. Prepare the rest of the data for the view
        // Initially, no organization is selected, so locations are empty. This is correct.
        $locations = collect();

        // 4. Load zones for the user's organization (for non-Superadmin)
        // For Superadmin, zones will be loaded via AJAX when organization is selected
        if ($user->hasRole('Superadmin')) {
            $zones = collect(); // Will be populated via AJAX
        } else {
            $zones = Zone::where('organization_id', $user->organization_id)
                ->active()
                ->orderBy('order')
                ->orderBy('name')
                ->get();
        }

        // 5. Return the view with the correctly scoped data.
        return view('backend.pages.tournaments.create', [
            'organizations' => $organizations,
            'locations' => $locations,
            'zones' => $zones,
            'breadcrumbs' => [
                ['label' => 'Tournaments', 'url' => route('admin.tournaments.index')],
                ['label' => 'Create Tournament'],
            ],
        ]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'zone_id'        => 'nullable|exists:zones,id',
            'name'           => 'required|string|max:255',
            'start_date'     => 'required|date|after_or_equal:today',
            'end_date'       => 'required|date|after_or_equal:start_date',
            'location'       => 'nullable|string|max:255',
        ]);

        // Handle empty zone_id
        if (empty($validated['zone_id'])) {
            $validated['zone_id'] = null;
        }

        Tournament::create($validated);

        return redirect()
            ->route('admin.tournaments.index')
            ->with('success', 'Tournament created successfully.');
    }

    public function edit(Tournament $tournament)
    {
        $user = Auth::user();

        if ($user->hasRole('Superadmin')) {
            $organizations = Organization::all();
        } else {
            $organizations = Organization::where('id', $user->organization_id)->get();
        }

        // Load zones for the tournament's organization
        $zones = Zone::where('organization_id', $tournament->organization_id)
            ->active()
            ->orderBy('order')
            ->orderBy('name')
            ->get();

        return view('backend.pages.tournaments.edit', [
            'tournament'    => $tournament,
            'organizations' => $organizations,
            'zones'         => $zones,
            'breadcrumbs'   => [
                ['label' => 'Tournaments', 'url' => route('admin.tournaments.index')],
                ['label' => 'Edit Tournament'],
            ],
        ]);
    }

    public function update(Request $request, Tournament $tournament)
    {
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'zone_id'        => 'nullable|exists:zones,id',
            'name'           => 'required|string|max:255',
            'logo'           => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'start_date'     => 'required|date|after_or_equal:today',
            'end_date'       => 'required|date|after_or_equal:start_date',
            'location'       => 'nullable|string|max:255',
        ]);

        // Handle empty zone_id
        if (empty($validated['zone_id'])) {
            $validated['zone_id'] = null;
        }

        // Handle logo upload
        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($tournament->logo && \Storage::disk('public')->exists($tournament->logo)) {
                \Storage::disk('public')->delete($tournament->logo);
            }
            $validated['logo'] = $request->file('logo')->store('tournaments/logos', 'public');
        }

        $tournament->update($validated);

        return redirect()
            ->route('admin.tournaments.index')
            ->with('success', 'Tournament updated successfully.');
    }


    public function destroy(Tournament $tournament)
    {
        $tournament->delete();

        return redirect()
            ->route('admin.tournaments.index')
            ->with('success', 'Tournament deleted successfully.');
    }
}

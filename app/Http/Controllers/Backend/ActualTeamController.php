<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ActualTeam;
use App\Models\AuctionPlayer;
use App\Models\Organization;
use App\Models\Player;
use App\Models\Role;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ActualTeamController extends Controller
{
    public function index()
    {
        $query = ActualTeam::with(['organization', 'tournament']);

        // If the logged-in user is NOT super-admin, filter by their organization
        if (!auth()->user()->hasRole('super-admin')) {
            $query->where('organization_id', auth()->user()->organization_id);
        }

        $actualTeams = $query->paginate(15);

        return view('backend.pages.actual_teams.index', compact('actualTeams'));
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
            // If they are a Superadmin, get ALL organizations and tournaments.
            $organizations = Organization::all();
            $tournaments = Tournament::all();
        } else {
            // For any OTHER user (Admin, Organizer, etc.), scope the data by their organization_id.

            // Safety check: ensure the non-admin user is actually assigned to an organization.
            if (!$user->organization_id) {
                // If not, they can't create a team. Redirect back with an informative error.
                return redirect()->back()->with('error', 'You are not assigned to an organization and cannot create a team.');
            }

            // Get ONLY the organization that belongs to this user.
            // We use ->get() to ensure the result is a collection, keeping the view logic consistent.
            $organizations = Organization::where('id', $user->organization_id)->get();

            // And get ONLY the tournaments that belong to that same organization.
            $tournaments = Tournament::where('organization_id', $user->organization_id)->get();
        }

        // 3. Return the view with the correctly scoped data.
        // The view itself doesn't need to change at all.
        return view('backend.pages.actual_teams.create', compact('organizations', 'tournaments'));
    }
    public function store(Request $request)
    {
        $data = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'tournament_id' => 'required|exists:tournaments,id',
            'name' => 'required|string|max:255',
        ]);

        ActualTeam::create($data);

        return redirect()->route('admin.actual-teams.index')->with('success', 'Actual Team created successfully.');
    }

    public function show(ActualTeam $actualTeam)
    {
        $this->authorize('actual-team.view');

        // **THIS IS THE FIX**
        // We tell Eloquent: "When you get the team, also get its organization,
        // its tournament, all of its users, AND for each of those users,
        // get their associated player record."
        // This solves the N+1 query problem.
        $actualTeam->load(['organization', 'tournament', 'users.player']);

        return view('backend.pages.actual_teams.show', compact('actualTeam'));
    }
    // public function edit(ActualTeam $actualTeam)
    // {
    //     // Authorization check: Ensure user can edit this team
    //     if (
    //         !auth()->user()->hasRole('Superadmin') && // Corrected to 'Superadmin'
    //         $actualTeam->organization_id !== auth()->user()->organization_id
    //     ) {
    //         abort(403, 'You are not authorized to edit this team.');
    //     }

    //     // --- Data Loading ---

    //     // Get lists for dropdowns (organizations, tournaments, roles)
    //     $organizations = Organization::all();
    //     $tournaments = Tournament::all();
    //     $roles = Role::whereNotIn('name', [
    //         'SuperAdmin',
    //         'Admin',
    //         'Contact',
    //         'Subscriber',
    //         'Viewer',
    //         'Editor'
    //     ])->get();

    //     // Eager load the users currently on the team for efficiency
    //     $actualTeam->load('users');

    //     // Get the IDs of users already on THIS team.
    //     $currentTeamUserIds = $actualTeam->users->pluck('id')->toArray();

    //     // Get the members of the current team to display in the "Current Squad" list
    //     $currentMembers = $actualTeam->users; // We can just use the loaded relationship

    //     // --- Main Logic: Get AVAILABLE Users ---

    //     // First, find all user IDs that are already assigned to ANY other team in the system.
    //     $assignedToOtherTeamIds = DB::table('actual_team_users')
    //         ->where('actual_team_id', '!=', $actualTeam->id)
    //         ->pluck('user_id')
    //         ->toArray();

    //     // **THIS IS THE KEY FIX**
    //     // Now, combine the two exclusion lists:
    //     // 1. Users on THIS team.
    //     // 2. Users on ANY OTHER team.
    //     $allExcludedUserIds = array_unique(array_merge($currentTeamUserIds, $assignedToOtherTeamIds));

    //     // Start building the query for available users
    //     $usersQuery = User::query();

    //     // Apply role-based scoping
    //     if (auth()->user()->hasRole('Superadmin')) { // Corrected to 'Superadmin'
    //         // Superadmin can see all users not already on a team.
    //         $usersQuery->whereNotIn('id', $allExcludedUserIds);
    //     } else {
    //         // Other users see only users from their own organization who are not on a team.
    //         $authUser = auth()->user();
    //         $usersQuery->where('organization_id', $authUser->organization_id)
    //             ->whereNotIn('id', $allExcludedUserIds)
    //             ->whereDoesntHave('roles', function ($query) {
    //                 $query->whereIn('name', ['Superadmin', 'Admin']);
    //             });
    //     }

    //     // Execute the query to get the final list of available users
    //     $users = $usersQuery->get();


    //     // --- Return the View ---

    //     return view('backend.pages.actual_teams.edit', compact(
    //         'actualTeam',
    //         'organizations',
    //         'tournaments',
    //         'roles',
    //         'users',       // This is now the correctly filtered list
    //         'currentMembers'
    //     ));
    // }
    public function edit(ActualTeam $actualTeam)
    {
        // Authorization check
        if (
            !auth()->user()->hasRole('Superadmin') &&
            $actualTeam->organization_id !== auth()->user()->organization_id
        ) {
            abort(403, 'You are not authorized to edit this team.');
        }

        // --- Data Loading ---
        $organizations = Organization::all();
        $tournaments = Tournament::all();
        $roles = Role::whereNotIn('name', [
            'SuperAdmin',
            'Admin',
            'Contact',
            'Subscriber',
            'Viewer',
            'Editor'
        ])->get();

        $actualTeam->load('users');
        $currentMembers = $actualTeam->users;
        $currentTeamUserIds = $currentMembers->pluck('id')->toArray();

        // --- Main Logic: Get AVAILABLE Users ---

        // Find all user IDs assigned to ANY other team.
        $assignedToOtherTeamIds = DB::table('actual_team_users')
            ->where('actual_team_id', '!=', $actualTeam->id)
            ->pluck('user_id')
            ->toArray();

        // Combine all user IDs that should be excluded.
        $allExcludedUserIds = array_unique(array_merge($currentTeamUserIds, $assignedToOtherTeamIds));

        // Start building the query for available users.
        $usersQuery = User::query();

        // **THIS IS THE REQUIRED FIX**
        // The query now has two main parts, grouped together.
        $usersQuery->where(function ($query) {
            // Condition 1: Include users who ARE 'Player' role AND have a welcome email sent.
            $query->whereHas('roles', function ($subQuery) {
                $subQuery->where('name', 'Player');
            })
                ->whereHas('player', function ($subQuery) {
                    $subQuery->whereNotNull('welcome_email_sent_at');
                });

            // OR

            // Condition 2: Include users who are NOT 'Player' role.
            $query->orWhere(function ($subQuery) {
                $subQuery->whereDoesntHave('roles', function ($roleQuery) {
                    $roleQuery->where('name', 'Player');
                });
            });
        });


        // Apply role-based scoping (Superadmin vs. regular user)
        if (auth()->user()->hasRole('Superadmin')) {
            // Superadmin sees all eligible users not on a team.
            $usersQuery->whereNotIn('id', $allExcludedUserIds);
        } else {
            // Other users see eligible users from their org who aren't on a team or privileged.
            $authUser = auth()->user();
            $usersQuery->where('organization_id', $authUser->organization_id)
                ->whereNotIn('id', $allExcludedUserIds)
                ->whereDoesntHave('roles', function ($query) {
                    $query->whereIn('name', ['Superadmin', 'Admin']);
                });
        }

        // Execute the query to get the final list of available users.
        $users = $usersQuery->get();

        // --- Return the View ---
        return view('backend.pages.actual_teams.edit', compact(
            'actualTeam',
            'organizations',
            'tournaments',
            'roles',
            'users',
            'currentMembers'
        ));
    }
    public function update(Request $request, ActualTeam $actualTeam)
    {
        // 1. Authorize the action
        $this->authorize('actual-team.edit');

        // 2. Validate all incoming data
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'organization_id' => 'required|exists:organizations,id',
            'tournament_id' => 'required|exists:tournaments,id',
            'members' => 'nullable|array',
            'members.*' => 'exists:users,id',
            'user_roles' => 'nullable|array',
            'user_roles.*' => 'nullable|string|exists:roles,name', // Ensures the role exists in Spatie's roles table
        ]);

        // 3. Update the main team details
        $actualTeam->update([
            'name' => $validated['name'],
            'organization_id' => $validated['organization_id'],
            'tournament_id' => $validated['tournament_id'],
        ]);

        // 4. Prepare data for syncing the pivot table
        $syncData = [];
        if (!empty($validated['members'])) {
            foreach ($validated['members'] as $memberId) {
                $role = $request->input("user_roles.{$memberId}", 'Player');
                $syncData[$memberId] = ['role' => $role];

                // -----------------------------------------------------------------
                // --- THIS IS THE FIX: Sync the user's system-wide Spatie role ---
                // -----------------------------------------------------------------

                // Find the user model instance
                $user = User::find($memberId);

                if ($user) {
                    // **Safety Check:** Prevent accidentally demoting an Admin or Superadmin.
                    // We should only sync roles for non-privileged users from this interface.
                    if (!$user->hasAnyRole(['Superadmin', 'Admin'])) {

                        // `syncRoles` is the best method. It removes all other roles the user might have
                        // and assigns only the one provided. This keeps things clean.
                        $user->syncRoles([$role]);
                    }
                }
            }
        }

        // 5. Synchronize the `actual_team_users` pivot table
        // This part remains the same, as it correctly updates the team's roster.
        $actualTeam->users()->sync($syncData);

        // 6. Redirect with a success message
        return redirect()->back()->with('success', 'Team roster and user permissions updated successfully.');
    }


    public function destroy(ActualTeam $actualTeam)
    {
        $actualTeam->delete();
        return redirect()->route('admin.actual-teams.index')->with('success', 'Actual Team deleted successfully.');
    }


    public function removeMember(Request $request, ActualTeam $actualTeam)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $deleted = DB::table('actual_team_users')
            ->where('actual_team_id', $actualTeam->id)
            ->where('user_id', $request->user_id)
            ->delete();

        if ($deleted) {
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'Member not found in this team.'], 404);
    }
}

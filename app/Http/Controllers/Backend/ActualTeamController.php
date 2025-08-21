<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ActualTeam;
use App\Models\Auction;
use App\Models\AuctionPlayer;
use App\Models\Organization;
use App\Models\Player;
use App\Models\Role;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ActualTeamController extends Controller
{
    public function index()
    {

        $user = Auth::user();
        $filters = [
            'organization_id' => request('organization_id'),
            'tournament_id' => request('tournament_id'),
        ];

        // Base query
        $query = ActualTeam::with(['organization', 'tournament', 'auction']);
        $query->applyFilters($filters);

        // Fetch all teams for pagination
        $actualTeams = $query->latest()->paginate(15);

        // Editable teams for Team Manager
        $editableTeamIds = [];
        $teamManagerTeamIds = [];
        if ($user->hasRole('Team Manager')) {
            $editableTeamIds = $user->actualTeams->pluck('id')->toArray();
            $teamManagerTeamIds = $editableTeamIds; // used for ordering
        }

        // Prepare filter dropdowns
        if ($user->hasRole('Superadmin')) {
            $organizations = Organization::orderBy('name')->get();
            $tournaments = Tournament::orderBy('name')->get();
        } elseif ($user->hasRole('Team Manager')) {
            $managedTeams = $user->actualTeams;
            $organizationIds = $managedTeams->pluck('organization_id')->unique();
            $tournamentIds = $managedTeams->pluck('tournament_id')->unique();

            $organizations = Organization::whereIn('id', $organizationIds)->orderBy('name')->get();
            $tournaments = Tournament::whereIn('id', $tournamentIds)->orderBy('name')->get();
        } else {
            $organizations = Organization::where('id', $user->organization_id)->get();
            $tournaments = Tournament::where('organization_id', $user->organization_id)->orderBy('name')->get();
        }

        // Calculate total spent per team
        // Calculate total spent per team and auctioned players count
        $teamBudgets = [];
        foreach ($actualTeams as $team) {
            $auction = Auction::first(); // get the auction related to this team
            $maxBudget = $auction->max_budget_per_team ?? 0;

            // Count only users in this team who were actually bought in the auction
            $auctionedUserCount = DB::table('auction_bids')
                ->where('auction_id', $auction->id)
                ->where('team_id', $team->id)
                ->distinct('user_id')
                ->count('user_id');

            // Total spent by the team
            $totalSpent = DB::table('auction_bids')
                ->where('auction_id', $auction->id)
                ->where('team_id', $team->id)
                ->sum('amount');

            $teamBudgets[$team->id] = [
                'spent' => number_format($totalSpent / 1000000, 2),
                'max_budget' => number_format($maxBudget / 1000000, 2),
                'user_count' => $auctionedUserCount, // only auctioned players
            ];
        }


        // Reorder: Team Manager's teams first
        if (!empty($teamManagerTeamIds)) {
            $sortedTeams = $actualTeams->getCollection()->sortByDesc(function ($team) use ($teamManagerTeamIds) {
                return in_array($team->id, $teamManagerTeamIds) ? 1 : 0;
            });

            // Rebuild paginated collection
            $actualTeams = new \Illuminate\Pagination\LengthAwarePaginator(
                $sortedTeams->values(),          // the sorted items
                $actualTeams->total(),           // total items
                $actualTeams->perPage(),         // items per page
                $actualTeams->currentPage(),     // current page
                ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath()]
            );
        }


        return view('backend.pages.actual_teams.index', compact(
            'actualTeams',
            'organizations',
            'tournaments',
            'editableTeamIds',
            'teamBudgets'
        ));
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

        // 1. Validate the incoming request data
        // This part is mostly correct, but let's make the logo nullable for flexibility.
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'tournament_id'   => 'required|exists:tournaments,id',
            'name'            => 'required|string|max:255|unique:actual_teams,name', // Added unique rule
            'team_logo'       => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // 2MB Max
        ]);

        // **THIS IS THE FIX**
        // 2. Handle the file upload
        if ($request->hasFile('team_logo')) {
            // Store the uploaded file in the 'public' disk, inside a 'team-logos' folder.
            // The `store()` method returns the unique path to the stored file.
            $logoPath = $request->file('team_logo')->store('team-logos', 'public');

            // Add the file path to the data that will be saved to the database.
            $validated['team_logo'] = $logoPath;
        }

        // 3. Create the new team record in the database
        // The $validated array now contains the correct file path for the team_logo column.
        ActualTeam::create($validated);

        // 4. Redirect with a success message
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
        $actualTeam->load([
            'organization',
            'tournament',
            'users.player.playerType',      // For each user, get their player profile and its type
            'users.player.battingProfile',  // Also get their batting profile
            'users.player.bowlingProfile',  // And their bowling profile
        ]);
        return view('backend.pages.actual_teams.show', compact('actualTeam'));
    }

    // public function edit(ActualTeam $actualTeam)
    // {
    //     // Authorization check
    //     if (
    //         !auth()->user()->hasRole('Superadmin') &&
    //         $actualTeam->organization_id !== auth()->user()->organization_id
    //     ) {
    //         abort(403, 'You are not authorized to edit this team.');
    //     }

    //     // --- Data Loading ---
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

    //     $actualTeam->load('users');
    //     $currentMembers = $actualTeam->users;
    //     $currentTeamUserIds = $currentMembers->pluck('id')->toArray();


    //     $currentPlayerMembers = $currentMembers->filter(function ($member) {
    //         // Check if the user has the 'Player' role in the current team context
    //         return $member->pivot->role === 'Player';
    //     });
    //     // --- Main Logic: Get AVAILABLE Users ---

    //     // Find all user IDs assigned to ANY other team.
    //     $assignedToOtherTeamIds = DB::table('actual_team_users')
    //         ->where('actual_team_id', '!=', $actualTeam->id)
    //         ->pluck('user_id')
    //         ->toArray();

    //     // Combine all user IDs that should be excluded.
    //     $allExcludedUserIds = array_unique(array_merge($currentTeamUserIds, $assignedToOtherTeamIds));

    //     // Start building the query for available users.
    //     $usersQuery = User::query();

    //     // **THIS IS THE REQUIRED FIX**
    //     // The query now has two main parts, grouped together.
    //     $usersQuery->where(function ($query) {
    //         // Condition 1: Include users who ARE 'Player' role AND have a welcome email sent.
    //         $query->whereHas('roles', function ($subQuery) {
    //             $subQuery->where('name', 'Player');
    //         })
    //             ->whereHas('player', function ($subQuery) {
    //                 $subQuery->whereNotNull('welcome_email_sent_at');
    //             });

    //         // OR

    //         // Condition 2: Include users who are NOT 'Player' role.
    //         $query->orWhere(function ($subQuery) {
    //             $subQuery->whereDoesntHave('roles', function ($roleQuery) {
    //                 $roleQuery->where('name', 'Player');
    //             });
    //         });
    //     });


    //     // Apply role-based scoping (Superadmin vs. regular user)
    //     if (auth()->user()->hasRole('Superadmin')) {
    //         // Superadmin sees all eligible users not on a team.
    //         $usersQuery->whereNotIn('id', $allExcludedUserIds);
    //     } else {
    //         // Other users see eligible users from their org who aren't on a team or privileged.
    //         $authUser = auth()->user();
    //         $usersQuery->where('organization_id', $authUser->organization_id)
    //             ->whereNotIn('id', $allExcludedUserIds)
    //             ->whereDoesntHave('roles', function ($query) {
    //                 $query->whereIn('name', ['Superadmin', 'Admin']);
    //             });
    //     }

    //     // Execute the query to get the final list of available users.
    //     $users = $usersQuery->get();

    //     // --- Return the View ---
    //     return view('backend.pages.actual_teams.edit', compact(
    //         'actualTeam',
    //         'organizations',
    //         'tournaments',
    //         'roles',
    //         'users',
    //         'currentMembers',
    //         'currentPlayerMembers' // Use this filtered collection

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

        // Filter roles that should be available for selection in the UI
        $availableRolesForSelection = Role::whereNotIn('name', [
            'Contact',
            'Subscriber',
            'Viewer',
            'Editor',
            'Admin',
            'SuperAdmin'
        ])->get();


        // --- Get CURRENT Members with their PIVOT data ---
        // This fetches all users linked to the actualTeam via the pivot table,
        $currentMembers = $actualTeam->members()->get();

        // --- Filter 1: Current Players (Squad) ---
        $currentPlayerMembers = $currentMembers->filter(function ($member) {
            // We rely on the role saved in the pivot table ('actual_team_users')
            return $member->pivot && $member->pivot->role === 'Player';
        });

        // --- Filter 2: Current Staff (Non-Players) ---
        $currentStaffMembers = $currentMembers->filter(function ($member) {
            // Filter for everyone whose pivot role is NOT 'Player'
            // We should also check for the existence of the pivot data
            return $member->pivot && $member->pivot->role !== 'Player';
        });


        // --- Logic to get AVAILABLE Users (as you had it) ---

        // Find all user IDs assigned to ANY other team.
        // Adjust table names if yours are different (e.g., 'team_user' instead of 'actual_team_users')
        $assignedToOtherTeamIds = DB::table('actual_team_users')
            ->where('actual_team_id', '!=', $actualTeam->id)
            ->pluck('user_id')
            ->toArray();

        // Combine all user IDs that should be excluded from the 'available' list.
        $currentTeamUserIds = $currentMembers->pluck('id')->toArray(); // Get IDs from already loaded current members


        $allExcludedUserIds = array_unique(array_merge($currentTeamUserIds, $assignedToOtherTeamIds));

        // Start building the query for available users.
        $usersQuery = User::query();

        // Apply the complex filtering logic for eligibility
        $usersQuery->where(function ($query) {
            // Condition 1: Include users who ARE 'Player' role AND have a welcome email sent.
            $query->whereHas('roles', function ($subQuery) {
                $subQuery->where('name', 'Player');
            })
                // Assumes a 'player' relationship to a 'player_profiles' table or similar
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

        // Apply role-based scoping (Superadmin vs. regular user) for the 'available users' list
        if (auth()->user()->hasRole('Superadmin')) {
            // Superadmin sees all eligible users not on any team.
            $usersQuery->whereNotIn('id', $allExcludedUserIds);
        } else {
            // Other users see eligible users from their org who aren't on a team or privileged.
            $authUser = auth()->user();
            $usersQuery->where('organization_id', $authUser->organization_id)
                ->whereNotIn('id', $allExcludedUserIds)
                ->whereDoesntHave('roles', function ($query) {
                    $query->whereIn('name', ['Superadmin', 'Admin']); // Exclude admins/superadmins from non-superadmin views
                });
        }

        // Execute the query to get the final list of available users.
        $availableUsers = $usersQuery->get();
        $allRolesForCombobox = Role::all(); // Fetch all roles for the dropdown
        // --- Return the View ---
        // Pass the filtered roles for the select dropdowns
        // Pass the current members with their pivot data
        // Pass the calculated available users
        return view('backend.pages.actual_teams.edit', compact(
            'actualTeam',
            'organizations',
            'tournaments',
            'availableRolesForSelection',
            'allRolesForCombobox',        // All roles available for existing members' role changes

            'availableUsers',
            'currentMembers', // This is the array of all current members with pivot data
            'currentPlayerMembers', // This is the array of current members filtered to be only players
            'currentStaffMembers'
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
            'team_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'members' => 'nullable|array',
            'members.*' => 'exists:users,id',
            'user_roles' => 'nullable|array',
            'user_roles.*' => 'nullable|string|exists:roles,name',
        ]);


        // 3. Handle the Team Logo Upload
        if ($request->hasFile('team_logo')) {
            if ($actualTeam->team_logo) {
                Storage::disk('public')->delete($actualTeam->team_logo);
            }
            $validated['team_logo'] = $request->file('team_logo')->store('team-logos', 'public');
        }

        // 4. Update the main team details
        $actualTeam->update($validated);

        // 5. Prepare data for syncing and get the list of current and new member IDs
        $syncData = [];
        $newMemberIds = !empty($validated['members']) ? $validated['members'] : [];

        foreach ($newMemberIds as $memberId) {
            $role = $request->input("user_roles.{$memberId}", 'Player');
            $syncData[$memberId] = ['role' => $role];

            // Sync the user's system-wide Spatie role
            $user = User::find($memberId);
            if ($user && !$user->hasAnyRole(['Superadmin', 'Admin'])) {
                $user->syncRoles([$role]);
            }
        }

        // =================================================================
        // 6. **THE FIX**: Perform the sync and manage player statuses
        // =================================================================

        // First, get the list of member IDs *before* the sync
        $originalMemberIds = $actualTeam->users()->pluck('users.id')->toArray();

        // Now, synchronize the pivot table. This returns arrays of attached, detached, and updated IDs.
        $syncResult = $actualTeam->users()->sync($syncData);
        $detachedIds = $syncResult['detached'];

        // a) Update newly added/retained members to 'Retained'
        if (!empty($newMemberIds)) {
            Player::whereIn('user_id', $newMemberIds)
                ->update(['player_mode' => 'retained']); // Corrected column name to 'player_mode'
        }

        // b) Update REMOVED members back to 'Normal'
        if (!empty($detachedIds)) {
            Player::whereIn('user_id', $detachedIds)
                ->update(['player_mode' => 'normal']);
        }

        // 7. Redirect with a success message
        return redirect()->back()->with('success', 'Team details, roster, and player statuses updated successfully.');
    }


    public function destroy(ActualTeam $actualTeam)
    {
        $actualTeam->delete();
        return redirect()->route('admin.actual-teams.index')->with('success', 'Actual Team deleted successfully.');
    }


    public function addMember(Request $request, ActualTeam $actualTeam)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'roles'   => 'required|array|min:1',
            'roles.*' => 'string',
            // Allow "retained" or "normal" string, or boolean
            'retained' => 'nullable|string|in:retained,normal,true,false,1,0',
        ]);

        $userId = $request->user_id;
        $inputRoles = $request->roles;

        // Normalize roles
        $roles = array_map('strtolower', $inputRoles);
        $isPlayer = in_array('player', $roles);

        // Normalize retained input
        $retainedInput = $request->input('retained');
        $isRetained = in_array(strtolower((string) $retainedInput), ['1', 'true', 'retained'], true);

        try {
            DB::beginTransaction();

            // 🔹 Check retained player limit (only for players)
            if ($isPlayer && $isRetained) {
                $retainedCount = DB::table('players')
                    ->where('actual_team_id', $actualTeam->id)
                    ->where('player_mode', 'retained')
                    ->count();

                // Check if this user is already retained (editing case)
                $alreadyRetained = DB::table('players')
                    ->where('actual_team_id', $actualTeam->id)
                    ->where('user_id', $userId)
                    ->where('player_mode', 'retained')
                    ->exists();

                if (!$alreadyRetained && $retainedCount >= 4) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Maximum 4 retained players are allowed per team.',
                    ], 422);
                }
            }

            $user = User::findOrFail($userId);

            // Sync roles
            $user->syncRoles($roles);

            // Attach to team
            DB::table('actual_team_users')->updateOrInsert(
                [
                    'actual_team_id' => $actualTeam->id,
                    'user_id'        => $userId,
                ],
                ['updated_at' => now()]
            );

            if ($isPlayer) {
                $player = $user->player;

                if ($player) {
                    $player->player_mode = $isRetained ? 'retained' : 'normal';
                    $player->actual_team_id = $actualTeam->id;
                    $player->save();
                } else {
                    Log::warning("User {$userId} added as Player to team {$actualTeam->id}, but no player record found.");
                    // Optionally create the record if needed
                }
            } else {
                // Non-player: reset player details if previously attached
                $player = $user->player;
                if ($player && $player->actual_team_id === $actualTeam->id) {
                    $player->player_mode = 'normal';
                    $player->actual_team_id = null;
                    $player->save();
                }
            }

            DB::commit();

            // Reload user with roles
            $userData = User::with('roles')->findOrFail($userId);
            $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($userData->name) . "&color=7F9CF5&background=EBF4FF";

            return response()->json([
                'success' => true,
                'message' => 'Member added successfully.',
                'user'    => [
                    'id'       => $userId,
                    'name'     => $userData->name,
                    'email'    => $userData->email,
                    'roles'    => $userData->roles->pluck('name')->toArray(),
                    'avatar'   => $avatarUrl,
                    'retained' => $isRetained ? 'retained' : 'normal',
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Error adding member to team {$actualTeam->id}: {$e->getMessage()} \n {$e->getTraceAsString()}");
            return response()->json([
                'success' => false,
                'message' => 'Failed to add member. Please try again.',
                'error_details' => $e->getMessage(),
            ], 500);
        }
    }




    /**
     * Removes a member from the actual team.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ActualTeam  $actualTeam
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeMember(ActualTeam $actualTeam, User $user)
    {
        $userId = $user->id;

        try {
            DB::beginTransaction();

            // 1. Check if the user is actually a member of this team
            $isMember = DB::table('actual_team_users')
                ->where('actual_team_id', $actualTeam->id)
                ->where('user_id', $userId)
                ->exists();

            if (!$isMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not a member of this team.',
                ], 400);
            }

            // Fetch user roles before detaching
            $userRoles = $user->roles->pluck('name')->map(fn($r) => strtolower($r))->toArray();
            $isPlayer = in_array('player', $userRoles);

            // 2. Detach from pivot
            $actualTeam->members()->detach($userId);

            // 3. If user is player, reset player data
            if ($isPlayer && $user->player) {
                $user->player->update([
                    'player_mode'    => 'unassigned', // or default
                    'actual_team_id' => null,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Member removed successfully.',
                'user_id' => $userId,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Error removing member {$userId} from team {$actualTeam->id}: {$e->getMessage()}");

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove member. Please try again.',
            ], 500);
        }
    }
}

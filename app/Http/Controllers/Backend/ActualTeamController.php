<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Mail\TeamManagerCredentialsMail;
use App\Models\ActualTeam;
use App\Models\Auction;
use App\Models\AuctionPlayer;
use App\Models\Organization;
use App\Models\Player;
use App\Models\Role;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;
use App\Services\ImageBackgroundRemovalService;
use App\Services\LogoProcessingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ActualTeamController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $filters = [
            'organization_id' => request('organization_id'),
            'tournament_id' => request('tournament_id'),
            'search' => request('search'),
        ];

        // Base query — eager load tournaments pivot as well
        $query = ActualTeam::with(['organization', 'tournament', 'auction', 'tournaments']);

        // Filter teams based on user role
        if ($user->hasRole('Superadmin')) {
            // Superadmin sees all teams
            $query->applyFilters($filters);
        } elseif ($user->organization_id) {
            // Admin/Organizer sees all teams in their org
            $query->where('organization_id', $user->organization_id)->applyFilters($filters);
        } else {
            // Team Manager and others see only their assigned teams (via actual_team_users pivot)
            $teamIds = $user->actualTeams->pluck('id')->toArray();
            $query->whereIn('id', $teamIds);
            $query->applyFilters($filters);
        }

        // Fetch teams for pagination
        $actualTeams = $query->latest()->paginate(15);

        // Editable teams based on role
        $editableTeamIds = [];
        $teamManagerTeamIds = [];
        if ($user->hasRole('Superadmin') || $user->organization_id) {
            $editableTeamIds = $actualTeams->pluck('id')->toArray();
        } else {
            // Team Manager can edit their assigned teams
            $editableTeamIds = $user->actualTeams->pluck('id')->toArray();
            $teamManagerTeamIds = $editableTeamIds;
        }

        // Prepare filter dropdowns
        if ($user->hasRole('Superadmin')) {
            $organizations = Organization::orderBy('name')->get();
            $tournaments = Tournament::orderBy('name')->get();
        } elseif ($user->organization_id) {
            $organizations = Organization::where('id', $user->organization_id)->orderBy('name')->get();
            $tournaments = Tournament::where('organization_id', $user->organization_id)->orderBy('name')->get();
        } else {
            $managedTeams = $user->actualTeams;
            $organizationIds = $managedTeams->pluck('organization_id')->unique();
            $tournamentIds = $managedTeams->pluck('tournament_id')->unique();

            $organizations = Organization::whereIn('id', $organizationIds)->orderBy('name')->get();
            $tournaments = Tournament::whereIn('id', $tournamentIds)->orderBy('name')->get();
        }

        // Calculate total spent per team and auctioned players count
        $teamBudgets = [];
        $auction = Auction::first();

        foreach ($actualTeams as $team) {
            if ($auction) {
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
                    'user_count' => $auctionedUserCount,
                ];
            } else {
                $teamBudgets[$team->id] = [
                    'spent' => '0.00',
                    'max_budget' => '0.00',
                    'user_count' => 0,
                ];
            }
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
        $teamScope = $request->input('team_scope', 'tournament');

        $rules = [
            'organization_id'  => 'required|exists:organizations,id',
            'name'             => 'required|string|max:255|unique:actual_teams,name',
            'team_logo'        => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'short_name'       => 'nullable|string|max:50',
            'location'         => 'nullable|string|max:100',
            'primary_color'    => 'nullable|string|max:7',
            'secondary_color'  => 'nullable|string|max:7',
            'sponsor_logo'     => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'captain_image'    => 'nullable|string|max:500',
            'team_scope'       => 'required|in:tournament,global',
        ];

        if ($teamScope === 'tournament') {
            $rules['tournament_ids']   = 'required|array|min:1';
            $rules['tournament_ids.*'] = 'exists:tournaments,id';
        }

        $validated = $request->validate($rules);

        // Handle the file upload — prefer cropped data, fallback to raw file
        if ($request->filled('team_logo_cropped')) {
            $validated['team_logo'] = LogoProcessingService::processBase64Logo($request->input('team_logo_cropped'), 'team-logos');
        } elseif ($request->hasFile('team_logo')) {
            $validated['team_logo'] = LogoProcessingService::processLogo($request->file('team_logo'), 'team-logos');
        }

        // Handle Sponsor Logo Upload
        if ($request->hasFile('sponsor_logo')) {
            $validated['sponsor_logo'] = $request->file('sponsor_logo')->store('team-sponsors', 'public');
        }

        // Handle Captain Image — processed path from player-image-upload component
        if ($request->filled('captain_image')) {
            $captainPath = $request->input('captain_image');
            if (Storage::disk('public')->exists($captainPath)) {
                $validated['captain_image'] = $captainPath;
            }
        }

        unset($validated['team_scope']);

        if ($teamScope === 'global') {
            $validated['is_global'] = true;
            $validated['tournament_id'] = null;
            unset($validated['tournament_ids']);

            $team = ActualTeam::create($validated);
        } else {
            $validated['is_global'] = false;
            $tournamentIds = $validated['tournament_ids'];
            $validated['tournament_id'] = $tournamentIds[0];
            unset($validated['tournament_ids']);

            $team = ActualTeam::create($validated);
            $team->tournaments()->sync($tournamentIds);
        }

        return redirect()->route('admin.actual-teams.index')->with('success', 'Team created successfully.');
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


        // --- Logic to get AVAILABLE Users ---

        // Only exclude users already on THIS team (not other teams — a user can be on multiple teams)
        $currentTeamUserIds = $currentMembers->pluck('id')->toArray();
        $allExcludedUserIds = $currentTeamUserIds;

        // Start building the query for available users.
        $usersQuery = User::query();

        // Apply the complex filtering logic for eligibility
        $usersQuery->where(function ($query) {
            // Condition 1: Include users who have an approved player record
            $query->whereHas('player', function ($subQuery) {
                $subQuery->where('status', 'approved');
            });

            // OR

            // Condition 2: Include users who are NOT 'Player' role (staff, managers, etc.)
            $query->orWhere(function ($subQuery) {
                $subQuery->whereDoesntHave('roles', function ($roleQuery) {
                    $roleQuery->where('name', 'Player');
                });
            });
        });

        // Apply role-based scoping (Superadmin vs. regular user) for the 'available users' list
        // Always exclude Superadmin and Admin users from the available list
        $usersQuery->whereDoesntHave('roles', function ($query) {
            $query->whereIn('name', ['Superadmin', 'Admin']);
        });

        if (auth()->user()->hasRole('Superadmin')) {
            $usersQuery->whereNotIn('id', $allExcludedUserIds);
        } else {
            $authUser = auth()->user();
            $usersQuery->where('organization_id', $authUser->organization_id)
                ->whereNotIn('id', $allExcludedUserIds);
        }

        // Execute the query to get the final list of available users.
        $availableUsers = $usersQuery->get();
        // Exclude admin roles from the roles dropdown
        $allRolesForCombobox = Role::whereNotIn('name', ['Superadmin', 'Admin'])->get();

        // Get auction for this team's tournament (if exists)
        $teamAuction = Auction::where('tournament_id', $actualTeam->tournament_id)
            ->where('status', '!=', 'completed')
            ->first();

        // Get all available auctions (for linking team to correct tournament)
        $availableAuctions = Auction::with('tournament')
            ->where('status', '!=', 'completed')
            ->get();

        // --- Get Registered Players for Captain Selection ---
        $currentMemberIds = $currentMembers->pluck('id')->toArray();
        $registeredPlayersForCaptain = collect();
        if ($actualTeam->tournament_id) {
            $registeredPlayersForCaptain = TournamentRegistration::where('tournament_id', $actualTeam->tournament_id)
                ->approved()
                ->players()
                ->whereHas('player.user')
                ->with('player.user')
                ->get()
                ->map(fn ($reg) => $reg->player->user)
                ->filter(fn ($user) => !in_array($user->id, $currentMemberIds))
                ->unique('id')
                ->values();
        }

        // Get current tournament IDs from pivot (for multi-select)
        $selectedTournamentIds = $actualTeam->tournaments()->pluck('tournaments.id')->toArray();
        // Ensure primary tournament_id is included
        if ($actualTeam->tournament_id && !in_array($actualTeam->tournament_id, $selectedTournamentIds)) {
            $selectedTournamentIds[] = $actualTeam->tournament_id;
        }

        // --- Player Roster (from new pivot table) ---
        $effectiveTournaments = $actualTeam->effective_tournaments;

        // Players grouped by tournament from the player_actual_team_tournament pivot
        $teamPlayers = DB::table('player_actual_team_tournament')
            ->where('actual_team_id', $actualTeam->id)
            ->get();

        $teamPlayersByTournament = $teamPlayers->groupBy('tournament_id');

        // Get all player details
        $playerIds = $teamPlayers->pluck('player_id')->unique()->toArray();
        $playersMap = Player::whereIn('id', $playerIds)->get()->keyBy('id');

        // Get all teams for each effective tournament (for the playing-team dropdown)
        $allTeamsForTournaments = [];
        foreach ($effectiveTournaments as $t) {
            $allTeamsForTournaments[$t->id] = ActualTeam::where(function ($q) use ($t) {
                $q->whereHas('tournaments', fn($sub) => $sub->where('tournaments.id', $t->id))
                  ->orWhere('tournament_id', $t->id)
                  ->orWhere(function ($sub) use ($t) {
                      $sub->where('is_global', true)->where('organization_id', $t->organization_id);
                  });
            })->get();
        }

        // --- Return the View ---
        return view('backend.pages.actual_teams.edit', compact(
            'actualTeam',
            'organizations',
            'tournaments',
            'selectedTournamentIds',
            'availableRolesForSelection',
            'allRolesForCombobox',
            'availableUsers',
            'currentMembers',
            'currentPlayerMembers',
            'currentStaffMembers',
            'registeredPlayersForCaptain',
            'teamAuction',
            'availableAuctions',
            'effectiveTournaments',
            'teamPlayersByTournament',
            'playersMap',
            'allTeamsForTournaments'
        ));
    }

    public function update(Request $request, ActualTeam $actualTeam)
    {
        // 1. Authorize the action
        $this->authorize('actual-team.edit');

        $teamScope = $request->input('team_scope', 'tournament');

        // 2. Validate all incoming data
        $rules = [
            'name' => 'required|string|max:255',
            'short_name' => 'nullable|string|max:50',
            'location' => 'nullable|string|max:100',
            'organization_id' => 'required|exists:organizations,id',
            'team_scope' => 'required|in:tournament,global',
            'team_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'sponsor_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'captain_image' => 'nullable|string|max:500',
            'primary_color' => 'nullable|string|max:7',
            'secondary_color' => 'nullable|string|max:7',
            'captain_user_id' => 'nullable|exists:users,id',
        ];

        if ($teamScope === 'tournament') {
            $rules['tournament_ids']   = 'required|array|min:1';
            $rules['tournament_ids.*'] = 'exists:tournaments,id';
        }

        $validated = $request->validate($rules);

        // 3. Handle the Team Logo Upload — prefer cropped data, fallback to raw file
        if ($request->filled('team_logo_cropped')) {
            $validated['team_logo'] = LogoProcessingService::processBase64Logo($request->input('team_logo_cropped'), 'team-logos', $actualTeam->team_logo);
        } elseif ($request->hasFile('team_logo')) {
            $validated['team_logo'] = LogoProcessingService::processLogo($request->file('team_logo'), 'team-logos', $actualTeam->team_logo);
        }

        // Handle Sponsor Logo Upload
        if ($request->hasFile('sponsor_logo')) {
            if ($actualTeam->sponsor_logo) {
                Storage::disk('public')->delete($actualTeam->sponsor_logo);
            }
            $validated['sponsor_logo'] = $request->file('sponsor_logo')->store('team-sponsors', 'public');
        }

        // Handle Captain Image — processed path from player-image-upload component
        if ($request->filled('captain_image') && $request->input('captain_image') !== $actualTeam->captain_image) {
            $newCaptainPath = $request->input('captain_image');
            // Only accept if the file actually exists in storage (processed by PlayerImageProcessController)
            if (Storage::disk('public')->exists($newCaptainPath)) {
                if ($actualTeam->captain_image && $actualTeam->captain_image !== $newCaptainPath) {
                    Storage::disk('public')->delete($actualTeam->captain_image);
                }
                $validated['captain_image'] = $newCaptainPath;
            }
        }

        // 4. Update the main team details based on scope
        $teamData = collect($validated)->except(['captain_user_id', 'tournament_ids', 'team_scope'])->toArray();

        if ($teamScope === 'global') {
            $teamData['is_global'] = true;
            $teamData['tournament_id'] = null;
            $actualTeam->update($teamData);
            // Clear tournament pivot for global teams
            $actualTeam->tournaments()->detach();
        } else {
            $tournamentIds = $validated['tournament_ids'];
            $teamData['is_global'] = false;
            $teamData['tournament_id'] = $tournamentIds[0];
            $actualTeam->update($teamData);
            // Sync multi-tournament pivot
            $actualTeam->tournaments()->sync($tournamentIds);
        }

        // 5. Handle Captain Assignment
        if ($request->filled('captain_user_id')) {
            $captainUserId = $request->captain_user_id;

            // Remove captain role from all current members
            DB::table('actual_team_users')
                ->where('actual_team_id', $actualTeam->id)
                ->where('role', 'captain')
                ->update(['role' => 'Player']);

            // Set the selected user as captain
            $existingMember = DB::table('actual_team_users')
                ->where('actual_team_id', $actualTeam->id)
                ->where('user_id', $captainUserId)
                ->first();

            if ($existingMember) {
                // Update existing member to captain
                DB::table('actual_team_users')
                    ->where('actual_team_id', $actualTeam->id)
                    ->where('user_id', $captainUserId)
                    ->update(['role' => 'captain', 'updated_at' => now()]);
            } else {
                // Add user as captain if not already a member
                DB::table('actual_team_users')->insert([
                    'actual_team_id' => $actualTeam->id,
                    'user_id' => $captainUserId,
                    'role' => 'captain',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 6. Redirect with a success message
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

            // Determine pivot role from the Spatie roles being assigned
            $pivotRole = 'Player'; // default
            if (in_array('owner', $roles)) {
                $pivotRole = 'Owner';
            } elseif (in_array('team manager', $roles) || in_array('manager', $roles)) {
                $pivotRole = 'Manager';
            } elseif (in_array('captain', $roles)) {
                $pivotRole = 'captain';
            }

            // Attach to team with role
            DB::table('actual_team_users')->updateOrInsert(
                [
                    'actual_team_id' => $actualTeam->id,
                    'user_id'        => $userId,
                ],
                ['role' => $pivotRole, 'updated_at' => now()]
            );

            if ($isPlayer) {
                $player = $user->player;

                if ($player) {
                    $player->player_mode = $isRetained ? 'retained' : 'normal';
                    // Only set home team if player doesn't already have one
                    if (!$player->actual_team_id) {
                        $player->actual_team_id = $actualTeam->id;
                    }
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

    /**
     * Create a team manager user for the team
     */
    public function createTeamManager(Request $request, ActualTeam $actualTeam)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'nullable|string|min:6',
        ]);

        try {
            DB::beginTransaction();

            // Generate password if not provided
            $plainPassword = $request->password ?: Str::random(10);

            // Generate unique username from email
            $baseUsername = Str::slug(explode('@', $request->email)[0], '_');
            $username = $baseUsername;
            $counter = 1;
            while (User::where('username', $username)->exists()) {
                $username = $baseUsername . '_' . $counter++;
            }

            // Create the user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'username' => $username,
                'password' => Hash::make($plainPassword),
                'organization_id' => $actualTeam->organization_id,
                'email_verified_at' => now(),
            ]);

            // Assign Team Manager role
            $user->assignRole('Team Manager');

            // Add to the team
            DB::table('actual_team_users')->insert([
                'actual_team_id' => $actualTeam->id,
                'user_id' => $user->id,
                'role' => 'Team Manager',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            // Auto-send credentials email
            try {
                $tournament = $actualTeam->tournament ?? $actualTeam->tournaments()->first();
                if ($tournament) {
                    Mail::to($user->email)->send(new TeamManagerCredentialsMail($user, $plainPassword, $tournament, $actualTeam));
                } else {
                    Log::warning("No tournament found for team {$actualTeam->id}, skipping credentials email to {$user->email}");
                }
            } catch (\Throwable $e) {
                Log::warning("Failed to send credentials email to {$user->email}: {$e->getMessage()}");
            }

            return response()->json([
                'success' => true,
                'message' => 'Team manager created successfully! Credentials email sent.',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'credentials' => [
                    'email' => $user->email,
                    'password' => $plainPassword,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Error creating team manager for team {$actualTeam->id}: {$e->getMessage()}");

            return response()->json([
                'success' => false,
                'message' => 'Failed to create team manager: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get team managers for a team
     */
    public function getTeamManagers(ActualTeam $actualTeam)
    {
        $managers = DB::table('actual_team_users')
            ->join('users', 'actual_team_users.user_id', '=', 'users.id')
            ->where('actual_team_users.actual_team_id', $actualTeam->id)
            ->where('actual_team_users.role', 'Team Manager')
            ->select('users.id', 'users.name', 'users.email', 'actual_team_users.role')
            ->get();

        return response()->json([
            'success' => true,
            'managers' => $managers,
        ]);
    }

    /**
     * Reset password for a team manager
     */
    public function resetTeamManagerPassword(Request $request, ActualTeam $actualTeam, User $user)
    {
        // Verify user belongs to this team
        $isMember = DB::table('actual_team_users')
            ->where('actual_team_id', $actualTeam->id)
            ->where('user_id', $user->id)
            ->exists();

        if (!$isMember) {
            return response()->json([
                'success' => false,
                'message' => 'User is not a member of this team.',
            ], 400);
        }

        $newPassword = $request->password ?: Str::random(10);
        $user->update(['password' => Hash::make($newPassword)]);

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully!',
            'credentials' => [
                'email' => $user->email,
                'password' => $newPassword,
            ],
        ]);
    }

    /**
     * Resend credentials for a team manager (reset password + send email)
     */
    public function resendTeamManagerCredentials(ActualTeam $actualTeam, User $user)
    {
        // Verify user belongs to this team
        $isMember = DB::table('actual_team_users')
            ->where('actual_team_id', $actualTeam->id)
            ->where('user_id', $user->id)
            ->exists();

        if (!$isMember) {
            return response()->json([
                'success' => false,
                'message' => 'User is not a member of this team.',
            ], 400);
        }

        $newPassword = Str::random(10);
        $user->update(['password' => Hash::make($newPassword)]);

        // Send credentials email
        try {
            Mail::to($user->email)->send(new TeamManagerCredentialsMail($user, $newPassword, $actualTeam->tournament, $actualTeam));
        } catch (\Throwable $e) {
            Log::warning("Failed to send credentials email to {$user->email}: {$e->getMessage()}");

            return response()->json([
                'success' => true,
                'message' => 'Password reset but email failed to send.',
                'credentials' => [
                    'email' => $user->email,
                    'password' => $newPassword,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Credentials email sent successfully!',
            'credentials' => [
                'email' => $user->email,
                'password' => $newPassword,
            ],
        ]);
    }

    /**
     * Add a player to the team (AJAX) — creates player + user if needed
     */
    public function addPlayer(Request $request, ActualTeam $actualTeam)
    {
        // If adding an existing player from squad, use relaxed validation
        if ($request->filled('existing_player_id')) {
            $request->validate([
                'existing_player_id'            => 'required|exists:players,id',
                'tournament_assignments'        => 'nullable|array',
                'tournament_assignments.*.tournament_id' => 'required|exists:tournaments,id',
                'tournament_assignments.*.team_id'       => 'required|exists:actual_teams,id',
                'tournament_assignments.*.role' => 'nullable|string|max:50',
            ]);
        } else {
            $request->validate([
                'name'                          => 'required|string|max:255',
                'email'                         => 'required|email|max:255',
                'phone'                         => 'required|string|max:20',
                'player_image'                  => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
                'tournament_assignments'        => 'nullable|array',
                'tournament_assignments.*.tournament_id' => 'required|exists:tournaments,id',
                'tournament_assignments.*.team_id'       => 'required|exists:actual_teams,id',
                'tournament_assignments.*.role' => 'nullable|string|max:50',
            ]);
        }

        try {
            DB::beginTransaction();

            $player = null;

            // If existing player ID is provided, use that directly
            if ($request->filled('existing_player_id')) {
                $player = Player::findOrFail($request->existing_player_id);
            } else {
                // Look up existing player by phone number
                if ($request->filled('phone')) {
                    $player = Player::where('mobile_number_full', $request->phone)->first();
                }

                // If no existing player, create new Player + User
                if (!$player) {
                    // Check if a user with this email already exists
                    $existingUser = User::where('email', $request->email)->first();
                    $user = $existingUser ?? User::create([
                        'name'              => $request->name,
                        'email'             => strtolower($request->email),
                        'username'          => Str::slug($request->name) . '_' . Str::random(4),
                        'password'          => Hash::make(Str::random(16)),
                        'organization_id'   => $actualTeam->organization_id,
                        'email_verified_at' => now(),
                    ]);

                    $player = Player::create([
                        'name'               => $request->name,
                        'mobile_number_full' => $request->phone,
                        'user_id'            => $user->id,
                        'actual_team_id'     => $actualTeam->id,
                        'status'             => 'approved',
                    ]);
                } else {
                    // Update phone if different
                    if ($request->filled('phone') && $player->mobile_number_full !== $request->phone) {
                        $player->mobile_number_full = $request->phone;
                    }
                    // Only set home team if player doesn't already have one
                    if (!$player->actual_team_id) {
                        $player->actual_team_id = $actualTeam->id;
                    }
                    $player->status = 'approved';
                    $player->save();
                }
            }

            // Handle image upload
            if ($request->hasFile('player_image')) {
                $imagePath = $request->file('player_image')->store('player-images', 'public');
                $player->update(['image_path' => $imagePath]);
            }

            // Insert tournament-team assignments
            $assignments = $request->input('tournament_assignments', []);
            foreach ($assignments as $assignment) {
                DB::table('player_actual_team_tournament')->updateOrInsert(
                    [
                        'player_id'     => $player->id,
                        'tournament_id' => $assignment['tournament_id'],
                    ],
                    [
                        'actual_team_id' => $assignment['team_id'],
                        'role'           => $assignment['role'] ?? null,
                        'updated_at'     => now(),
                        'created_at'     => now(),
                    ]
                );
            }

            // Assign Player Spatie role
            $playerUser = $player->user;
            if ($playerUser && !$playerUser->hasAnyRole(['Superadmin', 'Admin'])) {
                if (!$playerUser->hasRole('Player')) {
                    $playerUser->assignRole('Player');
                }
            }

            // Add to actual_team_users pivot
            if ($player->user_id) {
                DB::table('actual_team_users')->updateOrInsert(
                    ['actual_team_id' => $actualTeam->id, 'user_id' => $player->user_id],
                    ['role' => 'Player', 'updated_at' => now()]
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Player added successfully.',
                'player'  => [
                    'id'    => $player->id,
                    'name'  => $player->name,
                    'phone' => $player->mobile_number_full,
                    'image' => $player->image_path ? asset('storage/' . $player->image_path) : null,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Error adding player to team {$actualTeam->id}: {$e->getMessage()} \n {$e->getTraceAsString()}");

            return response()->json([
                'success' => false,
                'message' => 'Failed to add player: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a player's details and tournament assignments (AJAX)
     */
    public function updatePlayer(Request $request, ActualTeam $actualTeam, Player $player)
    {
        $request->validate([
            'phone'                         => 'nullable|string|max:20',
            'player_image'                  => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'tournament_assignments'        => 'nullable|array',
            'tournament_assignments.*.tournament_id' => 'required|exists:tournaments,id',
            'tournament_assignments.*.team_id'       => 'required|exists:actual_teams,id',
        ]);

        try {
            DB::beginTransaction();

            if ($request->filled('phone')) {
                $player->mobile_number_full = $request->phone;
                $player->save();
            }

            if ($request->hasFile('player_image')) {
                if ($player->image_path) {
                    Storage::disk('public')->delete($player->image_path);
                }
                $imagePath = $request->file('player_image')->store('player-images', 'public');
                $player->update(['image_path' => $imagePath]);
            }

            // Update tournament-team assignments
            $assignments = $request->input('tournament_assignments', []);
            if (!empty($assignments)) {
                // Remove existing assignments for this player on this team
                DB::table('player_actual_team_tournament')
                    ->where('player_id', $player->id)
                    ->where('actual_team_id', $actualTeam->id)
                    ->delete();

                foreach ($assignments as $assignment) {
                    DB::table('player_actual_team_tournament')->updateOrInsert(
                        [
                            'player_id'     => $player->id,
                            'tournament_id' => $assignment['tournament_id'],
                        ],
                        [
                            'actual_team_id' => $assignment['team_id'],
                            'role'           => $assignment['role'] ?? null,
                            'updated_at'     => now(),
                            'created_at'     => now(),
                        ]
                    );
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Player updated successfully.',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Error updating player {$player->id} on team {$actualTeam->id}: {$e->getMessage()}");

            return response()->json([
                'success' => false,
                'message' => 'Failed to update player: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove a player's assignments from this team (AJAX)
     */
    public function removePlayer(ActualTeam $actualTeam, Player $player)
    {
        try {
            DB::beginTransaction();

            // Remove all pivot assignments for this player on this team
            DB::table('player_actual_team_tournament')
                ->where('player_id', $player->id)
                ->where('actual_team_id', $actualTeam->id)
                ->delete();

            // Reset home team if this was their home team
            if ($player->actual_team_id === $actualTeam->id) {
                $player->update(['actual_team_id' => null]);
            }

            DB::commit();

            return response()->json([
                'success'   => true,
                'message'   => 'Player removed from team successfully.',
                'player_id' => $player->id,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Error removing player {$player->id} from team {$actualTeam->id}: {$e->getMessage()}");

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove player: ' . $e->getMessage(),
            ], 500);
        }
    }
}

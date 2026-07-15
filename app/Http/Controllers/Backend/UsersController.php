<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backend;

use App\Enums\ActionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\PlayerStatistic;
use App\Models\User;
use App\Services\RolesService;
use App\Services\UserService;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UsersController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
        private readonly RolesService $rolesService
    ) {}

    public function index(): Renderable
    {
        $this->checkAuthorization(Auth::user(), ['user.view']);

        // Get the currently logged-in user
        $user = Auth::user();

        // Prepare the standard filters from the request
        $filters = [
            'search' => request('search'),
            'role' => request('role'),
        ];

        // THE KEY LOGIC: If the user is NOT a Superadmin, add their organization_id to the filters.
        // This code correctly prepares the filter for the model to use.
        if (!$user->hasRole('Superadmin')) {
            $filters['organization_id'] = $user->organization_id;
        }

        // Now, pass the filters to the service.
        return view('backend.pages.users.index', [
            'users' => $this->userService->getUsers($filters),
            'roles' => $this->rolesService->getRolesDropdown(),
            'breadcrumbs' => [
                'title' => __('Users'),
            ],
        ]);
    }
    public function create(): Renderable
    {
        $this->checkAuthorization(Auth::user(), ['user.create']);

        ld_do_action('user_create_page_before');

        return view('backend.pages.users.create', [
            'roles' => $this->rolesService->getRolesDropdown(),
            'organizations' => \App\Models\Organization::orderBy('name')->get(),
            'breadcrumbs' => [
                'title' => __('New User'),
                'items' => [
                    ['label' => __('Users'), 'url' => route('admin.users.index')],
                ],
            ],
        ]);
    }


    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = new User();
        $user->organization_id = Auth::user()->hasRole('Superadmin')
            ? $request->organization_id
            : Auth::user()->organization_id;
        $user->name = $request->name;
        $user->username = $request->username;

        $user->email = $request->email;
        $user->password = Hash::make($request->password);

        $user = ld_apply_filters('user_store_before_save', $user, $request);
        $user->save();
        /** @var User $user */
        $user = ld_apply_filters('user_store_after_save', $user, $request);

        if ($request->roles) {
            $roles = array_filter($request->roles);
            $user->assignRole($roles);
        }

        $this->storeActionLog(ActionType::CREATED, ['user' => $user]);

        session()->flash('success', __('User has been created.'));

        ld_do_action('user_store_after', $user);

        return redirect()->route('admin.users.index');
    }

    public function show(int $id): Renderable
    {
        $this->checkAuthorization(Auth::user(), ['user.view']);

        $user = User::with(['roles', 'organization'])->findOrFail($id);

        // Load player profile if user has one
        $player = $user->player;
        $tournamentAssignments = collect();
        $tournamentStats = collect();

        if ($player) {
            $player->load([
                'actualTeam', 'team', 'location', 'kitSize',
                'battingProfile', 'bowlingProfile', 'playerType',
            ]);

            $tournamentAssignments = \DB::table('player_actual_team_tournament')
                ->join('tournaments', 'tournaments.id', '=', 'player_actual_team_tournament.tournament_id')
                ->join('actual_teams', 'actual_teams.id', '=', 'player_actual_team_tournament.actual_team_id')
                ->where('player_actual_team_tournament.player_id', $player->id)
                ->select(
                    'player_actual_team_tournament.*',
                    'tournaments.name as tournament_name',
                    'actual_teams.name as team_name',
                    'actual_teams.team_logo'
                )
                ->get();

            $tournamentStats = PlayerStatistic::where('player_id', $player->id)
                ->with('tournament')
                ->get()
                ->keyBy('tournament_id');
        }

        // Load organizer assignments
        $assignedTournaments = $user->assignedTournaments ?? collect();

        return view('backend.pages.users.show', [
            'user' => $user,
            'player' => $player,
            'tournamentAssignments' => $tournamentAssignments,
            'tournamentStats' => $tournamentStats,
            'assignedTournaments' => $assignedTournaments,
            'verifiedProfile' => $player ? $player->allFieldsVerified() : false,
            'breadcrumbs' => [
                'title' => __('View User'),
                'items' => [
                    ['label' => __('Users'), 'url' => route('admin.users.index')],
                ],
            ],
        ]);
    }

    public function edit(int $id): Renderable
    {
        $this->checkAuthorization(Auth::user(), ['user.edit']);

        $user = User::findOrFail($id);

        ld_do_action('user_edit_page_before');

        $user = ld_apply_filters('user_edit_page_before_with_user', $user);

        // Load player summary if user has a player profile
        $player = $user->player;
        $playerStats = collect();
        $playerTournaments = collect();
        if ($player) {
            $player->load(['actualTeam', 'player_type', 'batting_profile', 'bowling_profile', 'actualTeamAssignments']);
            $playerStats = PlayerStatistic::where('player_id', $player->id)
                ->with(['tournament', 'team'])
                ->get();

            // Get all tournaments this player's team(s) participate in
            $teamIds = $player->actualTeamAssignments->pluck('id')->push($player->actual_team_id)->filter()->unique();
            if ($teamIds->isNotEmpty()) {
                $playerTournaments = \App\Models\Tournament::where(function ($q) use ($teamIds) {
                    $q->whereHas('actualTeams', fn($q2) => $q2->whereIn('actual_teams.id', $teamIds))
                      ->orWhereHas('groups.teams', fn($q2) => $q2->whereIn('actual_teams.id', $teamIds));
                })
                ->with(['actualTeams' => fn($q) => $q->whereIn('actual_teams.id', $teamIds)])
                ->get();
            }
        }

        return view('backend.pages.users.edit', [
            'user' => $user,
            'player' => $player,
            'playerStats' => $playerStats,
            'playerTournaments' => $playerTournaments,
            'roles' => $this->rolesService->getRolesDropdown(),
            'breadcrumbs' => [
                'title' => __('Edit User'),
                'items' => [
                    [
                        'label' => __('Users'),
                        'url' => route('admin.users.index'),
                    ],
                ],
            ],
        ]);
    }

    public function update(UpdateUserRequest $request, int $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        // Prevent editing of super admin in demo mode
        $this->preventSuperAdminModification($user);

        $user->name = $request->name;
        $user->email = $request->email;
        $user->username = $request->username;
        if ($request->password) {
            $user->password = Hash::make($request->password);
        }
        $user = ld_apply_filters('user_update_before_save', $user, $request);
        $user->save();

        /** @var User $user */
        $user = ld_apply_filters('user_update_after_save', $user, $request);
        ld_do_action('user_update_after', $user);

        $user->roles()->detach();
        if ($request->roles) {
            $roles = array_filter($request->roles);
            $user->assignRole($roles);
        }

        $this->storeActionLog(ActionType::UPDATED, ['user' => $user]);

        session()->flash('success', __('User has been updated.'));

        return back();
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['user.delete']);
        $user = $this->userService->getUserById($id);

        // Prevent deletion of super admin in demo mode
        $this->preventSuperAdminModification($user);

        // Prevent users from deleting themselves.
        if (Auth::id() === $user->id) {
            session()->flash('error', __('You cannot delete your own account.'));
            return back();
        }

        $user = ld_apply_filters('user_delete_before', $user);
        $user->delete();
        $user = ld_apply_filters('user_delete_after', $user);
        session()->flash('success', __('User has been deleted.'));

        $this->storeActionLog(ActionType::DELETED, ['user' => $user]);

        ld_do_action('user_delete_after', $user);

        return back();
    }

    /**
     * Delete multiple users at once
     */
    public function bulkDelete(Request $request): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['user.delete']);

        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return redirect()->route('admin.users.index')
                ->with('error', __('No users selected for deletion'));
        }

        // Prevent deleting current user.
        if (in_array(Auth::id(), $ids)) {
            // Remove current user from the deletion list.
            $ids = array_filter($ids, fn($id) => $id != Auth::id());
            session()->flash('error', __('You cannot delete your own account. Other selected users will be processed.'));

            // If no users left to delete after filtering out current user.
            if (empty($ids)) {
                return redirect()->route('admin.users.index')
                    ->with('error', __('No users were deleted.'));
            }
        }

        $users = User::whereIn('id', $ids)->get();
        $deletedCount = 0;

        foreach ($users as $user) {
            // Skip super admin users
            if ($user->hasRole('superadmin')) {
                continue;
            }

            $user = ld_apply_filters('user_delete_before', $user);
            $user->delete();
            ld_apply_filters('user_delete_after', $user);

            $this->storeActionLog(ActionType::DELETED, ['user' => $user]);
            ld_do_action('user_delete_after', $user);

            $deletedCount++;
        }

        if ($deletedCount > 0) {
            session()->flash('success', __(':count users deleted successfully', ['count' => $deletedCount]));
        } else {
            session()->flash('error', __('No users were deleted. Selected users may include protected accounts.'));
        }

        return redirect()->route('admin.users.index');
    }
}

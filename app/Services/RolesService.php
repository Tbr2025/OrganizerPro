<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesService
{
    public function __construct(private readonly PermissionService $permissionService) {}

    public function getAllRoles()
    {
        return Role::all();
    }

    public function getRolesDropdown(): array
    {
        return Role::pluck('name', 'id')->toArray();
    }

    public function getPaginatedRoles(?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        $query = Role::query();

        if ($search) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        return $query->paginate(config('settings.default_pagination', $perPage));
    }

    public static function getPermissionsByGroupName(string $group_name): Collection
    {
        return Permission::select('name', 'id')
            ->where('group_name', $group_name)
            ->get();
    }

    /**
     * Get permissions by group
     */
    public function getPermissionsByGroup(string $groupName): ?array
    {
        return $this->permissionService->getPermissionsByGroup($groupName);
    }

    public function roleHasPermissions(Role $role, $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (! $role->hasPermissionTo($permission->name)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Create a new role with permissions
     */
    public function createRole(string $name, array $permissions = []): \Spatie\Permission\Models\Role
    {
        /** @var \Spatie\Permission\Models\Role $role */
        $role = Role::create(['name' => $name, 'guard_name' => 'web']);

        if (! empty($permissions)) {
            $role->syncPermissions($permissions);
        }

        return $role;
    }

    public function findRoleById(int $id): ?Role
    {
        $role = Role::findById($id);

        return $role instanceof Role ? $role : null;
    }

    public function findRoleByName(string $name): ?Role
    {
        $role = Role::findByName($name);

        return $role instanceof Role ? $role : null;
    }

    public function updateRole(Role $role, string $name, array $permissions = []): Role
    {
        $role->name = $name;
        $role->save();

        if (! empty($permissions)) {
            $role->syncPermissions($permissions);
        }

        return $role;
    }

    public function deleteRole(Role $role): bool
    {
        return $role->delete();
    }

    /**
     * Count users in a specific role
     *
     * @param  Role|string  $role
     */
    public function countUsersInRole($role): int
    {
        if (is_string($role)) {
            $role = Role::where('name', $role)->first();
            if (! $role) {
                return 0;
            }
        }

        return $role->users->count();
    }

    /**
     * Get roles with user counts
     */
    public function getPaginatedRolesWithUserCount(?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        // Check if we're sorting by user count
        $sort = request()->query('sort');
        $isUserCountSort = ($sort === 'user_count' || $sort === '-user_count');

        // For user count sorting, we need to handle it separately
        if ($isUserCountSort) {
            // Get all roles matching the search criteria without any sorting
            $query = \Spatie\Permission\Models\Role::query(); // Use Spatie's Role model directly

            if ($search) {
                $query->where('name', 'like', '%' . $search . '%');
            }

            $allRoles = $query->get();

            // Add user count to each role
            foreach ($allRoles as $role) {
                $userCount = $this->countUsersInRole($role);
                $role->setAttribute('user_count', $userCount);
            }

            // Sort the collection by user_count
            $direction = $sort === 'user_count' ? 'asc' : 'desc';
            $sortedRoles = $direction === 'asc'
                ? $allRoles->sortBy('user_count')
                : $allRoles->sortByDesc('user_count');

            // Manually paginate the collection
            $page = request()->get('page', 1);
            $offset = ($page - 1) * $perPage;

            $paginatedRoles = new \Illuminate\Pagination\LengthAwarePaginator(
                $sortedRoles->slice($offset, $perPage)->values(),
                $sortedRoles->count(),
                $perPage,
                $page,
                ['path' => request()->url(), 'query' => request()->query()]
            );

            return $paginatedRoles;
        }

        // For normal sorting by database columns
        // Assuming \App\Models\Role has applyFilters and paginateData methods
        // If not, use standard Eloquent methods
        $filters = [
            'search' => $search,
            'sort_field' => 'name',
            'sort_direction' => 'asc',
        ];

        // Ensure you're using the correct Role model. If `\App\Models\Role` is a wrapper
        // and provides `applyFilters` and `paginateData`, keep it.
        // Otherwise, replace with `\Spatie\Permission\Models\Role::query()`.
        $query = \Spatie\Permission\Models\Role::query(); // Assuming default Spatie Role
        if ($search) {
            $query->where('name', 'like', '%' . $search . '%');
        }
        // Apply sorting based on request query params if not user_count sort
        $sortField = request()->query('sort_field', 'name');
        $sortDirection = request()->query('sort_direction', 'asc');
        $query->orderBy($sortField, $sortDirection);


        $roles = $query->paginate(config('settings.default_pagination', $perPage));


        // Add user count to each role
        foreach ($roles->items() as $role) {
            $userCount = $this->countUsersInRole($role);
            $role->setAttribute('user_count', $userCount);
        }

        return $roles;
    }

    /**
     * Create predefined roles with their permissions
     */
    public function createPredefinedRoles(): array
    {
        $roles = [];

        // 1. Superadmin - has all permissions
        $allPermissionNames = [];
        foreach ($this->permissionService->getAllPermissions() as $group) {
            foreach ($group['permissions'] as $permission) {
                $allPermissionNames[] = $permission;
            }
        }
        $roles['superadmin'] = $this->createRole('Superadmin', $allPermissionNames);

        // 2. Admin - all permissions except some critical ones
        $adminExcludedPermissions = ['user.delete', 'role.delete', 'permission.delete', 'settings.view','settings.edit']; // Added more critical exclusions
        $adminPermissions = array_diff($allPermissionNames, $adminExcludedPermissions);
        $roles['admin'] = $this->createRole('Admin', $adminPermissions);

        // 3. Organizer - full tournament management, including player status updates
        $organizerPermissions = [
            'dashboard.view',
            'tournament.create',
            'tournament.view',
            'tournament.edit',
            'tournament.delete', // Organizers can delete tournaments
            'team.create',
            'team.view',
            'team.edit',
            'team.delete', // Organizers can delete teams
            'player.create',
            'player.view',
            'player.edit', // This permission includes updating player status
            'player.delete', // Organizers can delete players
            'match.create',
            'match.view',
            'match.edit',
            'match.delete',
            'match_appreciation.create',
            'match_appreciation.view',
            // Potentially add permissions for managing categories, kits, etc. if they are part of tournament setup
            // 'kit_size.view', 'kit_size.create', 'kit_size.edit', 'kit_size.delete',
            // 'player_type.view', 'player_type.create', 'player_type.edit', 'player_type.delete',
            // 'batting_profile.view', 'bowling_profile.view', etc.
        ];
        $roles['organizer'] = $this->createRole('Organizer', $organizerPermissions);

        // 4. Team Manager (New Role based on user context: "Manager or admin can create team.")
        // This role can manage their specific team, create players for it, and update their status.
        // This is distinct from a "Captain" who might just manage players *within* their existing team.
        // This role assumes the manager is "assigned" to a team and can only manage players of that team.
        // The implementation of scope (e.g., "only players of my team") would be in a policy/middleware.
        $teamManagerPermissions = [
            'dashboard.view',
            'team.view', // Can view teams (at least their own)
            'team.edit', // Can edit their own team details (if allowed by policy)
            'player.create', // Can add players to their team
            'player.view', // Can view players (at least their team's)
            'player.edit', // Can edit players, including status, for their team
            // If managers can create teams: 'team.create', (based on user's saved info "Manager or admin can create team.")
            'team.create', // Added based on saved context
        ];
        $roles['team_manager'] = $this->createRole('Team Manager', $teamManagerPermissions);


        // 5. Coach - can view teams & players
        $coachPermissions = [
            'dashboard.view',
            'team.view',
            'player.view',
        ];
        $roles['coach'] = $this->createRole('Coach', $coachPermissions);

        // 6. Captain - manage own team and players (can edit own team's players only)
        // Note: The actual "own team" scope needs to be enforced via policies.
        $captainPermissions = [
            'dashboard.view',
            'team.view',
            'player.view',
            'player.edit', // Can edit players, implied to be for their own team
        ];
        $roles['captain'] = $this->createRole('Captain', $captainPermissions);

        // 7. Player - view only own profile
        $playerPermissions = [
            'dashboard.view',
            'profile.view',
            'profile.edit',
        ];
        $roles['player'] = $this->createRole('Player', $playerPermissions);

        // 8. Scorer - manage match scores
        $scorerPermissions = [
            'dashboard.view',
            'match.view',
            'match.edit', // Can edit scores
        ];
        $roles['scorer'] = $this->createRole('Scorer', $scorerPermissions);

        // 9. Viewer - public role, can view tournaments
        $viewerPermissions = [
            'dashboard.view',
            'tournament.view',
            'team.view',
            'player.view',
            'match.view',
        ];
        $roles['viewer'] = $this->createRole('Viewer', $viewerPermissions);

        // 10. Editor (content management)
        $roles['editor'] = $this->createRole('Editor', [
            'dashboard.view',
            'blog.create',
            'blog.view',
            'blog.edit',
            'profile.view',
            'profile.edit',
            'profile.update',
            'translations.view',
            'post.create',
            'post.view',
            'post.edit',
            'term.view',
            'term.create',
        ]);

        // 11. Subscriber
        $basicPermissions = [
            'dashboard.view',
            'profile.view',
            'profile.edit',
            'profile.update',
            'post.view', // Can view posts
            'term.view', // Can view terms/categories
        ];
        $roles['subscriber'] = $this->createRole('Subscriber', $basicPermissions);

        // 12. Contact (Fixing the typo 'contact ' to 'contact')
        $roles['contact'] = $this->createRole('Contact', $basicPermissions);

        return $roles;
    }
    // public function getOrCreateRoleForOrganization(string $roleName, int $organizationId): Role
    // {
    //     return Role::firstOrCreate(
    //         [
    //             'name' => $roleName,
    //             'organization_id' => $organizationId,
    //             'guard_name' => 'web', // change if you're using another guard
    //         ]
    //     );
    // }
    // public function assignRoleToUser(User $user, string $roleName): void
    // {
    //     if (!$user->organization_id) {
    //         throw new \InvalidArgumentException('User must have an organization_id to assign role');
    //     }

    //     $role = $this->getOrCreateRoleForOrganization($roleName, $user->organization_id);

    //     $user->assignRole($role);
    // }
    /**
     * Get a specific predefined role's permissions
     */
    public function getPredefinedRolePermissions(string $roleName): array
    {
        $roleName = strtolower($roleName);

        // This is a more robust way to get all permissions without re-iterating
        $getAllPermissionNames = function () {
            $allPermissionNames = [];
            foreach ($this->permissionService->getAllPermissions() as $group) {
                foreach ($group['permissions'] as $permission) {
                    $allPermissionNames[] = $permission;
                }
            }
            return $allPermissionNames;
        };


        switch ($roleName) {
            case 'superadmin':
                return $getAllPermissionNames();

            case 'admin':
                $adminExcludedPermissions = [
                    'user.delete',
                    'role.delete',
                    'permission.delete',
                ];
                return array_diff($getAllPermissionNames(), $adminExcludedPermissions);

            case 'organizer':
                return [
                    'dashboard.view',
                    'tournament.create',
                    'tournament.view',
                    'tournament.edit',
                    'tournament.delete',
                    'team.create',
                    'team.view',
                    'team.edit',
                    'team.delete',
                    'player.create',
                    'player.view',
                    'player.edit',
                    'player.delete',
                    'match.create',
                    'match.view',
                    'match.edit',
                    'match.delete',
                    'match_appreciation.create',
                    'match_appreciation.view',
                    'image-templates.edit',
                    'image-templates.view',
                    'image-templates.delete'
                ];
            case 'team_manager': // Permissions for the new Team Manager role
                return [
                    'dashboard.view',
                    'team.create', // Based on saved context: "Manager or admin can create team."
                    'team.view',
                    'team.edit',
                    'player.create',
                    'player.view',
                    'player.edit', // Can edit players, including status, for their team
                ];

            case 'coach':
                return [
                    'dashboard.view',
                    'team.view',
                    'player.view',
                ];

            case 'captain':
                return [
                    'dashboard.view',
                    'team.view',
                    'player.view',
                    'player.edit', // Can edit players, implied for their own team
                ];

            case 'player':
                return [
                    'dashboard.view',
                    'profile.view',
                    'profile.edit',
                ];

            case 'scorer':
                return [
                    'dashboard.view',
                    'match.view',
                    'match.edit',
                ];

            case 'viewer':
                return [
                    'dashboard.view',
                    'tournament.view',
                    'team.view',
                    'player.view',
                    'match.view',
                ];

            case 'editor':
                return [
                    'dashboard.view',
                    'blog.create',
                    'blog.view',
                    'blog.edit',
                    'profile.view',
                    'profile.edit',
                    'profile.update',
                    'translations.view',
                    'post.create',
                    'post.view',
                    'post.edit',
                    'term.view',
                    'term.create',
                ];

            case 'subscriber':
            case 'contact': // Fixed the typo here
                return [
                    'dashboard.view',
                    'profile.view',
                    'profile.edit',
                    'profile.update',
                    'post.view',
                    'term.view',
                ];
            default:
                // If a role is not explicitly defined, return a sensible default or empty array.
                // It's safer to have a default that doesn't grant unexpected permissions.
                return [
                    'dashboard.view',
                    'profile.view',
                    'profile.edit',
                    'profile.update',
                    'post.view',
                    'term.view',
                ];
        }
    }

    /**
     * Create a new role (API wrapper)
     */
    public function create(array $data): Role
    {
        return $this->createRole($data['name'], $data['permissions'] ?? []);
    }

    /**
     * Update a role (API wrapper)
     */
    public function update(Role $role, array $data): Role
    {
        return $this->updateRole($role, $data['name'], $data['permissions'] ?? []);
    }
}

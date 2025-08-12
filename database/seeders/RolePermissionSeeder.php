<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Services\PermissionService;
use App\Services\RolesService;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Class RolePermissionSeeder.
 *
 * @see https://spatie.be/docs/laravel-permission/v5/basic-usage/multiple-guards
 */
class RolePermissionSeeder extends Seeder
{
    public function __construct(
        private readonly PermissionService $permissionService,
        private readonly RolesService $rolesService
    ) {}

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Step 1: Sync all permission definitions
        $this->command->info('Syncing permissions...');
        $this->permissionService->createPermissions();

        // Step 2: Sync all role definitions and their permissions
        $this->command->info('Syncing predefined roles...');
        $roles = $this->rolesService->createPredefinedRoles(); // This uses your safe createOrSyncRole method

        // Step 3: Assign the 'Superadmin' role to the superadmin user, if it doesn't have it.
        $superadminUser = User::where('username', 'superadmin')->first();
        if ($superadminUser && ! $superadminUser->hasRole('Superadmin')) {
            $this->command->info('Assigning Superadmin role to superadmin user...');
            $superadminUser->assignRole($roles['superadmin']);
        }

        // -------------------------------------------------------------------
        // THE CORRECTED LOGIC IS HERE
        // -------------------------------------------------------------------
        // Step 4: Assign a default role ONLY to users who have NO roles at all.

        $this->command->info('Checking for users without any roles...');

        // This is the most important change. We ONLY get users who have zero roles.
        $usersWithoutRoles = User::whereDoesntHave('roles')->get();

        if ($usersWithoutRoles->isEmpty()) {
            $this->command->info('No users found without roles. Skipping assignment.');
        } else {
            $this->command->info('Assigning a default role to ' . $usersWithoutRoles->count() . ' new user(s)...');
            $availableRoles = ['Subscriber', 'Player', 'Viewer', 'Contact']; // A safe list of default roles

            foreach ($usersWithoutRoles as $user) {
                // This loop now ONLY runs for users who have no roles.
                // It is safe to assign one.
                $randomRole = $availableRoles[array_rand($availableRoles)];
                $user->assignRole($randomRole);
                $this->command->line("-> Assigned '{$randomRole}' to user: {$user->email}");
            }
        }

        $this->command->info('Roles and Permissions seeding completed successfully!');
    }
}

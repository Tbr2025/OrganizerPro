<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Split watching an auction from running one, and add the role that only watches.
 *
 * `auction.edit` was doing two unrelated jobs: it gates the configuration wizard AND the
 * live control panel. So the only way to let somebody see the board was to let them sell
 * players and rewrite the auction's rules — which is why the person calling the lots in the
 * room has, until now, had to be an Admin.
 *
 *   auction.observe — reach the panel and read it. Every GET in the organizer group.
 *   auction.control — change the auction. Every POST.
 *
 * Done as a migration rather than by re-running RolePermissionSeeder, because
 * createOrSyncRole() SYNCS a role's permissions against the code's list — and the live
 * Organizer role holds a set that does not match it. Running the seeder to add one role
 * would silently rewrite the others, which is not a thing to do days before an auction.
 * This only ever adds.
 */
return new class extends Migration
{
    /** Everything the auctioneer needs to follow the auction, and nothing that changes it. */
    private const AUCTIONEER_PERMISSIONS = [
        'dashboard.view',
        'tournament.view',
        'team.view',
        'actual-team.view',
        'player.view',
        'auctions.view',
        'auction.view',
        'auction.observe',
        'auction.closed-bids',
    ];

    public function up(): void
    {
        $observe = $this->permissionId('auction.observe', 'auction');
        $control = $this->permissionId('auction.control', 'auction');

        /*
         * Anybody who can already run an auction keeps being able to.
         *
         * Keyed off who actually holds auction.edit today rather than off a hardcoded list
         * of role names, so a hand-made role on the live database is carried across too.
         * Without this the new POST guard would lock every existing operator out of their
         * own control panel the moment it deployed.
         */
        $editors = DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('permissions.name', 'auction.edit')
            ->pluck('role_has_permissions.role_id');

        foreach ($editors as $roleId) {
            $this->grant($roleId, $observe);
            $this->grant($roleId, $control);
        }

        // The Auctioneer itself: observe, never control.
        $roleId = DB::table('roles')->where('name', 'Auctioneer')->where('guard_name', 'web')->value('id');

        if ($roleId === null) {
            $roleId = DB::table('roles')->insertGetId([
                'name' => 'Auctioneer',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (self::AUCTIONEER_PERMISSIONS as $name) {
            // Every one of these already exists in PermissionService; created if absent so a
            // database that is behind on permissions still ends up with a usable role.
            $this->grant($roleId, $this->permissionId($name, explode('.', $name)[0]));
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('name', 'Auctioneer')->where('guard_name', 'web')->value('id');

        if ($roleId !== null) {
            DB::table('role_has_permissions')->where('role_id', $roleId)->delete();
            // Leaves any user's assignment behind rather than silently unassigning people;
            // model_has_roles rows for a deleted role are harmless and visible.
            DB::table('roles')->where('id', $roleId)->delete();
        }

        $ids = DB::table('permissions')
            ->whereIn('name', ['auction.observe', 'auction.control'])
            ->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function permissionId(string $name, string $group): int
    {
        $id = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id');

        if ($id !== null) {
            return (int) $id;
        }

        return (int) DB::table('permissions')->insertGetId([
            'name' => $name,
            'guard_name' => 'web',
            'group_name' => $group,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function grant(int $roleId, int $permissionId): void
    {
        $exists = DB::table('role_has_permissions')
            ->where('role_id', $roleId)
            ->where('permission_id', $permissionId)
            ->exists();

        if (! $exists) {
            DB::table('role_has_permissions')->insert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }
};

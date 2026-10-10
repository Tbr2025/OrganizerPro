<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions for posters, scoring, the LED wall and credits, plus the
 * Auction Organizer role.
 *
 * Poster routes were gated by role NAME (`role:Superadmin`, `role:Superadmin|Admin`),
 * so no other role could ever be given poster access. They now check
 * `template.manage` / `template.generate`, and this grants those to exactly the
 * roles that could reach the routes before — nobody loses access on deploy.
 *
 * Like 2026_08_12_000001, this is a migration rather than a seeder run: the
 * seeder SYNCS role permissions against the code's list, and the live roles do
 * not match it. This only ever adds.
 */
return new class extends Migration
{
    private const AUCTION_ORGANIZER = [
        'dashboard.view',
        'tournament.view',
        'tournament.registrations',
        'actual-team.view',
        'player.create',
        'player.view',
        'player.edit',
        'template.generate',
        'auctions.view',
        'auction.view',
        'auction.create',
        'auction.edit',
        'auction.observe',
        'auction.control',
        'auction.closed-bids',
        'credits.view',
    ];

    private const ORGANIZER_AUCTION = [
        'auctions.view',
        'auction.view',
        'auction.create',
        'auction.edit',
        'auction.delete',
        'auction.observe',
        'auction.control',
        'auction.closed-bids',
    ];

    public function up(): void
    {
        // A database with no roles yet (fresh install, test runs) is set up by
        // RolePermissionSeeder from PermissionService/RolesService, which
        // already carry everything below. Creating permissions here first
        // would only collide with it.
        if ($this->roleId('Superadmin') === null) {
            return;
        }

        $superadmin = $this->roleId('Superadmin');
        $admin = $this->roleId('Admin');
        $organizer = $this->roleId('Organizer');

        // Posters: same reach as the old role gates, plus Organizer.
        $this->grantTo([$superadmin], 'template.manage');
        $this->grantTo([$superadmin, $admin, $organizer], 'template.generate');

        // Scoring and the wall: whoever can already edit a match.
        $matchEditors = $this->rolesHolding('match.edit');
        foreach (['scoring.update', 'scoring.quick_add_player', 'ledwall.operate'] as $name) {
            $this->grantTo([$superadmin, ...$matchEditors], $name);
        }

        // Credits.
        $this->grantTo([$superadmin, $admin, $organizer], 'credits.view');
        foreach (['credits.manage', 'discount.manage', 'feature.manage'] as $name) {
            $this->grantTo([$superadmin], $name);
        }

        // Organizers run auctions for their own tournaments. Still gated per
        // organization by Organization::isAuctionEnabled().
        foreach (self::ORGANIZER_AUCTION as $name) {
            $this->grantTo([$organizer], $name);
        }

        $auctionOrganizer = $this->roleId('Auction Organizer', create: true);
        foreach (self::AUCTION_ORGANIZER as $name) {
            $this->grantTo([$auctionOrganizer], $name);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        $roleId = $this->roleId('Auction Organizer');
        if ($roleId !== null) {
            DB::table('role_has_permissions')->where('role_id', $roleId)->delete();
            DB::table('roles')->where('id', $roleId)->delete();
        }

        $ids = DB::table('permissions')->whereIn('name', [
            'template.generate', 'template.manage',
            'scoring.update', 'scoring.quick_add_player', 'ledwall.operate',
            'credits.view', 'credits.manage', 'discount.manage', 'feature.manage',
        ])->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        // The Organizer auction grants are left in place: they may have been
        // given by hand before this ran, and removing them could lock someone out.

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /** @param array<int|null> $roleIds */
    private function grantTo(array $roleIds, string $permission): void
    {
        $permissionId = $this->permissionId($permission);

        foreach (array_unique(array_filter($roleIds)) as $roleId) {
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
    }

    /** @return array<int> */
    private function rolesHolding(string $permission): array
    {
        return DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('permissions.name', $permission)
            ->pluck('role_has_permissions.role_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function roleId(string $name, bool $create = false): ?int
    {
        $id = DB::table('roles')->where('name', $name)->where('guard_name', 'web')->value('id');

        if ($id === null && $create) {
            $id = DB::table('roles')->insertGetId([
                'name' => $name,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $id === null ? null : (int) $id;
    }

    private function permissionId(string $name): int
    {
        $id = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id');

        if ($id !== null) {
            return (int) $id;
        }

        return (int) DB::table('permissions')->insertGetId([
            'name' => $name,
            'guard_name' => 'web',
            'group_name' => explode('.', $name)[0],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};

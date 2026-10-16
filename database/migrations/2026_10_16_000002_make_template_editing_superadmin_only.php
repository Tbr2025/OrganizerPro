<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Template designs are Super Admin's: only Super Admin edits, duplicates, adds
 * or deletes a tournament's templates. Organization users (Admin, Organizer,
 * Auction Organizer) keep template.generate — making posters — and may change
 * only the background of a free design (bg_only), a paid action.
 *
 * Reverses 2026_10_11_000002_let_organizers_edit_designs.
 */
return new class extends Migration
{
    private const ROLES = ['Admin', 'Organizer', 'Auction Organizer'];

    public function up(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'template.manage')->where('guard_name', 'web')->value('id');
        if ($permissionId === null) {
            return;
        }

        DB::table('role_has_permissions')
            ->where('permission_id', $permissionId)
            ->whereIn('role_id', DB::table('roles')->whereIn('name', self::ROLES)->where('guard_name', 'web')->pluck('id'))
            ->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'template.manage')->where('guard_name', 'web')->value('id');
        if ($permissionId === null) {
            return;
        }
        foreach (DB::table('roles')->whereIn('name', self::ROLES)->where('guard_name', 'web')->pluck('id') as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};

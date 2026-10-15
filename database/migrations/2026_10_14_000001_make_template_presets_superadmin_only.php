<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Template presets are managed by Super Admin only.
 *
 * `template.presets` was granted to Admin, Organizer and Auction Organizer so each
 * organization could build its own. Now only Super Admin builds presets (global or
 * for any organization); everyone else still PICKS from the presets offered to their
 * organization when creating a tournament or season — that needs no permission here.
 */
return new class extends Migration
{
    private const ROLES = ['Admin', 'Organizer', 'Auction Organizer'];

    public function up(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'template.presets')->value('id');
        if (! $permissionId) {
            return;
        }

        DB::table('role_has_permissions')
            ->where('permission_id', $permissionId)
            ->whereIn('role_id', DB::table('roles')->whereIn('name', self::ROLES)->pluck('id'))
            ->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'template.presets')->value('id');
        if (! $permissionId) {
            return;
        }

        foreach (DB::table('roles')->whereIn('name', self::ROLES)->pluck('id') as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};

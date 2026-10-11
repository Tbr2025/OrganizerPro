<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Admins run the credit system alongside Superadmin: wallets, top-ups,
 * prices and discounts. Only adds grants.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['credits.view', 'credits.manage', 'discount.manage', 'feature.manage'];

    public function up(): void
    {
        $adminId = DB::table('roles')->where('name', 'Admin')->where('guard_name', 'web')->value('id');
        if ($adminId === null) {
            return;
        }

        foreach (DB::table('permissions')->whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->pluck('id') as $permissionId) {
            DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $adminId, 'permission_id' => $permissionId]);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        $adminId = DB::table('roles')->where('name', 'Admin')->where('guard_name', 'web')->value('id');
        if ($adminId === null) {
            return;
        }

        $ids = DB::table('permissions')->whereIn('name', ['credits.manage', 'discount.manage', 'feature.manage'])->pluck('id');
        DB::table('role_has_permissions')->where('role_id', $adminId)->whereIn('permission_id', $ids)->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};

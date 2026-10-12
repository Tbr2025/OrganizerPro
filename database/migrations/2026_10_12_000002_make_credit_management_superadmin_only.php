<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Running the credit system — every wallet, grants, top-up approvals, prices,
 * discounts, platform presets — is Super Admin's alone. Undoes the Admin grant
 * from 2026_10_10_000003. Everyone keeps credits.view: their own wallet and
 * top-up requests.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['credits.manage', 'discount.manage', 'feature.manage'];

    public function up(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->pluck('id');
        $superadminId = DB::table('roles')->where('name', 'Superadmin')->where('guard_name', 'web')->value('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)
            ->when($superadminId, fn ($q) => $q->where('role_id', '!=', $superadminId))
            ->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
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
};

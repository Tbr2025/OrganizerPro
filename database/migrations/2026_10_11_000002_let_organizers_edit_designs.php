<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Admins, Organizers and Auction Organizers could apply a ready-made design
 * but not open it in the editor: template.manage had been kept for Superadmin.
 * They edit only their own tournaments' templates (organization scoping on the
 * tournament routes). Only adds grants.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'template.manage')->where('guard_name', 'web')->value('id');
        if ($permissionId === null) {
            return;
        }

        $roleIds = DB::table('roles')->whereIn('name', ['Admin', 'Organizer', 'Auction Organizer'])->where('guard_name', 'web')->pluck('id');
        foreach ($roleIds as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Leaves the grants: removing them would lock people out of their designs.
    }
};

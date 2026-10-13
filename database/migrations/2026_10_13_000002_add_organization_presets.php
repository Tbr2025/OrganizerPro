<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Template presets per organization.
 *
 * A preset is a tournament_templates row with no tournament (see TemplateLibrary).
 * organization_id NULL is a global preset — Super Admin's, offered to everyone;
 * organization_id X belongs to that organization, managed by its own admins and
 * offered only to its tournaments. organization_id means nothing on a
 * tournament's own template, which belongs to its tournament.
 *
 * `template.presets` lets a role manage its organization's presets. Granted to
 * the roles that already manage templates, so the org admins who design
 * posters today can build presets on deploy.
 */
return new class extends Migration
{
    private const ROLES = ['Superadmin', 'Admin', 'Organizer', 'Auction Organizer'];

    public function up(): void
    {
        Schema::table('tournament_templates', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('tournament_id')
                ->constrained()->cascadeOnDelete();
        });

        // A fresh install gets the permission from RolePermissionSeeder.
        if (! DB::table('roles')->where('name', 'Superadmin')->exists()) {
            return;
        }

        $permissionId = DB::table('permissions')->where('name', 'template.presets')->where('guard_name', 'web')->value('id')
            ?? DB::table('permissions')->insertGetId([
                'name' => 'template.presets', 'guard_name' => 'web', 'group_name' => 'template',
                'created_at' => now(), 'updated_at' => now(),
            ]);

        foreach (DB::table('roles')->whereIn('name', self::ROLES)->where('guard_name', 'web')->pluck('id') as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        $id = DB::table('permissions')->where('name', 'template.presets')->value('id');
        if ($id) {
            DB::table('role_has_permissions')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }

        Schema::table('tournament_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
        });
    }
};

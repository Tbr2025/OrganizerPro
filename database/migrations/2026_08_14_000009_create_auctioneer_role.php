<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The role that lets somebody open an auction panel at all.
 *
 * The routes have long been split for this — the comment above them says so: "that split is what
 * makes an Auctioneer possible". The permissions existed, the split existed, and the role that
 * would use them did not, so there was no way to hand the panel to the person calling the lots
 * without also making them an Admin.
 *
 * It carries `auction.observe` and `auction.control` — see the board, take bids, correct the
 * price — and deliberately NOT `auction.edit`. Selling, passing and ending a lot are a different
 * job from calling it, and an auctioneer who can also end a lot can end one nobody asked them to.
 * An organizer who wants both grants the `sell` ability per auction instead, which says which
 * auction it applies to.
 *
 * Idempotent: safe on an install that already has the role, and it grants nobody the role — that
 * happens when a person is added to an auction.
 */
return new class extends Migration
{
    public function up(): void
    {
        $guard = config('auth.defaults.guard', 'web');

        $roleId = DB::table('roles')->where('name', 'Auctioneer')->where('guard_name', $guard)->value('id');

        if (! $roleId) {
            $roleId = DB::table('roles')->insertGetId([
                'name' => 'Auctioneer',
                'guard_name' => $guard,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('name', ['auction.observe', 'auction.control'])
            ->where('guard_name', $guard)
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('role_has_permissions')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => $permissionId],
                [],
            );
        }
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('name', 'Auctioneer')->value('id');

        if (! $roleId) {
            return;
        }

        // The role goes, but nobody's account does — model_has_roles cascades on the role id.
        DB::table('role_has_permissions')->where('role_id', $roleId)->delete();
        DB::table('model_has_roles')->where('role_id', $roleId)->delete();
        DB::table('roles')->where('id', $roleId)->delete();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Credits are the only billing model.
 *
 *  - Every organization moves to billing_mode 'credits' (the package columns stay,
 *    unread, so nothing is lost).
 *  - The tournament fee is now paid when a tournament is created, not when it
 *    leaves draft: renamed to say so.
 *  - Live scoring becomes a feature, unlocked once per tournament. Seeded at
 *    cost 0 — nothing is charged until Super Admin sets a price — and every
 *    tournament that already exists (not a draft) keeps scoring free, so a
 *    price set mid-season never stops a running tournament.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('organizations')->update(['billing_mode' => 'credits']);
        foreach (DB::table('organizations')->pluck('id') as $orgId) {
            DB::table('credit_wallets')->insertOrIgnore(['organization_id' => $orgId, 'balance' => 0, 'created_at' => $now, 'updated_at' => $now]);
        }

        DB::table('credit_features')->where('key', 'tournament_publish')->update([
            'name' => 'Create a tournament',
            'description' => 'Once per tournament (a new season too), charged when it is created.',
            'updated_at' => $now,
        ]);

        DB::table('credit_features')->insertOrIgnore([
            'key' => 'scoring', 'name' => 'Live scoring',
            'description' => 'Once per tournament: ball-by-ball live scoring. Viewing matches is always free.',
            'cost' => 0, 'charge_trigger' => 'unlock_once', 'scope' => 'tournament', 'is_active' => true, 'sort' => 5,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        DB::table('tournaments')->where('status', '!=', 'draft')->whereNotNull('organization_id')
            ->orderBy('id')->select('id', 'organization_id')
            ->chunk(500, function ($tournaments) use ($now) {
                DB::table('credit_feature_unlocks')->insertOrIgnore($tournaments->map(fn ($t) => [
                    'organization_id' => $t->organization_id,
                    'feature_key' => 'scoring',
                    'scope_type' => 'App\\Models\\Tournament',
                    'scope_id' => $t->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });
    }

    public function down(): void
    {
        DB::table('credit_feature_unlocks')->where('feature_key', 'scoring')->whereNull('ledger_id')->delete();
        DB::table('credit_features')->where('key', 'scoring')->delete();
        DB::table('credit_features')->where('key', 'tournament_publish')->update([
            'name' => 'Publish a tournament',
            'description' => 'Once per tournament, when it leaves draft. Drafts are free.',
        ]);
    }
};

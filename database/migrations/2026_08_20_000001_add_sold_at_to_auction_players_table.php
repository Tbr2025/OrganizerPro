<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When a player was sold.
 *
 * The schema has never recorded it. A sale's time could only be inferred — from the action log
 * (which only exists for auctions run since 2026-08-04), from the winning bid's `created_at`
 * (what the auction report does), or from `updated_at`, which any later edit moves. None of
 * those can be filtered or sorted in SQL without a correlated subquery per row, and the player
 * history report needs to do both.
 *
 * The backfill fills in the past from those same three sources, best first. It only ever writes
 * `sold_at` on rows already marked sold, so it cannot alter a price, a status or a squad.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('auction_players', function (Blueprint $table) {
            $table->timestamp('sold_at')->nullable()->after('final_price');
            $table->index('sold_at');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('auction_players', function (Blueprint $table) {
            $table->dropIndex(['sold_at']);
            $table->dropColumn('sold_at');
        });
    }

    /**
     * Best source first, and each step only touches rows the previous one left null.
     *
     * 1. The action log — a real "sold to X for Y" event with its own timestamp.
     * 2. The winning team's last live bid — the moment the price that won was called.
     * 3. `updated_at` — approximate, but better than a sold player with no date at all.
     */
    private function backfill(): void
    {
        if (Schema::hasTable('auction_action_logs')) {
            DB::table('auction_players')
                ->where('status', 'sold')
                ->whereNull('sold_at')
                ->update([
                    'sold_at' => DB::raw('(
                        SELECT MAX(l.created_at) FROM auction_action_logs l
                        WHERE l.auction_player_id = auction_players.id
                          AND l.action IN (\'sell\', \'allot\', \'closed_bid\')
                          AND l.undone_at IS NULL
                    )'),
                ]);
        }

        if (Schema::hasTable('auction_bids')) {
            DB::table('auction_players')
                ->where('status', 'sold')
                ->whereNull('sold_at')
                ->whereNotNull('sold_to_team_id')
                ->update([
                    'sold_at' => DB::raw('(
                        SELECT MAX(b.created_at) FROM auction_bids b
                        WHERE b.auction_player_id = auction_players.id
                          AND b.team_id = auction_players.sold_to_team_id
                          AND b.is_void = 0
                    )'),
                ]);
        }

        DB::table('auction_players')
            ->where('status', 'sold')
            ->whereNull('sold_at')
            ->update(['sold_at' => DB::raw('updated_at')]);
    }
};

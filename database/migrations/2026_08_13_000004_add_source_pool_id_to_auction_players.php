<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which pool a player was in when nobody bid on them.
 *
 * Unsold players are moving to a single pile per auction rather than one per source pool, and
 * `auction_pool_id` is overwritten by that move — so the pile itself can no longer answer where
 * anyone came from. That mattered in exactly one place, and it is not the allotment screen:
 * **re-auction** returns unsold players to a biddable pool, and it did so by reading the
 * holding pool's `parent_pool_id`. With one shared pile there is no parent, and every player
 * would have been left sitting in a pool the auction never serves.
 *
 * So the origin moves onto the player, where it is a fact about them rather than about the
 * container they happen to be in.
 *
 * Runs BEFORE the merge migration, which deletes the per-pool piles this backfills from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auction_players', function (Blueprint $table) {
            // nullOnDelete: deleting a pool must not delete the players who came from it.
            $table->foreignId('source_pool_id')->nullable()->after('auction_pool_id')
                ->constrained('auction_pools')->nullOnDelete();
        });

        /*
         * Backfill from the holding pools as they stand right now — every player sitting in a
         * per-pool unsold pool came from that pool's parent. This is the last moment the
         * information exists.
         */
        DB::table('auction_players')
            ->join('auction_pools', 'auction_pools.id', '=', 'auction_players.auction_pool_id')
            ->where('auction_pools.is_unsold_pool', true)
            ->whereNotNull('auction_pools.parent_pool_id')
            ->update(['auction_players.source_pool_id' => DB::raw('auction_pools.parent_pool_id')]);
    }

    public function down(): void
    {
        Schema::table('auction_players', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_pool_id');
        });
    }
};

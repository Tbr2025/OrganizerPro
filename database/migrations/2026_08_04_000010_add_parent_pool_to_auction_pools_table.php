<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Links an "unsold" pool back to the pool its players came from.
     *
     * A player who attracts no bids is moved into a companion unsold pool for the pool
     * they were being auctioned from, so after the auction the organizer can run final
     * allotment pool by pool rather than from one undifferentiated pile.
     *
     * `is_unsold_pool` already existed on this table but, like `status`, was never
     * written or read by anything.
     */
    public function up(): void
    {
        Schema::table('auction_pools', function (Blueprint $table) {
            if (! Schema::hasColumn('auction_pools', 'parent_pool_id')) {
                $table->unsignedBigInteger('parent_pool_id')->nullable()->after('is_unsold_pool');
                $table->foreign('parent_pool_id')->references('id')->on('auction_pools')->nullOnDelete();
                $table->index(['auction_id', 'is_unsold_pool'], 'auction_pools_unsold_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('auction_pools', function (Blueprint $table) {
            $table->dropForeign(['parent_pool_id']);
            $table->dropIndex('auction_pools_unsold_idx');
            $table->dropColumn('parent_pool_id');
        });
    }
};

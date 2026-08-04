<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Pool-locked auctioning: the organizer activates one pool and the auction serves
     * only that pool's players until it is exhausted.
     *
     * `status` (pending|active|completed) has existed since the pools table was
     * created but was never written or read by anything, so every pool row in every
     * database is 'pending'. These columns give it a lifecycle, let a pool be taken
     * out of play without deleting it, and record how many times it has been run
     * (a re-auction round re-activates a pool).
     */
    public function up(): void
    {
        Schema::table('auction_pools', function (Blueprint $table) {
            if (! Schema::hasColumn('auction_pools', 'is_enabled')) {
                $table->boolean('is_enabled')->default(true)->after('status');
            }
            if (! Schema::hasColumn('auction_pools', 'times_used')) {
                $table->unsignedInteger('times_used')->default(0)->after('is_enabled');
            }
            if (! Schema::hasColumn('auction_pools', 'activated_at')) {
                $table->timestamp('activated_at')->nullable()->after('times_used');
            }
            if (! Schema::hasColumn('auction_pools', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('activated_at');
            }
        });

        Schema::table('auction_pools', function (Blueprint $table) {
            // "Which pool is live in this auction?" is asked on every 2-second poll.
            $table->index(['auction_id', 'status'], 'auction_pools_auction_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('auction_pools', function (Blueprint $table) {
            $table->dropIndex('auction_pools_auction_status_idx');
            $table->dropColumn(['is_enabled', 'times_used', 'activated_at', 'completed_at']);
        });
    }
};

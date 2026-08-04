<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Makes the bid timer real, and adds configurable quick-bid steps.
     *
     * `bid_timer_seconds` already existed but was a purely decorative client-side
     * countdown: nothing was enforced and nothing happened when it hit zero. With
     * `timer_started_at` stamped server-side, a late bid can be rejected, and
     * `timer_expiry_action` decides whether expiry auto-sells to the highest bidder
     * or simply locks bidding and waits for the organizer to press SELL.
     */
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            if (! Schema::hasColumn('auctions', 'timer_enabled')) {
                $table->boolean('timer_enabled')->default(true)->after('bid_timer_reset_seconds');
            }
            if (! Schema::hasColumn('auctions', 'timer_expiry_action')) {
                $table->enum('timer_expiry_action', ['auto_sell', 'manual'])
                    ->default('manual')
                    ->after('timer_enabled');
            }
            if (! Schema::hasColumn('auctions', 'timer_started_at')) {
                $table->timestamp('timer_started_at')->nullable()->after('timer_expiry_action');
            }
            if (! Schema::hasColumn('auctions', 'quick_bid_steps')) {
                // Optional list of jump amounts offered alongside the standard
                // increment ladder in bid_rules.
                $table->json('quick_bid_steps')->nullable()->after('bid_rules');
            }
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn(['timer_enabled', 'timer_expiry_action', 'timer_started_at', 'quick_bid_steps']);
        });
    }
};

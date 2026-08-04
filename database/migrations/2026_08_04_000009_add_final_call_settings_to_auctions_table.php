<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Auctioneer's closing calls: in the closing seconds the display escalates
     * "FIRST CALL → SECOND CALL → FINAL CALL" at a fixed gap, then the auction's
     * configured expiry action resolves the player.
     *
     * Three calls at `final_call_interval_seconds` apart, so the default of 3 gives
     * calls at 9s, 6s and 3s remaining — the last ten seconds of the clock.
     */
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            if (! Schema::hasColumn('auctions', 'final_call_enabled')) {
                $table->boolean('final_call_enabled')->default(true)->after('timer_expiry_action');
            }
            if (! Schema::hasColumn('auctions', 'final_call_interval_seconds')) {
                $table->unsignedSmallInteger('final_call_interval_seconds')->default(3)->after('final_call_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn(['final_call_enabled', 'final_call_interval_seconds']);
        });
    }
};

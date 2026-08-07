<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Give the bid TYPE its own manual-override flag, separate from the bid MODE's.
 *
 * `mode_manually_overridden` was doing two unrelated jobs, and they pull in opposite
 * directions:
 *
 *  - Bid type (open ↔ sealed) is price-driven and belongs to a player. Each new player
 *    starts at their base price, so resetting it per player is right.
 *  - Bid mode (online ↔ offline) is a fact about the room. If the organizer is taking
 *    bids by hand, that is true for the session, not for one player.
 *
 * With one flag, choosing "offline" also silenced the sealed-bid threshold, and the
 * per-player reset that legitimately clears the bid type also wiped the mode — so offline
 * mode never survived the next player.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('auctions', 'bid_type_manually_overridden')) {
            return;
        }

        Schema::table('auctions', function (Blueprint $table) {
            $table->boolean('bid_type_manually_overridden')->default(false)->after('mode_manually_overridden');
        });

        // Existing auctions carry their old meaning forward: the single flag suppressed
        // both transitions, so both flags start where it was.
        DB::table('auctions')
            ->where('mode_manually_overridden', true)
            ->update(['bid_type_manually_overridden' => true]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('auctions', 'bid_type_manually_overridden')) {
            return;
        }

        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn('bid_type_manually_overridden');
        });
    }
};

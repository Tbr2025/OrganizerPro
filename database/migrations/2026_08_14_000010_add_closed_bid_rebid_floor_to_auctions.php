<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a re-bid round opens at after a tie.
 *
 * Until now there was one answer: strictly above the tied amount, by one increment. That is a
 * defensible rule — it guarantees the round cannot end in the same tie twice — and it is not the
 * only one an organizer might want. Keeping the tied figure as the floor lets a team hold its
 * nerve and repeat it, which is how a sealed re-bid is run in plenty of rooms: the second round
 * is about who will go higher, not about who is forced to.
 *
 * Null means the existing behaviour, so nothing changes for any auction already configured.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            if (! Schema::hasColumn('auctions', 'closed_bid_rebid_floor')) {
                $table->string('closed_bid_rebid_floor', 8)->nullable()->after('closed_bid_tie_breaker');
            }
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            if (Schema::hasColumn('auctions', 'closed_bid_rebid_floor')) {
                $table->dropColumn('closed_bid_rebid_floor');
            }
        });
    }
};

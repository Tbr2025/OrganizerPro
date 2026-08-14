<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What the public screens are showing instead of the live card — if anything.
 *
 * `show_sold_board` was a boolean, added this morning for one board. A second board (the
 * highlights reel an organizer puts up during a pause) makes that shape wrong: two booleans can
 * both be true, and then the wall has to invent a rule about which wins. A single column cannot
 * express the contradiction in the first place.
 *
 * Backfilled, then the boolean is dropped rather than left beside it — one day old, written by
 * the same change that is replacing it, and two sources of truth for one question is exactly the
 * bug this shape avoids.
 *
 * Null means the live card, which is the normal state and the default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->string('public_board', 20)->nullable()->after('status');
        });

        // Anything currently showing the sold board keeps showing it across the deploy.
        DB::table('auctions')->where('show_sold_board', true)->update(['public_board' => 'sold']);

        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn('show_sold_board');
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->boolean('show_sold_board')->default(false)->after('status');
        });

        DB::table('auctions')->where('public_board', 'sold')->update(['show_sold_board' => true]);

        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn('public_board');
        });
    }
};

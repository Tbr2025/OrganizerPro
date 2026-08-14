<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether the public screens are currently showing the board of players sold.
 *
 * Server state rather than something each screen decides for itself, because the wall, the
 * ticker and any phone watching have to agree — an organizer pressing the button in the room
 * means "put it up", not "put it up on whichever screen happens to be listening". It also
 * survives a screen being opened or reloaded while the board is up, which a broadcast alone
 * cannot: a wall plugged in halfway through would otherwise show the live card while the rest
 * of the hall is reading the summary.
 *
 * Default false — nothing changes for an auction that never presses the button.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->boolean('show_sold_board')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn('show_sold_board');
        });
    }
};

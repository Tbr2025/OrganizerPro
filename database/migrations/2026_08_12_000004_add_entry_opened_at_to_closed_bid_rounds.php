<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records that the organizer opened this round to the teams themselves.
 *
 * `opened_at` is set when the round starts collecting, whichever way it got there — starting
 * without picking teams auto-invites everyone — so it cannot answer "did the organizer choose
 * Open Entry". That distinction is what decides whether the panel offers amount fields: when the
 * teams are entering their own, those fields are clutter over a board the organizer is reading,
 * and a stray keystroke in one of them writes a bid for somebody else.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auction_closed_bid_rounds', function (Blueprint $table) {
            $table->timestamp('entry_opened_at')->nullable()->after('opened_at');
        });
    }

    public function down(): void
    {
        Schema::table('auction_closed_bid_rounds', function (Blueprint $table) {
            $table->dropColumn('entry_opened_at');
        });
    }
};

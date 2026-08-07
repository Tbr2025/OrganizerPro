<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the bid clock was paused.
 *
 * Pausing only ever flipped `auctions.status`, but the countdown is pure wall-clock
 * arithmetic against `timer_started_at` — so it kept running through the pause. Pause a
 * 30-second timer for a minute and the player came back already expired, and with
 * `timer_expiry_action = auto_sell` they would be sold the instant the room resumed.
 *
 * While this is set the clock reads frozen; on resume `timer_started_at` is shifted forward
 * by however long the pause lasted, so the same number of seconds is left as when it stopped.
 * Nullable, so every existing auction simply behaves as "not paused".
 */
return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('auctions', 'timer_paused_at')) {
            return;
        }

        Schema::table('auctions', function (Blueprint $table) {
            $table->timestamp('timer_paused_at')->nullable()->after('timer_started_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('auctions', 'timer_paused_at')) {
            return;
        }

        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn('timer_paused_at');
        });
    }
};

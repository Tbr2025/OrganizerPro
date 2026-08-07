<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the auction was last restarted.
 *
 * The big screen needs to announce a restart rather than silently blanking to the waiting
 * state, and it has to do so for the same ten seconds on every screen watching — a hall
 * projector, an OBS source and three phones must not each run their own timer from
 * whenever they happened to poll. Recording the moment on the auction lets the server
 * answer "are we restarting?" identically for all of them.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('auctions', 'restarted_at')) {
            return;
        }

        Schema::table('auctions', function (Blueprint $table) {
            $table->timestamp('restarted_at')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('auctions', 'restarted_at')) {
            return;
        }

        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn('restarted_at');
        });
    }
};

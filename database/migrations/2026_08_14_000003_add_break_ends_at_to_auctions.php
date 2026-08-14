<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the current break is due to end.
 *
 * The board's clock counted UP, on the reasoning that nobody knows when a break really ends. In a
 * hall that reads as "they have been gone four minutes" rather than "they are back in six", which
 * is the question the room is actually asking — and an organizer who says "ten minutes" wants the
 * screens to hold them to it.
 *
 * A TIMESTAMP rather than a duration, so every screen counts down to the same instant instead of
 * each one starting its own clock from whenever it happened to poll. The remaining seconds are
 * computed server-side for the same reason the restart notice is.
 *
 * Null means no break is running, which is the normal state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->timestamp('break_ends_at')->nullable()->after('public_board');
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn('break_ends_at');
        });
    }
};

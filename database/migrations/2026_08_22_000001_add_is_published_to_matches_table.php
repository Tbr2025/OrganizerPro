<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a fixture is shown to the public yet.
 *
 * A schedule is drafted before it is announced — grounds move, a pool gets redrawn, a date is not
 * confirmed — and until now the only way to keep an unready fixture off the public page was to
 * mark it CANCELLED or delete it. Cancelled is a real cricket outcome that spectators read as
 * "this match was called off", which is not the same statement at all, and deleting loses the work.
 *
 * So: a separate flag, and deliberately not reusing `is_cancelled` or `status`.
 *
 * **Defaults to true.** Every fixture that exists today was already public, and a default of false
 * would silently empty the fixtures page of every live tournament the moment this migrated.
 *
 * The index is composite with `tournament_id` because that is how the public page asks —
 * "this tournament's published fixtures" — and it is the only query that runs on every visitor.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->boolean('is_published')->default(true)->after('is_cancelled');
            $table->index(['tournament_id', 'is_published'], 'matches_tournament_published_idx');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropIndex('matches_tournament_published_idx');
            $table->dropColumn('is_published');
        });
    }
};

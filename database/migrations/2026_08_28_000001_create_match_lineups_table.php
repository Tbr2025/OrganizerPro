<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The eleven a team names for a match.
 *
 * There was nowhere to record this. A squad belongs to a tournament, not to a fixture, so
 * "who is playing today" existed only in the organizer's head — and the Playing XI poster made
 * them retype eleven names for every render because there was nothing to read back.
 *
 * One row per player per match, which makes the unique key the thing that stops a player being
 * named twice for the same fixture. Deliberately NOT unique on (match, team, batting_order):
 * an organizer reordering a list mid-edit would trip it on the way through.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_lineups', function (Blueprint $table) {
            $table->id();

            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('actual_team_id')->constrained('actual_teams')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();

            // Position in the list as the manager arranged it — batting order for a batting side,
            // simply "the order they should be printed" for the poster.
            $table->unsignedSmallInteger('batting_order')->default(0);

            // C, VC, WK — the marks a line-up graphic shows. Nullable: most players have none.
            $table->string('role', 8)->nullable();

            // Who named it, so a disputed XI has an author.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['match_id', 'player_id']);
            $table->index(['match_id', 'actual_team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_lineups');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            // Drop existing foreign keys that reference the `teams` table
            $table->dropForeign(['team_a_id']);
            $table->dropForeign(['team_b_id']);
            $table->dropForeign(['winner_team_id']);

            // Re-add foreign keys pointing to `actual_teams`
            $table->foreign('team_a_id')->references('id')->on('actual_teams')->onDelete('cascade');
            $table->foreign('team_b_id')->references('id')->on('actual_teams')->onDelete('cascade');
            $table->foreign('winner_team_id')->references('id')->on('actual_teams')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropForeign(['team_a_id']);
            $table->dropForeign(['team_b_id']);
            $table->dropForeign(['winner_team_id']);

            $table->foreign('team_a_id')->references('id')->on('teams')->onDelete('cascade');
            $table->foreign('team_b_id')->references('id')->on('teams')->onDelete('cascade');
            $table->foreign('winner_team_id')->references('id')->on('teams')->onDelete('set null');
        });
    }
};

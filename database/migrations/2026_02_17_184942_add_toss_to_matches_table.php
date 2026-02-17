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
            $table->foreignId('toss_winner_team_id')->nullable()->after('winner_team_id')
                  ->constrained('actual_teams')->onDelete('set null');
            $table->enum('toss_decision', ['bat', 'bowl'])->nullable()->after('toss_winner_team_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropForeign(['toss_winner_team_id']);
            $table->dropColumn(['toss_winner_team_id', 'toss_decision']);
        });
    }
};

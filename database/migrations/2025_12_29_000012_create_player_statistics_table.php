<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->onDelete('cascade');
            $table->foreignId('player_id')->constrained()->onDelete('cascade');
            $table->foreignId('actual_team_id')->constrained()->onDelete('cascade');

            // Batting Stats
            $table->unsignedTinyInteger('matches')->default(0);
            $table->unsignedTinyInteger('innings_batted')->default(0);
            $table->unsignedSmallInteger('runs')->default(0);
            $table->unsignedSmallInteger('balls_faced')->default(0);
            $table->unsignedTinyInteger('fours')->default(0);
            $table->unsignedTinyInteger('sixes')->default(0);
            $table->unsignedSmallInteger('highest_score')->default(0);
            $table->boolean('highest_not_out')->default(false);
            $table->unsignedTinyInteger('fifties')->default(0);
            $table->unsignedTinyInteger('hundreds')->default(0);
            $table->unsignedTinyInteger('not_outs')->default(0);
            $table->unsignedTinyInteger('ducks')->default(0);

            // Bowling Stats
            $table->unsignedTinyInteger('innings_bowled')->default(0);
            $table->decimal('overs_bowled', 5, 1)->default(0);
            $table->unsignedSmallInteger('runs_conceded')->default(0);
            $table->unsignedTinyInteger('wickets')->default(0);
            $table->unsignedTinyInteger('maidens')->default(0);
            $table->string('best_bowling', 10)->nullable(); // "4/25"
            $table->unsignedTinyInteger('four_wickets')->default(0);
            $table->unsignedTinyInteger('five_wickets')->default(0);
            $table->unsignedTinyInteger('wides')->default(0);
            $table->unsignedTinyInteger('no_balls')->default(0);

            // Fielding Stats
            $table->unsignedTinyInteger('catches')->default(0);
            $table->unsignedTinyInteger('stumpings')->default(0);
            $table->unsignedTinyInteger('run_outs')->default(0);

            $table->timestamps();

            $table->unique(['tournament_id', 'player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_statistics');
    }
};

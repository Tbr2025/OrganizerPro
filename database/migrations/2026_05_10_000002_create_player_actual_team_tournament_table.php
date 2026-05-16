<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_actual_team_tournament', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->foreignId('actual_team_id')->constrained('actual_teams')->onDelete('cascade');
            $table->foreignId('tournament_id')->constrained('tournaments')->onDelete('cascade');
            $table->string('role')->nullable();
            $table->timestamps();

            // One team per tournament per player
            $table->unique(['player_id', 'tournament_id'], 'player_tournament_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_actual_team_tournament');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create pivot table for many-to-many teams <-> tournaments
        Schema::create('actual_team_tournament', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actual_team_id')->constrained('actual_teams')->onDelete('cascade');
            $table->foreignId('tournament_id')->constrained('tournaments')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['actual_team_id', 'tournament_id']);
        });

        // 2. Populate pivot from existing tournament_id values
        DB::statement('
            INSERT INTO actual_team_tournament (actual_team_id, tournament_id, created_at, updated_at)
            SELECT id, tournament_id, NOW(), NOW()
            FROM actual_teams
            WHERE tournament_id IS NOT NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('actual_team_tournament');
    }
};

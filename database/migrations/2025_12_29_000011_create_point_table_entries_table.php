<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_table_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->onDelete('cascade');
            $table->foreignId('tournament_group_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('actual_team_id')->constrained()->onDelete('cascade');

            // Match Statistics
            $table->unsignedTinyInteger('matches_played')->default(0);
            $table->unsignedTinyInteger('won')->default(0);
            $table->unsignedTinyInteger('lost')->default(0);
            $table->unsignedTinyInteger('tied')->default(0);
            $table->unsignedTinyInteger('no_result')->default(0);

            // Points
            $table->unsignedSmallInteger('points')->default(0);

            // NRR Calculation Data
            $table->unsignedInteger('runs_scored')->default(0);
            $table->decimal('overs_faced', 6, 1)->default(0);
            $table->unsignedInteger('runs_conceded')->default(0);
            $table->decimal('overs_bowled', 6, 1)->default(0);
            $table->decimal('net_run_rate', 6, 3)->default(0);

            // Position (calculated)
            $table->unsignedTinyInteger('position')->default(0);
            $table->boolean('qualified')->default(false);

            $table->timestamps();

            $table->unique(['tournament_id', 'tournament_group_id', 'actual_team_id'], 'unique_point_entry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_table_entries');
    }
};

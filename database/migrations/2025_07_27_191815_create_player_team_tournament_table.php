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
        Schema::create('player_team_tournament', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->onDelete('cascade');
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->foreignId('tournament_id')->nullable()->constrained()->onDelete('cascade');
            $table->timestamps();
            $table->string('role')->nullable(); // captain, vice_captain, wicket_keeper, etc.
            $table->string('image_path')->nullable(); // player image in team context
            $table->unique(['player_id', 'tournament_id']); // Ensure one player per tournament
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_team_tournament');
    }
};

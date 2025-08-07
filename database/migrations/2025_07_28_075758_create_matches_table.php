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
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedTinyInteger('overs')->nullable();

            $table->foreignId('tournament_id')->constrained()->onDelete('cascade');
            $table->foreignId('team_a_id')->constrained('teams')->onDelete('cascade');
            $table->foreignId('team_b_id')->constrained('teams')->onDelete('cascade');
            $table->timestamp('match_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            $table->string('venue')->nullable();
            $table->enum('status', ['upcoming', 'live', 'completed'])->default('upcoming');
            $table->foreignId('winner_team_id')->nullable()->constrained('teams');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};

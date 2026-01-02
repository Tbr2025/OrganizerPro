<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_group_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_group_id')->constrained()->onDelete('cascade');
            $table->foreignId('actual_team_id')->constrained()->onDelete('cascade');
            $table->unsignedTinyInteger('order')->default(0);
            $table->timestamps();

            $table->unique(['tournament_group_id', 'actual_team_id'], 'unique_group_team');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_group_teams');
    }
};

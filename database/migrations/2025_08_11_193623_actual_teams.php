<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Actual teams table
        Schema::create('actual_teams', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->index();
            $table->unsignedBigInteger('tournament_id')->index();
            $table->string('name'); // actual team name
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('tournament_id')->references('id')->on('tournaments')->onDelete('cascade');
        });

        // Pivot table for players assigned to actual teams
        Schema::create('actual_team_players', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actual_team_id')->index();
            $table->unsignedBigInteger('player_id')->index();
            $table->timestamps();

            $table->foreign('actual_team_id')->references('id')->on('actual_teams')->onDelete('cascade');
            $table->foreign('player_id')->references('id')->on('players')->onDelete('cascade');

            $table->unique(['actual_team_id', 'player_id']);
        });

        // Pivot table for users (like coaches, managers) assigned to actual teams
        Schema::create('actual_team_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actual_team_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->timestamps();
            $table->string('role')->nullable();  // <-- Add this
            $table->foreign('actual_team_id')->references('id')->on('actual_teams')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->unique(['actual_team_id', 'user_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('actual_team_users');
        Schema::dropIfExists('actual_team_players');
        Schema::dropIfExists('actual_teams');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('players', function (Blueprint $table) {
            // 1. Update ENUM to include 'sold' and 'unsold'
            $table->enum('player_mode', [
                'retained',
                'normal',
                'not_selected',
                'sold',
                'unsold'
            ])->default('normal')->change();

            // 2. Add actual_team_id column
            $table->foreignId('actual_team_id')
                ->nullable()
                ->constrained('actual_teams')
                ->nullOnDelete(); // If a team is deleted, set null
        });
    }

    public function down()
    {
        Schema::table('players', function (Blueprint $table) {
            // Rollback ENUM changes
            $table->enum('player_mode', [
                'retained',
                'normal',
                'not_selected'
            ])->default('normal')->change();

            // Drop foreign key & column
            $table->dropForeign(['actual_team_id']);
            $table->dropColumn('actual_team_id');
        });
    }
};

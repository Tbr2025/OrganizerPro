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
        Schema::table('players', function (Blueprint $table) {
            $table->string('batting_mode')->nullable()->after('batting_profile_id');
            $table->json('preferred_batting_positions')->nullable()->after('batting_mode');
            $table->string('playing_team_name_ref')->nullable()->after('actual_team_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['batting_mode', 'preferred_batting_positions', 'playing_team_name_ref']);
        });
    }
};

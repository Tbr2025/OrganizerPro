<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actual_teams', function (Blueprint $table) {
            $table->string('invite_code', 12)->nullable()->unique()->after('captain_image');
        });

        // Generate invite codes for existing teams
        $teams = \App\Models\ActualTeam::all();
        foreach ($teams as $team) {
            $team->update(['invite_code' => Str::random(12)]);
        }
    }

    public function down(): void
    {
        Schema::table('actual_teams', function (Blueprint $table) {
            $table->dropColumn('invite_code');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_settings', function (Blueprint $table) {
            $table->string('player_registration_status', 20)->default('open')->after('tournament_status');
            $table->string('team_registration_status', 20)->default('open')->after('player_registration_status');
        });

        // Seed from existing boolean toggles
        DB::table('tournament_settings')->where('player_registration_open', true)->update(['player_registration_status' => 'open']);
        DB::table('tournament_settings')->where('player_registration_open', false)->update(['player_registration_status' => 'closed']);
        DB::table('tournament_settings')->where('team_registration_open', true)->update(['team_registration_status' => 'open']);
        DB::table('tournament_settings')->where('team_registration_open', false)->update(['team_registration_status' => 'closed']);
    }

    public function down(): void
    {
        Schema::table('tournament_settings', function (Blueprint $table) {
            $table->dropColumn(['player_registration_status', 'team_registration_status']);
        });
    }
};

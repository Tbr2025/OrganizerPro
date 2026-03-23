<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add summary update mode to tournament settings
        Schema::table('tournament_settings', function (Blueprint $table) {
            $table->enum('summary_update_mode', ['manual', 'automatic'])->default('manual')->after('auto_send_match_summary');
        });

        // Add CricHeroes match URL to matches table
        Schema::table('matches', function (Blueprint $table) {
            $table->string('cricheroes_match_url')->nullable()->after('end_time');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_settings', function (Blueprint $table) {
            $table->dropColumn('summary_update_mode');
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('cricheroes_match_url');
        });
    }
};

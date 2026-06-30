<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('tournament_settings', 'team_photo_guidelines')) {
                $table->text('team_photo_guidelines')->nullable();
            }
            if (! Schema::hasColumn('tournament_settings', 'team_photo_sample_path')) {
                $table->string('team_photo_sample_path')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tournament_settings', function (Blueprint $table) {
            $table->dropColumn(['team_photo_guidelines', 'team_photo_sample_path']);
        });
    }
};

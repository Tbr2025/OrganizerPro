<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('tournament_settings', 'photo_guidelines')) {
                $table->text('photo_guidelines')->nullable();
            }
            if (! Schema::hasColumn('tournament_settings', 'photo_sample_path')) {
                $table->string('photo_sample_path')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tournament_settings', function (Blueprint $table) {
            $table->dropColumn(['photo_guidelines', 'photo_sample_path']);
        });
    }
};

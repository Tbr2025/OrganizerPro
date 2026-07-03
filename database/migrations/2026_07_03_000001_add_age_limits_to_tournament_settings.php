<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('tournament_settings', 'min_age')) {
                $table->unsignedSmallInteger('min_age')->nullable();
            }
            if (! Schema::hasColumn('tournament_settings', 'max_age')) {
                $table->unsignedSmallInteger('max_age')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tournament_settings', function (Blueprint $table) {
            $table->dropColumn(['min_age', 'max_age']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_settings', function (Blueprint $table) {
            $table->string('accent_color', 7)->default('#fbbf24')->after('secondary_color');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_settings', function (Blueprint $table) {
            $table->dropColumn('accent_color');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('tournament_settings', 'og_image')) {
                $table->string('og_image')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tournament_settings', function (Blueprint $table) {
            $table->dropColumn('og_image');
        });
    }
};

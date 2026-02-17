<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_awards', function (Blueprint $table) {
            $table->json('template_settings')->nullable()->after('template_image');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_awards', function (Blueprint $table) {
            $table->dropColumn('template_settings');
        });
    }
};

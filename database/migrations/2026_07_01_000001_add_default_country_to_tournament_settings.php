<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('tournament_settings', 'default_country')) {
                $table->string('default_country', 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tournament_settings', function (Blueprint $table) {
            $table->dropColumn('default_country');
        });
    }
};

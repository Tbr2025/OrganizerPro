<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tournament_settings', function (Blueprint $table) {
            $table->string('default_phone_country', 2)->nullable()->after('default_country');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournament_settings', function (Blueprint $table) {
            $table->dropColumn('default_phone_country');
        });
    }
};

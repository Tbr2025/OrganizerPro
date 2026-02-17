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
        Schema::table('players', function (Blueprint $table) {
            // Make phone-related columns nullable for Team Manager created players
            $table->string('mobile_country_code', 10)->nullable()->change();
            $table->string('mobile_national_number', 20)->nullable()->change();

            // Make email nullable and drop unique constraint
            $table->dropUnique(['email']);
        });

        Schema::table('players', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            // Add back unique index that allows nulls (MySQL allows multiple NULLs in unique index)
            $table->unique('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->string('mobile_country_code', 10)->nullable(false)->change();
            $table->string('mobile_national_number', 20)->nullable(false)->change();
            $table->dropUnique(['email']);
        });

        Schema::table('players', function (Blueprint $table) {
            $table->string('email')->nullable(false)->unique()->change();
        });
    }
};

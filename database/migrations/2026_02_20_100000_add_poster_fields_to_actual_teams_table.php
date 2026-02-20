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
        Schema::table('actual_teams', function (Blueprint $table) {
            $table->string('short_name')->nullable()->after('name');
            $table->string('location')->nullable()->after('short_name'); // District/City e.g., "Ernakulam"
            $table->string('primary_color', 7)->nullable()->after('team_logo'); // Hex color e.g., "#00BCD4"
            $table->string('secondary_color', 7)->nullable()->after('primary_color');
            $table->string('sponsor_logo')->nullable()->after('secondary_color');
            $table->string('captain_image')->nullable()->after('sponsor_logo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('actual_teams', function (Blueprint $table) {
            $table->dropColumn([
                'short_name',
                'location',
                'primary_color',
                'secondary_color',
                'sponsor_logo',
                'captain_image',
            ]);
        });
    }
};

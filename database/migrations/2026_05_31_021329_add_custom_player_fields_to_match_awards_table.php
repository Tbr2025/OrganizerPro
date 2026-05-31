<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_awards', function (Blueprint $table) {
            // Make player_id nullable (drop foreign key first, re-add as nullable)
            $table->foreignId('player_id')->nullable()->change();

            // Add custom player fields
            $table->string('custom_player_name')->nullable()->after('player_id');
            $table->string('custom_player_image')->nullable()->after('custom_player_name');
        });
    }

    public function down(): void
    {
        Schema::table('match_awards', function (Blueprint $table) {
            $table->dropColumn(['custom_player_name', 'custom_player_image']);
            $table->foreignId('player_id')->nullable(false)->change();
        });
    }
};

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
        Schema::table('auction_bids', function (Blueprint $table) {
            // Drop existing foreign key
            $table->dropForeign(['team_id']);

            // Add new foreign key referencing actual_teams
            $table->foreign('team_id')->references('id')->on('actual_teams')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auction_bids', function (Blueprint $table) {
            // Drop the new foreign key
            $table->dropForeign(['team_id']);

            // Restore original foreign key referencing teams
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('set null');
        });
    }
};

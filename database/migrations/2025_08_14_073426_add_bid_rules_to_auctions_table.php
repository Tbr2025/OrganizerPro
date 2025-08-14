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
        // Part 1: Add the bid_rules column to the 'auctions' table
        Schema::table('auctions', function (Blueprint $table) {
            $table->json('bid_rules')->nullable()->after('max_budget_per_team');
        });

        // Part 2: Add the correct foreign key to the 'auction_bids' table
        Schema::table('auction_bids', function (Blueprint $table) {
            // This column is crucial for linking a bid directly to a player in a specific auction.
            // It should be placed after 'auction_id' for logical grouping.
            $table->foreignId('auction_player_id')
                ->nullable() // or ->required() depending on your logic
                ->after('auction_id')
                ->constrained('auction_players') // Explicitly state the table name
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse Part 1
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn('bid_rules');
        });

        // Reverse Part 2
        Schema::table('auction_bids', function (Blueprint $table) {
            // Drop the foreign key constraint first, then the column
            $table->dropForeign(['auction_player_id']);
            $table->dropColumn('auction_player_id');
        });
    }
};

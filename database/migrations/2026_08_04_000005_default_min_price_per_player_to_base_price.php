<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Make the column default 0, which Auction::minPricePerPlayer() reads as
     * "fall back to this auction's base_price".
     *
     * A hardcoded 1,000,000 default is wrong for any auction not created through the
     * wizard — e.g. TournamentController auto-creates a stub auction when a
     * tournament's budget is edited — and an auction whose purse is under
     * squad x 1,000,000 would refuse every bid. Deriving the floor from base_price
     * is always satisfiable, because a player's opening price is by definition
     * affordable. The wizard still lets the organizer set an explicit figure.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('auctions', 'min_price_per_player')) {
            return;
        }

        DB::statement('ALTER TABLE `auctions` MODIFY `min_price_per_player` DECIMAL(15,2) NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        if (! Schema::hasColumn('auctions', 'min_price_per_player')) {
            return;
        }

        DB::statement('ALTER TABLE `auctions` MODIFY `min_price_per_player` DECIMAL(15,2) NOT NULL DEFAULT 1000000');
    }
};

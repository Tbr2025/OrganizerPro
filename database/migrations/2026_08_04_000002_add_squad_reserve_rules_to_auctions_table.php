<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Squad-reserve rule: a team must always retain enough purse to buy the
     * squad slots it still has to fill, so it cannot spend everything on two
     * players and end up unable to field a legal side.
     *
     *   reserve   = max(0, slots_remaining - 1) * min_price_per_player
     *   max_bid   = remaining_budget - reserve
     *
     * These live on the auction (not tournament_settings) so the auction owns
     * its own rule — tournament_settings.min_players_per_team is a
     * registration-era setting that no code currently reads.
     */
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            if (! Schema::hasColumn('auctions', 'min_squad_size')) {
                $table->unsignedInteger('min_squad_size')->default(11)->after('max_budget_per_team');
            }
            if (! Schema::hasColumn('auctions', 'min_price_per_player')) {
                // 0 means "use this auction's base_price" (see
                // Auction::minPricePerPlayer()). A hardcoded floor would deadlock any
                // auction whose purse is smaller than squad x floor.
                $table->decimal('min_price_per_player', 15, 2)->default(0)->after('min_squad_size');
            }
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn(['min_squad_size', 'min_price_per_player']);
        });
    }
};

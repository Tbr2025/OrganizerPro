<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * The bid log becomes append-only so a mis-click can be undone.
     *
     * Previously addBid() used updateOrCreate() keyed on
     * (auction_id, auction_player_id, team_id, user_id), keeping ONE row per
     * team per player and overwriting it on every raise — which left no history
     * to roll back to. Bids are now inserted, and retracted bids are marked
     * voided rather than deleted so the audit trail survives.
     */
    public function up(): void
    {
        Schema::table('auction_bids', function (Blueprint $table) {
            if (! Schema::hasColumn('auction_bids', 'is_void')) {
                $table->boolean('is_void')->default(false)->after('bid_source');
            }
            if (! Schema::hasColumn('auction_bids', 'voided_by')) {
                $table->unsignedBigInteger('voided_by')->nullable()->after('is_void');
            }
            if (! Schema::hasColumn('auction_bids', 'voided_at')) {
                $table->timestamp('voided_at')->nullable()->after('voided_by');
            }
        });

        Schema::table('auction_bids', function (Blueprint $table) {
            // Undo walks a player's bid stack newest-first; the panels read the
            // latest live bid per team.
            $table->index(['auction_player_id', 'is_void', 'id'], 'auction_bids_player_void_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('auction_bids', function (Blueprint $table) {
            $table->dropIndex('auction_bids_player_void_id_idx');
            $table->dropColumn(['is_void', 'voided_by', 'voided_at']);
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A per-player ceiling for OPEN bidding.
 *
 * The sealed round has had one since it was built (`closed_bid_max_pct_of_budget`, a share of
 * a team's allocation that it may not exceed on any one player). Open bidding has only ever
 * had the squad-reserve rule — hold back enough to fill the places you still need — which
 * stops a team going broke but does nothing to stop it spending 90% of its purse on one
 * marquee player and fielding ten minimum-price others.
 *
 * NULLABLE, and null means no ceiling: an auction that does not configure this behaves exactly
 * as it does today. That is deliberate — this deploys days before a live auction, and a new
 * constraint that switches itself on is a way to lose one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->decimal('max_bid_pct_of_budget', 5, 2)
                ->nullable()
                ->after('min_price_per_player');
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn('max_bid_pct_of_budget');
        });
    }
};

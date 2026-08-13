<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How the minimum sealed bid is worked out.
 *
 * `floorFor()` was `snapUpToStep(max(threshold, price))`, which lands ON the standing open bid: a
 * player at 8M got a sealed floor of 8M, so a sealed bid could MATCH the bid already on the table
 * rather than beat it — and win, because the sealed round replaces the open one. A tie-break round
 * has always used `nextLegalAbove()` for exactly this reason; the first round did not.
 *
 * `price_plus_step` is therefore the default: it is the rule the auction already applies once a
 * round is tied, and the one an auctioneer would state out loud.
 *
 *   price            -> the standing price, snapped up to the step   (the old behaviour)
 *   price_plus_step  -> the next legal amount above it               (8M -> 8.1M)
 *   price_plus_fixed -> the price plus `closed_bid_min_offset`
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->string('closed_bid_min_rule', 20)->default('price_plus_step')->after('closed_bid_starts_at');
            $table->decimal('closed_bid_min_offset', 15, 2)->nullable()->after('closed_bid_min_rule');
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn(['closed_bid_min_rule', 'closed_bid_min_offset']);
        });
    }
};

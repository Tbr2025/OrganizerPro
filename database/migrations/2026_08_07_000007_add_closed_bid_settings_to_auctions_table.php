<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-auction rules for the sealed round.
 *
 * `closed_bid_step` is the whole point of the exercise: sealed amounts must land on a
 * fixed grid (0.1M by default) and anything off it is refused rather than rounded.
 * Nothing in the codebase validated a legal amount before, so a sealed bid of
 * 1,234,567 was accepted.
 *
 * `closed_bid_max_pct_of_budget` caps what a team may commit to ONE player, as a
 * percentage of its TOTAL allocated budget — not its remaining purse, and not its
 * post-retention purse. Fixed for the auction, so the figure a team sees never moves
 * under it.
 *
 * All nullable: the edit endpoint is exercised with a minimal payload, so a
 * non-nullable validated field would break every existing save. The accessors own the
 * defaults.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            if (! Schema::hasColumn('auctions', 'closed_bid_step')) {
                $table->decimal('closed_bid_step', 15, 2)->nullable()->after('closed_bid_starts_at');
            }
            if (! Schema::hasColumn('auctions', 'closed_bid_max_pct_of_budget')) {
                $table->decimal('closed_bid_max_pct_of_budget', 5, 2)->nullable()->after('closed_bid_step');
            }
            if (! Schema::hasColumn('auctions', 'closed_bid_max_rebid_rounds')) {
                $table->unsignedTinyInteger('closed_bid_max_rebid_rounds')->nullable()->after('closed_bid_max_pct_of_budget');
            }
            if (! Schema::hasColumn('auctions', 'closed_bid_timer_seconds')) {
                $table->unsignedInteger('closed_bid_timer_seconds')->nullable()->after('closed_bid_max_rebid_rounds');
            }
            if (! Schema::hasColumn('auctions', 'closed_bid_requires_acceptance')) {
                $table->boolean('closed_bid_requires_acceptance')->nullable()->after('closed_bid_timer_seconds');
            }
            if (! Schema::hasColumn('auctions', 'closed_bid_tie_breaker')) {
                $table->string('closed_bid_tie_breaker', 16)->nullable()->after('closed_bid_requires_acceptance');
            }
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            foreach ([
                'closed_bid_step',
                'closed_bid_max_pct_of_budget',
                'closed_bid_max_rebid_rounds',
                'closed_bid_timer_seconds',
                'closed_bid_requires_acceptance',
                'closed_bid_tie_breaker',
            ] as $column) {
                if (Schema::hasColumn('auctions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

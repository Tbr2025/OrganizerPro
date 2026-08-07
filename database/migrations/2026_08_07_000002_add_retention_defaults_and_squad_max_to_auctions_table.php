<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retention defaults and a squad ceiling for an auction.
 *
 * `default_retained_value` exists because the only place a retention price could be
 * entered had no default, and a blank field was stored as 0 — so a retained player
 * cost their team nothing and a whole retention budget silently vanished.
 *
 * All three columns are nullable with NO database default, deliberately. NULL means
 * "use the constant"; 0 stays meaningful and settable — free retentions, or an
 * auction that expects no retentions at all (which suppresses the count warning).
 * A `DEFAULT 5000000` would collapse those two states into one and you could never
 * configure a free retention again.
 *
 * This is intentionally unlike `min_price_per_player`, where 0 ⇒ "fall back" was fine
 * because a money floor of zero has no meaning to preserve.
 *
 * `max_squad_size` is display and soft-warning only. It is deliberately NOT read by
 * the reserve rule, `maxAllowedBid()` or `isExcluded()` — making it bite would change
 * live bidding on every existing auction the moment somebody filled it in.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            if (! Schema::hasColumn('auctions', 'max_squad_size')) {
                $table->unsignedInteger('max_squad_size')->nullable()->after('min_squad_size');
            }

            if (! Schema::hasColumn('auctions', 'default_retained_value')) {
                $table->decimal('default_retained_value', 15, 2)->nullable()->after('min_price_per_player');
            }

            if (! Schema::hasColumn('auctions', 'expected_retained_per_team')) {
                $table->unsignedInteger('expected_retained_per_team')->nullable()->after('default_retained_value');
            }
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            foreach (['max_squad_size', 'default_retained_value', 'expected_retained_per_team'] as $column) {
                if (Schema::hasColumn('auctions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

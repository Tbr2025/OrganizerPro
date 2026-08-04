<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * What auction amounts are called and how they read on screen.
     *
     * Every screen showed bare numbers on a hardcoded Indian ladder (K / L / Cr), which
     * is wrong for auctions run in points, coins or dollars. The ladder is now K / M / B
     * everywhere and the unit is chosen per auction — `usd` renders as a `$` prefix,
     * everything else as a suffix, with `custom` taking free text.
     */
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            if (! Schema::hasColumn('auctions', 'amount_unit')) {
                $table->enum('amount_unit', ['points', 'coins', 'usd', 'custom'])
                    ->default('points')
                    ->after('min_price_per_player');
            }
            if (! Schema::hasColumn('auctions', 'amount_unit_label')) {
                // Only used when amount_unit is 'custom'.
                $table->string('amount_unit_label', 30)->nullable()->after('amount_unit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn(['amount_unit', 'amount_unit_label']);
        });
    }
};

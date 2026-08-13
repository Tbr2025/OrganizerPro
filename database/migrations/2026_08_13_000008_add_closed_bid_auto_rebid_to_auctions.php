<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a tied sealed round opens its own re-bid.
 *
 * Everything a tie-break needs already exists — `startRebid()` builds the child round, sets its
 * floor strictly above the tied amount, marks the tied teams MUST_REBID and the rest MAY_OPT_IN,
 * and starts the clock. The only thing missing was the trigger: a tie stopped at `tie` and waited
 * for somebody to press a button. In a hall that is a pause with nothing happening in it, at the
 * precise moment the room is most interested.
 *
 * Default FALSE, so no existing auction changes behaviour. An organizer who wants the pause —
 * to read the tied amount out, or to let a team query it — keeps it.
 *
 * The number of re-bid rounds is NOT part of this: `closed_bid_max_rebid_rounds` already decides
 * that, and a tie in the final round still goes to a lot rather than opening a round that is not
 * allowed to exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->boolean('closed_bid_auto_rebid')->default(false)->after('closed_bid_requires_acceptance');
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn('closed_bid_auto_rebid');
        });
    }
};

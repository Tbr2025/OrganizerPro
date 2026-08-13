<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether squad lists show HOW a player was acquired.
 *
 * The Icon Player / Auction badge answers a question not every competition wants asked in
 * public: which of a rival's players were kept before the auction and which were bought in the
 * room. `show_amounts` already withholds what they cost; this withholds the badge itself, for
 * organizers who want a squad list to read as a squad rather than as a transfer record.
 *
 * Default true — every squad list shows the badge today, and a migration must not change what a
 * live tournament looks like. Sits beside `show_amounts` because it is the same kind of
 * decision, made once for the competition and inherited by every auction in it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_settings', function (Blueprint $table) {
            $table->boolean('show_acquisition_badge')->default(true)->after('show_amounts');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_settings', function (Blueprint $table) {
            $table->dropColumn('show_acquisition_badge');
        });
    }
};

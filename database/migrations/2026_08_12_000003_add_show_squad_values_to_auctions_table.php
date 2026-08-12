<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether squad lists show what each player cost.
 *
 * Prices on a squad page are useful to the organizer and awkward for everyone else: a team
 * manager's screen is often on a shared table, and what a rival paid for a player is exactly
 * the number people lean over to read. The badge (Icon Player / Retained) stays either way —
 * only the money is switched off.
 *
 * Defaults to TRUE, which is what every squad view does today, so nothing changes until an
 * organizer decides otherwise.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->boolean('show_squad_values')->default(true)->after('amount_unit_label');
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn('show_squad_values');
        });
    }
};

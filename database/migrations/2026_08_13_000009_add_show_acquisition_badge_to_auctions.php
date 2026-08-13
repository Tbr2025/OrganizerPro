<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The badge switch, per auction as well as per tournament.
 *
 * `show_squad_values` has always existed on both — the tournament decides, an auction that
 * overrides answers for itself. The badge setting arrived tournament-only, so an auction with
 * "override the tournament's rules" ticked had no way to say anything about it and silently fell
 * back to always-on. Two switches that sit beside each other on the same screen should behave the
 * same way.
 *
 * Default true, which is what every squad list does today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->boolean('show_acquisition_badge')->default(true)->after('show_squad_values');
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn('show_acquisition_badge');
        });
    }
};

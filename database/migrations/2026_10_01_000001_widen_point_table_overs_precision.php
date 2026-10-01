<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Point table overs were decimal(6,1), so 18.2 overs (18.3333) was stored as 18.3 and the next
 * match added to the rounded figure. NRR is runs over these sums, so the error showed up there.
 *
 * The rows are derived data — PointTableService::recalculatePointTable() rebuilds them from
 * match_results — so nothing is converted here; the next saved result rewrites every entry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('point_table_entries', function (Blueprint $table) {
            $table->decimal('overs_faced', 8, 4)->default(0)->change();
            $table->decimal('overs_bowled', 8, 4)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('point_table_entries', function (Blueprint $table) {
            $table->decimal('overs_faced', 6, 1)->default(0)->change();
            $table->decimal('overs_bowled', 6, 1)->default(0)->change();
        });
    }
};

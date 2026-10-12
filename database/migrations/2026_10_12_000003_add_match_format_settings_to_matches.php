<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A match's playing format, set by the scorer before the first ball:
 *  - innings_per_team: 1 (limited overs) or more (two-innings matches); the sides
 *    bat in turn and the aggregate decides it;
 *  - players_per_team: the side's size, so all out is one fewer wicket. Null =
 *    the named line-up, else eleven (the old rule);
 *  - powerplays: over ranges, e.g. [{"from":1,"to":6}]. Null = none.
 * Existing matches keep one innings each and their current behaviour.
 *
 * Results gain two outcomes multi-innings matches have: won by an innings, and
 * a draw (time ran out with the last innings unfinished).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->unsignedTinyInteger('innings_per_team')->default(1)->after('overs');
            $table->unsignedTinyInteger('players_per_team')->nullable()->after('innings_per_team');
            $table->json('powerplays')->nullable()->after('bowler_overs_limit');
        });

        DB::statement("ALTER TABLE match_results MODIFY result_type ENUM('runs','wickets','tie','no_result','super_over','dls','innings','draw') NULL");
    }

    public function down(): void
    {
        DB::table('match_results')->whereIn('result_type', ['innings', 'draw'])->update(['result_type' => null]);
        DB::statement("ALTER TABLE match_results MODIFY result_type ENUM('runs','wickets','tie','no_result','super_over','dls') NULL");

        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn(['innings_per_team', 'players_per_team', 'powerplays']);
        });
    }
};

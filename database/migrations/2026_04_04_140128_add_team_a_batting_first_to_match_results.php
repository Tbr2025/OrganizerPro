<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('match_results', function (Blueprint $table) {
            $table->boolean('team_a_batting_first')->nullable()->after('toss_decision');
        });

        // Backfill from existing toss data
        DB::table('match_results')
            ->whereNotNull('toss_won_by')
            ->whereNotNull('toss_decision')
            ->get()
            ->each(function ($result) {
                $match = DB::table('matches')->find($result->match_id);
                if (!$match) return;

                if ($result->toss_decision === 'bat') {
                    $teamABatsFirst = $result->toss_won_by == $match->team_a_id;
                } else {
                    $teamABatsFirst = $result->toss_won_by != $match->team_a_id;
                }

                DB::table('match_results')
                    ->where('id', $result->id)
                    ->update(['team_a_batting_first' => $teamABatsFirst]);
            });
    }

    public function down(): void
    {
        Schema::table('match_results', function (Blueprint $table) {
            $table->dropColumn('team_a_batting_first');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Live match state lives on the match, not in the scorer's PHP session.
 *
 * The current innings was `session('match_innings_{id}')` and the batters at
 * the crease were derived from that browser's session too, so any other screen
 * — a second scorer, the LED wall, the public ticker — saw innings 1 forever.
 * And innings were inferred from which team the batsman belonged to, with team
 * A always batting first whatever the toss said.
 *
 * matches: current_innings, batting_first_team_id (from the toss),
 *          striker/non-striker/bowler at the crease, score_version (bumped on
 *          every change so screens and a second scorer can tell they are stale).
 * balls:   innings, non_striker_id and out_batsman_id (a run-out can dismiss the
 *          non-striker), event_uuid (a retried request records the ball once).
 *
 * Backfill keeps every existing ball meaning what it meant: innings 1 was team
 * A's batting and innings 2 team B's, which is what the old code assumed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->unsignedTinyInteger('current_innings')->default(1)->after('toss_decision');
            $table->foreignId('batting_first_team_id')->nullable()->after('current_innings')
                ->constrained('actual_teams')->nullOnDelete();
            $table->foreignId('striker_id')->nullable()->after('batting_first_team_id')
                ->constrained('actual_team_users')->nullOnDelete();
            $table->foreignId('non_striker_id')->nullable()->after('striker_id')
                ->constrained('actual_team_users')->nullOnDelete();
            $table->foreignId('current_bowler_id')->nullable()->after('non_striker_id')
                ->constrained('actual_team_users')->nullOnDelete();
            $table->unsignedInteger('score_version')->default(0)->after('current_bowler_id');
        });

        Schema::table('balls', function (Blueprint $table) {
            $table->unsignedTinyInteger('innings')->nullable()->after('match_id');
            $table->foreignId('non_striker_id')->nullable()->after('batsman_id')
                ->constrained('actual_team_users')->nullOnDelete();
            $table->foreignId('out_batsman_id')->nullable()->after('dismissal_type')
                ->constrained('actual_team_users')->nullOnDelete();
            $table->uuid('event_uuid')->nullable()->unique()->after('id');
            $table->index(['match_id', 'innings', 'id'], 'balls_match_innings_id_index');
        });

        $this->backfill();
    }

    /**
     * Give pre-existing rows the meaning the old code gave them. Idempotent:
     * only fills what is still empty. Public so a test can run it on
     * legacy-shaped rows.
     */
    public function backfill(): void
    {
        // Existing balls: innings by the batsman's team, exactly as the old
        // in_array(team A players) / in_array(team B players) split did.
        DB::statement('
            UPDATE balls b
            JOIN matches m ON m.id = b.match_id
            JOIN actual_team_users atu ON atu.id = b.batsman_id
            SET b.innings = CASE
                WHEN atu.actual_team_id = m.team_a_id THEN 1
                WHEN atu.actual_team_id = m.team_b_id THEN 2
                ELSE NULL END
            WHERE b.innings IS NULL
        ');

        // A wicket always dismissed the striker in the old data.
        DB::statement('UPDATE balls SET out_batsman_id = batsman_id WHERE is_wicket = 1 AND out_batsman_id IS NULL');

        // Matches with ball data batted in team-A-first order.
        DB::statement('
            UPDATE matches m
            SET m.batting_first_team_id = m.team_a_id
            WHERE m.batting_first_team_id IS NULL
              AND EXISTS (SELECT 1 FROM balls b WHERE b.match_id = m.id)
        ');
        DB::statement('
            UPDATE matches m
            SET m.current_innings = 2
            WHERE EXISTS (SELECT 1 FROM balls b WHERE b.match_id = m.id AND b.innings = 2)
        ');
    }

    public function down(): void
    {
        // The match_id foreign key may be leaning on the composite index;
        // give it its own before dropping that.
        $hasOwnIndex = collect(DB::select("SHOW INDEX FROM balls WHERE Column_name = 'match_id' AND Seq_in_index = 1"))
            ->contains(fn ($i) => $i->Key_name !== 'balls_match_innings_id_index');
        if (! $hasOwnIndex) {
            Schema::table('balls', fn (Blueprint $table) => $table->index('match_id'));
        }

        Schema::table('balls', function (Blueprint $table) {
            $table->dropIndex('balls_match_innings_id_index');
            $table->dropUnique(['event_uuid']);
            $table->dropColumn('event_uuid');
            $table->dropConstrainedForeignId('out_batsman_id');
            $table->dropConstrainedForeignId('non_striker_id');
            $table->dropColumn('innings');
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('score_version');
            $table->dropConstrainedForeignId('current_bowler_id');
            $table->dropConstrainedForeignId('non_striker_id');
            $table->dropConstrainedForeignId('striker_id');
            $table->dropConstrainedForeignId('batting_first_team_id');
            $table->dropColumn('current_innings');
        });
    }
};

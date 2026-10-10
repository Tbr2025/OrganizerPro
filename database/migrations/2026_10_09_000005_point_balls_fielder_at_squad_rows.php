<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * balls.fielder_id → actual_team_users, like batsman_id and bowler_id.
 *
 * 2025_08_28_150600 moved batsman_id and bowler_id to actual_team_users but
 * left fielder_id pointing at players, while the scoring page has always sent
 * a squad-row id. So a caught / stumped / run-out wicket with a fielder either
 * failed (500, foreign key) or — when the number happened to also be a player
 * id — saved against the wrong record. (On live at the time of writing no ball
 * had a fielder, so no stored wicket is affected.)
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->dropForeignIfExists('balls_fielder_id_foreign');

        // Anything that is not a squad row cannot be resolved; clear it rather
        // than guess.
        DB::statement('UPDATE balls SET fielder_id = NULL WHERE fielder_id IS NOT NULL AND fielder_id NOT IN (SELECT id FROM actual_team_users)');

        Schema::table('balls', function (Blueprint $table) {
            $table->foreign('fielder_id')->references('id')->on('actual_team_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        $this->dropForeignIfExists('balls_fielder_id_foreign');

        DB::statement('UPDATE balls SET fielder_id = NULL WHERE fielder_id IS NOT NULL AND fielder_id NOT IN (SELECT id FROM players)');

        Schema::table('balls', function (Blueprint $table) {
            $table->foreign('fielder_id')->references('id')->on('players')->nullOnDelete();
        });
    }

    private function dropForeignIfExists(string $name): void
    {
        $exists = DB::selectOne("
            SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_TYPE = 'FOREIGN KEY' AND TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'balls' AND CONSTRAINT_NAME = ?", [$name]);

        if ($exists) {
            Schema::table('balls', fn (Blueprint $table) => $table->dropForeign($name));
        }
    }
};

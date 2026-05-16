<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Migrate existing players.actual_team_id into the new pivot table
        $players = DB::table('players')
            ->whereNotNull('actual_team_id')
            ->get(['id', 'actual_team_id']);

        foreach ($players as $player) {
            $team = DB::table('actual_teams')->where('id', $player->actual_team_id)->first();
            if (!$team || !$team->tournament_id) {
                continue;
            }

            DB::table('player_actual_team_tournament')->insertOrIgnore([
                'player_id' => $player->id,
                'actual_team_id' => $player->actual_team_id,
                'tournament_id' => $team->tournament_id,
                'role' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Data migration — no rollback needed; the table drop handles cleanup
    }
};

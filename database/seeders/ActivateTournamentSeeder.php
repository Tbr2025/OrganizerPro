<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ActivateTournamentSeeder extends Seeder
{
    public function run(): void
    {
        $tournament = DB::table('tournaments')->first();
        if (!$tournament) {
            $this->command->error('No tournament found.');
            return;
        }

        // Skip if already seeded
        if (DB::table('matches')->where('tournament_id', $tournament->id)->exists()) {
            $this->command->info('Tournament data already activated. Skipping.');
            return;
        }

        $tournamentId = $tournament->id;
        $orgId = DB::table('organizations')->value('id') ?? 1;

        // Actual team IDs (dynamically look up)
        $royalId = DB::table('actual_teams')->where('name', 'Royal Strikers')->value('id');
        $thunderId = DB::table('actual_teams')->where('name', 'Thunder Kings')->value('id');
        $desertId = DB::table('actual_teams')->where('name', 'Desert Eagles')->value('id');

        if (!$royalId || !$thunderId || !$desertId) {
            $this->command->error('Actual teams not found. Run DummyTeamsAndPlayersSeeder first.');
            return;
        }

        // Registration team IDs (dynamically look up)
        $royalTeamId = $royalId;
        $thunderTeamId = $thunderId;
        $desertTeamId = $desertId;

        // ─── 1. Update Tournament ───
        DB::table('tournaments')->where('id', $tournamentId)->update([
            'name' => 'Premier Cricket League 2026',
            'slug' => 'premier-cricket-league-2026',
            'start_date' => '2026-03-20',
            'end_date' => '2026-04-10',
            'location' => 'Dubai, UAE',
            'status' => 'active',
            'updated_at' => now(),
        ]);
        $this->command->info('Tournament updated to active.');

        // ─── 2. Create Ground ───
        DB::table('grounds')->insert([
            ['organization_id' => $orgId, 'name' => 'Dubai International Cricket Ground', 'address' => 'Dubai Sports City', 'city' => 'Dubai', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['organization_id' => $orgId, 'name' => 'Sharjah Cricket Stadium', 'address' => 'Al Sharq', 'city' => 'Sharjah', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $groundId = DB::table('grounds')->where('name', 'Dubai International Cricket Ground')->value('id');
        $ground2Id = DB::table('grounds')->where('name', 'Sharjah Cricket Stadium')->value('id');
        $this->command->info('Grounds created.');

        // ─── 3. Create Tournament Settings ───
        DB::table('tournament_settings')->insert([
            'tournament_id' => $tournamentId,
            'primary_color' => '#1a56db',
            'secondary_color' => '#ffffff',
            'player_registration_open' => true,
            'team_registration_open' => true,
            'registration_deadline' => '2026-03-25 23:59:59',
            'max_players_per_team' => 15,
            'min_players_per_team' => 11,
            'format' => 'league',
            'number_of_groups' => 1,
            'teams_per_group' => 3,
            'matches_per_week' => 3,
            'number_of_grounds' => 2,
            'has_quarter_finals' => false,
            'has_semi_finals' => false,
            'has_third_place' => false,
            'overs_per_match' => 20,
            'points_per_win' => 2,
            'points_per_tie' => 1,
            'points_per_no_result' => 1,
            'points_per_loss' => 0,
            'match_poster_days_before' => 3,
            'send_match_reminders' => true,
            'send_result_notifications' => true,
            'auto_send_welcome_cards' => true,
            'auto_send_flyer_on_registration' => true,
            'auto_send_match_summary' => true,
            'description' => 'Premier Cricket League 2026 - A T20 cricket tournament featuring the best teams from across the UAE.',
            'rules' => "1. Each match is 20 overs per side.\n2. Teams must have a minimum of 11 players.\n3. DLS method applies for rain-affected matches.\n4. Super Over in case of a tie.\n5. League format - all teams play each other twice.",
            'contact_email' => 'info@premierleague.ae',
            'contact_phone' => '+971501234567',
            'whatsapp_contact' => '+971501234567',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->command->info('Tournament settings created.');

        // ─── 4. Create Tournament Group ───
        $groupId = DB::table('tournament_groups')->insertGetId([
            'tournament_id' => $tournamentId,
            'name' => 'League Stage',
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Assign teams to group
        DB::table('tournament_group_teams')->insert([
            ['tournament_group_id' => $groupId, 'actual_team_id' => $royalId, 'order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['tournament_group_id' => $groupId, 'actual_team_id' => $thunderId, 'order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['tournament_group_id' => $groupId, 'actual_team_id' => $desertId, 'order' => 3, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->command->info('Tournament group and team assignments created.');

        // ─── 5. Create Tournament Registrations (approved) ───
        $teams = [
            [$royalId, $royalTeamId, 'Royal Strikers', 'RST', 'Arjun Mehta', 'arjun.mehta@example.com', '+971501111111', 'Ravi Prasad', '+971501111112'],
            [$thunderId, $thunderTeamId, 'Thunder Kings', 'TKS', 'Imran Syed', 'imran.syed@example.com', '+971502222221', 'Nikhil Patel', '+971502222222'],
            [$desertId, $desertTeamId, 'Desert Eagles', 'DEG', 'Ajay Krishnan', 'ajay.krishnan@example.com', '+971503333331', 'Pranav Mohan', '+971503333332'],
        ];

        foreach ($teams as [$actualId, $teamId, $name, $short, $captain, $email, $phone, $vc, $vcPhone]) {
            DB::table('tournament_registrations')->insert([
                'tournament_id' => $tournamentId,
                'type' => 'team',
                'team_name' => $name,
                'team_short_name' => $short,
                'captain_name' => $captain,
                'captain_email' => $email,
                'captain_phone' => $phone,
                'vice_captain_name' => $vc,
                'vice_captain_phone' => $vcPhone,
                'status' => 'approved',
                'processed_at' => now(),
                'processed_by' => 1,
                'actual_team_id' => $actualId,
                'welcome_card_sent' => true,
                'welcome_card_sent_at' => now(),
                'flyer_sent' => true,
                'flyer_sent_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->command->info('Tournament registrations created.');

        // ─── 6. Create Matches (6 league matches - each team plays others twice) ───
        $matches = [
            // Round 1
            ['name' => 'Match 1: Royal Strikers vs Thunder Kings', 'num' => 1, 'a' => $royalTeamId, 'b' => $thunderTeamId, 'date' => '2026-03-22', 'start' => '10:00', 'end' => '14:00', 'ground' => $groundId, 'status' => 'completed'],
            ['name' => 'Match 2: Thunder Kings vs Desert Eagles', 'num' => 2, 'a' => $thunderTeamId, 'b' => $desertTeamId, 'date' => '2026-03-24', 'start' => '10:00', 'end' => '14:00', 'ground' => $ground2Id, 'status' => 'completed'],
            ['name' => 'Match 3: Desert Eagles vs Royal Strikers', 'num' => 3, 'a' => $desertTeamId, 'b' => $royalTeamId, 'date' => '2026-03-26', 'start' => '14:00', 'end' => '18:00', 'ground' => $groundId, 'status' => 'completed'],
            // Round 2
            ['name' => 'Match 4: Thunder Kings vs Royal Strikers', 'num' => 4, 'a' => $thunderTeamId, 'b' => $royalTeamId, 'date' => '2026-03-29', 'start' => '10:00', 'end' => '14:00', 'ground' => $ground2Id, 'status' => 'upcoming'],
            ['name' => 'Match 5: Desert Eagles vs Thunder Kings', 'num' => 5, 'a' => $desertTeamId, 'b' => $thunderTeamId, 'date' => '2026-04-01', 'start' => '14:00', 'end' => '18:00', 'ground' => $groundId, 'status' => 'upcoming'],
            ['name' => 'Match 6: Royal Strikers vs Desert Eagles', 'num' => 6, 'a' => $royalTeamId, 'b' => $desertTeamId, 'date' => '2026-04-03', 'start' => '10:00', 'end' => '14:00', 'ground' => $ground2Id, 'status' => 'upcoming'],
            // Final
            ['name' => 'Final', 'num' => 7, 'a' => $royalTeamId, 'b' => $thunderTeamId, 'date' => '2026-04-10', 'start' => '16:00', 'end' => '20:00', 'ground' => $groundId, 'status' => 'upcoming', 'stage' => 'final'],
        ];

        $matchIds = [];
        foreach ($matches as $m) {
            $slug = \Illuminate\Support\Str::slug($m['name']) . '-' . \Illuminate\Support\Str::random(6);
            $matchIds[] = DB::table('matches')->insertGetId([
                'tournament_id' => $tournamentId,
                'tournament_group_id' => ($m['stage'] ?? 'league') === 'final' ? null : $groupId,
                'name' => $m['name'],
                'slug' => $slug,
                'overs' => 20,
                'team_a_id' => $m['a'],
                'team_b_id' => $m['b'],
                'match_date' => $m['date'] . ' ' . $m['start'] . ':00',
                'start_time' => $m['start'] . ':00',
                'end_time' => $m['end'] . ':00',
                'venue' => $m['ground'] == $groundId ? 'Dubai International Cricket Ground' : 'Sharjah Cricket Stadium',
                'ground_id' => $m['ground'],
                'status' => $m['status'],
                'stage' => $m['stage'] ?? 'league',
                'match_number' => $m['num'],
                'poster_sent' => false,
                'is_cancelled' => false,
                'winner_team_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->command->info('7 matches created (3 completed, 3 upcoming, 1 final).');

        // ─── 7. Match Results (for completed matches) ───
        // Match 1: Royal Strikers (185/4) beat Thunder Kings (172/8) by 13 runs
        DB::table('match_results')->insert([
            'match_id' => $matchIds[0],
            'team_a_score' => 185, 'team_a_wickets' => 4, 'team_a_overs' => 20.0, 'team_a_extras' => 12,
            'team_b_score' => 172, 'team_b_wickets' => 8, 'team_b_overs' => 20.0, 'team_b_extras' => 8,
            'result_summary' => 'Royal Strikers won by 13 runs',
            'winner_team_id' => $royalId,
            'result_type' => 'runs', 'margin' => 13,
            'toss_won_by' => $royalId, 'toss_decision' => 'bat',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('matches')->where('id', $matchIds[0])->update(['winner_team_id' => $royalTeamId, 'toss_winner_team_id' => $royalId, 'toss_decision' => 'bat']);

        // Match 2: Thunder Kings (156/6) beat Desert Eagles (148/9) — Desert Eagles batting first
        DB::table('match_results')->insert([
            'match_id' => $matchIds[1],
            'team_a_score' => 156, 'team_a_wickets' => 6, 'team_a_overs' => 19.2, 'team_a_extras' => 10,
            'team_b_score' => 148, 'team_b_wickets' => 9, 'team_b_overs' => 20.0, 'team_b_extras' => 6,
            'result_summary' => 'Thunder Kings won by 4 wickets',
            'winner_team_id' => $thunderId,
            'result_type' => 'wickets', 'margin' => 4,
            'toss_won_by' => $desertId, 'toss_decision' => 'bat',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('matches')->where('id', $matchIds[1])->update(['winner_team_id' => $thunderTeamId, 'toss_winner_team_id' => $desertId, 'toss_decision' => 'bat']);

        // Match 3: Desert Eagles (192/5) beat Royal Strikers (178/7) by 14 runs
        DB::table('match_results')->insert([
            'match_id' => $matchIds[2],
            'team_a_score' => 192, 'team_a_wickets' => 5, 'team_a_overs' => 20.0, 'team_a_extras' => 9,
            'team_b_score' => 178, 'team_b_wickets' => 7, 'team_b_overs' => 20.0, 'team_b_extras' => 11,
            'result_summary' => 'Desert Eagles won by 14 runs',
            'winner_team_id' => $desertId,
            'result_type' => 'runs', 'margin' => 14,
            'toss_won_by' => $desertId, 'toss_decision' => 'bat',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('matches')->where('id', $matchIds[2])->update(['winner_team_id' => $desertTeamId, 'toss_winner_team_id' => $desertId, 'toss_decision' => 'bat']);

        $this->command->info('Match results created.');

        // ─── 8. Point Table ───
        // Royal Strikers: W1 L1 = 2pts, scored 363 in 40 overs, conceded 364 in 39.2 overs
        // Thunder Kings: W1 L1 = 2pts, scored 328 in 39.2 overs, conceded 341 in 40 overs
        // Desert Eagles: W1 L1 = 2pts, scored 340 in 40 overs, conceded 334 in 39.2 overs
        DB::table('point_table_entries')->insert([
            [
                'tournament_id' => $tournamentId, 'tournament_group_id' => $groupId, 'actual_team_id' => $royalId,
                'matches_played' => 2, 'won' => 1, 'lost' => 1, 'tied' => 0, 'no_result' => 0, 'points' => 2,
                'runs_scored' => 363, 'overs_faced' => 40.0, 'runs_conceded' => 364, 'overs_bowled' => 39.2,
                'net_run_rate' => 0.188, 'position' => 1, 'qualified' => false,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'tournament_id' => $tournamentId, 'tournament_group_id' => $groupId, 'actual_team_id' => $desertId,
                'matches_played' => 2, 'won' => 1, 'lost' => 1, 'tied' => 0, 'no_result' => 0, 'points' => 2,
                'runs_scored' => 340, 'overs_faced' => 40.0, 'runs_conceded' => 334, 'overs_bowled' => 39.2,
                'net_run_rate' => 0.018, 'position' => 2, 'qualified' => false,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'tournament_id' => $tournamentId, 'tournament_group_id' => $groupId, 'actual_team_id' => $thunderId,
                'matches_played' => 2, 'won' => 1, 'lost' => 1, 'tied' => 0, 'no_result' => 0, 'points' => 2,
                'runs_scored' => 328, 'overs_faced' => 39.2, 'runs_conceded' => 341, 'overs_bowled' => 40.0,
                'net_run_rate' => -0.206, 'position' => 3, 'qualified' => false,
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
        $this->command->info('Point table entries created.');

        // ─── 9. Player Statistics ───
        $allPlayers = DB::table('players')->whereIn('actual_team_id', [$royalId, $thunderId, $desertId])->get();

        foreach ($allPlayers as $player) {
            $isBatsman = in_array($player->player_type_id, [1, 4, 5]); // Batsman, Captain, Vice-Captain
            $isBowler = $player->player_type_id == 2;
            $isAllRounder = $player->player_type_id == 3;
            $isKeeper = $player->is_wicket_keeper;

            $matches = 2;
            $inningsBatted = rand(1, 2);

            if ($isBatsman || $player->player_type_id == 4 || $player->player_type_id == 5) {
                $runs = rand(40, 120);
                $ballsFaced = rand(30, 80);
                $fours = rand(3, 10);
                $sixes = rand(0, 5);
                $highestScore = rand((int)($runs * 0.5), $runs);
                $fifties = $highestScore >= 50 ? 1 : 0;
                $wickets = $isAllRounder ? rand(1, 4) : 0;
                $oversBowled = $isAllRounder ? rand(4, 8) : 0;
                $runsConceded = $oversBowled * rand(6, 9);
                $inningsBowled = $oversBowled > 0 ? rand(1, 2) : 0;
            } elseif ($isBowler) {
                $runs = rand(5, 30);
                $ballsFaced = rand(5, 20);
                $fours = rand(0, 3);
                $sixes = rand(0, 1);
                $highestScore = $runs;
                $fifties = 0;
                $oversBowled = rand(6, 8);
                $wickets = rand(2, 6);
                $runsConceded = $oversBowled * rand(5, 8);
                $inningsBowled = 2;
            } elseif ($isAllRounder) {
                $runs = rand(30, 80);
                $ballsFaced = rand(20, 55);
                $fours = rand(2, 7);
                $sixes = rand(1, 4);
                $highestScore = rand((int)($runs * 0.5), $runs);
                $fifties = $highestScore >= 50 ? 1 : 0;
                $oversBowled = rand(4, 8);
                $wickets = rand(2, 5);
                $runsConceded = $oversBowled * rand(6, 9);
                $inningsBowled = 2;
            } else {
                $runs = rand(20, 70);
                $ballsFaced = rand(15, 50);
                $fours = rand(1, 6);
                $sixes = rand(0, 3);
                $highestScore = $runs;
                $fifties = $highestScore >= 50 ? 1 : 0;
                $wickets = 0;
                $oversBowled = 0;
                $runsConceded = 0;
                $inningsBowled = 0;
            }

            $bestBowling = $wickets > 0 ? $wickets . '/' . rand(15, 35) : null;

            DB::table('player_statistics')->insert([
                'tournament_id' => $tournamentId,
                'player_id' => $player->id,
                'actual_team_id' => $player->actual_team_id,
                'matches' => $matches,
                'innings_batted' => $inningsBatted,
                'runs' => $runs,
                'balls_faced' => $ballsFaced,
                'fours' => $fours,
                'sixes' => $sixes,
                'highest_score' => $highestScore,
                'highest_not_out' => (bool)rand(0, 1),
                'fifties' => $fifties,
                'hundreds' => 0,
                'not_outs' => rand(0, 1),
                'ducks' => $runs < 10 ? rand(0, 1) : 0,
                'innings_bowled' => $inningsBowled,
                'overs_bowled' => $oversBowled,
                'runs_conceded' => $runsConceded,
                'wickets' => $wickets,
                'maidens' => $oversBowled > 4 ? rand(0, 1) : 0,
                'best_bowling' => $bestBowling,
                'four_wickets' => $wickets >= 4 ? 1 : 0,
                'five_wickets' => $wickets >= 5 ? 1 : 0,
                'wides' => rand(0, 4),
                'no_balls' => rand(0, 2),
                'catches' => rand(0, 3),
                'stumpings' => $isKeeper ? rand(0, 2) : 0,
                'run_outs' => rand(0, 1),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->command->info('Player statistics created for all 33 players.');

        // ─── 10. Match Time Slots ───
        foreach ($matches as $i => $m) {
            DB::table('match_time_slots')->insert([
                'tournament_id' => $tournamentId,
                'ground_id' => $m['ground'],
                'slot_date' => $m['date'],
                'start_time' => $m['start'] . ':00',
                'end_time' => $m['end'] . ':00',
                'is_available' => false,
                'match_id' => $matchIds[$i],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->command->info('Match time slots created.');

        // ─── 11. Match Summaries (for completed matches) ───
        $summaries = [
            [$matchIds[0], 'Royal Strikers put up a commanding 185/4 with Arjun Mehta scoring a brilliant 67. Thunder Kings fell short at 172/8 despite Imran Syed\'s 54.'],
            [$matchIds[1], 'Thunder Kings chased down 149 with 4 wickets in hand. Bipin Varghese picked up 3/22 to restrict Desert Eagles to 148/9.'],
            [$matchIds[2], 'Desert Eagles posted a massive 192/5 with Ajay Krishnan\'s explosive 78*. Royal Strikers fought hard but could only manage 178/7.'],
        ];

        foreach ($summaries as [$matchId, $commentary]) {
            DB::table('match_summaries')->insert([
                'match_id' => $matchId,
                'highlights' => json_encode(['Top scorer', 'Best bowler', 'Key moments']),
                'commentary' => $commentary,
                'poster_template' => 'classic',
                'poster_sent' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->command->info('Match summaries created.');

        $this->command->info('All tournament data activated successfully!');
    }
}

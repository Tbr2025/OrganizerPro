<?php

namespace Database\Seeders;

use App\Models\ActualTeam;
use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DummyTeamsAndPlayersSeeder extends Seeder
{
    public function run(): void
    {
        $organizationId = 1;
        $tournament = Tournament::first();

        if (!$tournament) {
            $this->command->error('No tournament found. Please create a tournament first.');
            return;
        }

        // Skip if already seeded
        if (ActualTeam::where('name', 'Royal Strikers')->exists()) {
            $this->command->info('Dummy teams already exist. Skipping.');
            return;
        }

        $teams = [
            [
                'name' => 'Royal Strikers',
                'short_name' => 'RST',
                'location' => 'Dubai',
                'primary_color' => '#1E3A8A',
                'secondary_color' => '#FBBF24',
                'players' => [
                    ['name' => 'Arjun Mehta',     'jersey_name' => 'MEHTA',     'jersey_number' => 7,  'player_type_id' => 4, 'batting_profile_id' => 1,  'bowling_profile_id' => 2, 'is_wicket_keeper' => false, 'role' => 'captain',       'total_matches' => 85, 'total_runs' => 3200, 'total_wickets' => 12],
                    ['name' => 'Rahul Sharma',    'jersey_name' => 'SHARMA',    'jersey_number' => 1,  'player_type_id' => 1, 'batting_profile_id' => 4,  'bowling_profile_id' => 3, 'is_wicket_keeper' => false, 'role' => null,            'total_matches' => 72, 'total_runs' => 2800, 'total_wickets' => 5],
                    ['name' => 'Vikram Singh',    'jersey_name' => 'VIKRAM',    'jersey_number' => 3,  'player_type_id' => 1, 'batting_profile_id' => 5,  'bowling_profile_id' => null, 'is_wicket_keeper' => false, 'role' => null,          'total_matches' => 60, 'total_runs' => 2100, 'total_wickets' => 0],
                    ['name' => 'Faisal Khan',     'jersey_name' => 'FAISAL',    'jersey_number' => 5,  'player_type_id' => 3, 'batting_profile_id' => 6,  'bowling_profile_id' => 3, 'is_wicket_keeper' => false, 'role' => null,            'total_matches' => 55, 'total_runs' => 1500, 'total_wickets' => 45],
                    ['name' => 'Deepak Nair',     'jersey_name' => 'NAIR',      'jersey_number' => 9,  'player_type_id' => 1, 'batting_profile_id' => 12, 'bowling_profile_id' => null, 'is_wicket_keeper' => false, 'role' => null,          'total_matches' => 48, 'total_runs' => 1200, 'total_wickets' => 0],
                    ['name' => 'Suresh Pillai',   'jersey_name' => 'PILLAI',    'jersey_number' => 11, 'player_type_id' => 1, 'batting_profile_id' => 16, 'bowling_profile_id' => null, 'is_wicket_keeper' => true,  'role' => 'wicket_keeper', 'total_matches' => 65, 'total_runs' => 1800, 'total_wickets' => 0],
                    ['name' => 'Mohammed Ashraf', 'jersey_name' => 'ASHRAF',    'jersey_number' => 15, 'player_type_id' => 3, 'batting_profile_id' => 7,  'bowling_profile_id' => 5, 'is_wicket_keeper' => false, 'role' => null,            'total_matches' => 50, 'total_runs' => 800,  'total_wickets' => 55],
                    ['name' => 'Anil Kumar',      'jersey_name' => 'ANIL',      'jersey_number' => 22, 'player_type_id' => 2, 'batting_profile_id' => 9,  'bowling_profile_id' => 1, 'is_wicket_keeper' => false, 'role' => null,            'total_matches' => 70, 'total_runs' => 250,  'total_wickets' => 95],
                    ['name' => 'Pradeep Menon',   'jersey_name' => 'MENON',     'jersey_number' => 33, 'player_type_id' => 2, 'batting_profile_id' => 8,  'bowling_profile_id' => 2, 'is_wicket_keeper' => false, 'role' => null,            'total_matches' => 58, 'total_runs' => 320,  'total_wickets' => 78],
                    ['name' => 'Sajid Ali',       'jersey_name' => 'SAJID',     'jersey_number' => 44, 'player_type_id' => 2, 'batting_profile_id' => 9,  'bowling_profile_id' => 7, 'is_wicket_keeper' => false, 'role' => null,            'total_matches' => 42, 'total_runs' => 150,  'total_wickets' => 62],
                    ['name' => 'Ravi Prasad',     'jersey_name' => 'PRASAD',    'jersey_number' => 10, 'player_type_id' => 5, 'batting_profile_id' => 6,  'bowling_profile_id' => 2, 'is_wicket_keeper' => false, 'role' => 'vice_captain',  'total_matches' => 68, 'total_runs' => 1900, 'total_wickets' => 30],
                ],
            ],
            [
                'name' => 'Thunder Kings',
                'short_name' => 'TKS',
                'location' => 'Abu Dhabi',
                'primary_color' => '#DC2626',
                'secondary_color' => '#FFFFFF',
                'players' => [
                    ['name' => 'Imran Syed',      'jersey_name' => 'IMRAN',     'jersey_number' => 10, 'player_type_id' => 4, 'batting_profile_id' => 1,  'bowling_profile_id' => 3, 'is_wicket_keeper' => false, 'role' => 'captain',       'total_matches' => 90, 'total_runs' => 3500, 'total_wickets' => 40],
                    ['name' => 'Karthik Rajan',   'jersey_name' => 'KARTHIK',   'jersey_number' => 2,  'player_type_id' => 1, 'batting_profile_id' => 4,  'bowling_profile_id' => null, 'is_wicket_keeper' => false, 'role' => null,          'total_matches' => 78, 'total_runs' => 3100, 'total_wickets' => 0],
                    ['name' => 'Naveen Thomas',   'jersey_name' => 'NAVEEN',    'jersey_number' => 4,  'player_type_id' => 1, 'batting_profile_id' => 2,  'bowling_profile_id' => null, 'is_wicket_keeper' => false, 'role' => null,          'total_matches' => 55, 'total_runs' => 1900, 'total_wickets' => 0],
                    ['name' => 'Abdul Rashid',    'jersey_name' => 'RASHID',    'jersey_number' => 6,  'player_type_id' => 3, 'batting_profile_id' => 6,  'bowling_profile_id' => 7, 'is_wicket_keeper' => false, 'role' => null,            'total_matches' => 65, 'total_runs' => 1600, 'total_wickets' => 70],
                    ['name' => 'Sanjay Mohan',    'jersey_name' => 'SANJAY',    'jersey_number' => 8,  'player_type_id' => 1, 'batting_profile_id' => 14, 'bowling_profile_id' => null, 'is_wicket_keeper' => false, 'role' => null,          'total_matches' => 45, 'total_runs' => 1400, 'total_wickets' => 0],
                    ['name' => 'Jithin Jose',     'jersey_name' => 'JITHIN',    'jersey_number' => 17, 'player_type_id' => 1, 'batting_profile_id' => 16, 'bowling_profile_id' => null, 'is_wicket_keeper' => true,  'role' => 'wicket_keeper', 'total_matches' => 70, 'total_runs' => 1700, 'total_wickets' => 0],
                    ['name' => 'Shajin Babu',     'jersey_name' => 'SHAJIN',    'jersey_number' => 19, 'player_type_id' => 3, 'batting_profile_id' => 7,  'bowling_profile_id' => 1, 'is_wicket_keeper' => false, 'role' => null,            'total_matches' => 52, 'total_runs' => 900,  'total_wickets' => 48],
                    ['name' => 'Bipin Varghese',  'jersey_name' => 'BIPIN',     'jersey_number' => 21, 'player_type_id' => 2, 'batting_profile_id' => 9,  'bowling_profile_id' => 4, 'is_wicket_keeper' => false, 'role' => null,            'total_matches' => 62, 'total_runs' => 200,  'total_wickets' => 88],
                    ['name' => 'Anwar Hussain',   'jersey_name' => 'ANWAR',     'jersey_number' => 25, 'player_type_id' => 2, 'batting_profile_id' => 8,  'bowling_profile_id' => 5, 'is_wicket_keeper' => false, 'role' => null,            'total_matches' => 55, 'total_runs' => 280,  'total_wickets' => 72],
                    ['name' => 'Dileep Krishnan', 'jersey_name' => 'DILEEP',    'jersey_number' => 30, 'player_type_id' => 2, 'batting_profile_id' => 9,  'bowling_profile_id' => 6, 'is_wicket_keeper' => false, 'role' => null,            'total_matches' => 40, 'total_runs' => 120,  'total_wickets' => 58],
                    ['name' => 'Nikhil Patel',    'jersey_name' => 'NIKHIL',    'jersey_number' => 12, 'player_type_id' => 5, 'batting_profile_id' => 5,  'bowling_profile_id' => 3, 'is_wicket_keeper' => false, 'role' => 'vice_captain',  'total_matches' => 75, 'total_runs' => 2200, 'total_wickets' => 25],
                ],
            ],
            [
                'name' => 'Desert Eagles',
                'short_name' => 'DEG',
                'location' => 'Sharjah',
                'primary_color' => '#059669',
                'secondary_color' => '#F59E0B',
                'players' => [
                    ['name' => 'Ajay Krishnan',   'jersey_name' => 'AJAY',      'jersey_number' => 18, 'player_type_id' => 4, 'batting_profile_id' => 2,  'bowling_profile_id' => 5, 'is_wicket_keeper' => false, 'role' => 'captain',       'total_matches' => 82, 'total_runs' => 2900, 'total_wickets' => 35],
                    ['name' => 'Manoj Thampi',    'jersey_name' => 'MANOJ',     'jersey_number' => 1,  'player_type_id' => 1, 'batting_profile_id' => 4,  'bowling_profile_id' => null, 'is_wicket_keeper' => false, 'role' => null,          'total_matches' => 68, 'total_runs' => 2700, 'total_wickets' => 0],
                    ['name' => 'Vishnu Das',      'jersey_name' => 'VISHNU',    'jersey_number' => 3,  'player_type_id' => 1, 'batting_profile_id' => 1,  'bowling_profile_id' => null, 'is_wicket_keeper' => false, 'role' => null,          'total_matches' => 57, 'total_runs' => 2000, 'total_wickets' => 0],
                    ['name' => 'Shahin Musthafa', 'jersey_name' => 'SHAHIN',    'jersey_number' => 5,  'player_type_id' => 3, 'batting_profile_id' => 6,  'bowling_profile_id' => 3, 'is_wicket_keeper' => false, 'role' => null,            'total_matches' => 60, 'total_runs' => 1700, 'total_wickets' => 52],
                    ['name' => 'Sreejith Nair',   'jersey_name' => 'SREEJITH',  'jersey_number' => 9,  'player_type_id' => 1, 'batting_profile_id' => 13, 'bowling_profile_id' => null, 'is_wicket_keeper' => false, 'role' => null,          'total_matches' => 50, 'total_runs' => 1500, 'total_wickets' => 0],
                    ['name' => 'George Mathew',   'jersey_name' => 'GEORGE',    'jersey_number' => 14, 'player_type_id' => 1, 'batting_profile_id' => 16, 'bowling_profile_id' => null, 'is_wicket_keeper' => true,  'role' => 'wicket_keeper', 'total_matches' => 73, 'total_runs' => 1600, 'total_wickets' => 0],
                    ['name' => 'Akhil Babu',      'jersey_name' => 'AKHIL',     'jersey_number' => 16, 'player_type_id' => 3, 'batting_profile_id' => 7,  'bowling_profile_id' => 2, 'is_wicket_keeper' => false, 'role' => null,            'total_matches' => 48, 'total_runs' => 750,  'total_wickets' => 42],
                    ['name' => 'Midhun Raj',      'jersey_name' => 'MIDHUN',    'jersey_number' => 23, 'player_type_id' => 2, 'batting_profile_id' => 9,  'bowling_profile_id' => 1, 'is_wicket_keeper' => false, 'role' => null,            'total_matches' => 66, 'total_runs' => 300,  'total_wickets' => 92],
                    ['name' => 'Noufal Ibrahim',  'jersey_name' => 'NOUFAL',    'jersey_number' => 27, 'player_type_id' => 2, 'batting_profile_id' => 8,  'bowling_profile_id' => 4, 'is_wicket_keeper' => false, 'role' => null,            'total_matches' => 54, 'total_runs' => 260,  'total_wickets' => 68],
                    ['name' => 'Vineeth Kumar',   'jersey_name' => 'VINEETH',   'jersey_number' => 35, 'player_type_id' => 2, 'batting_profile_id' => 9,  'bowling_profile_id' => 7, 'is_wicket_keeper' => false, 'role' => null,            'total_matches' => 38, 'total_runs' => 100,  'total_wickets' => 55],
                    ['name' => 'Pranav Mohan',    'jersey_name' => 'PRANAV',    'jersey_number' => 11, 'player_type_id' => 5, 'batting_profile_id' => 5,  'bowling_profile_id' => 2, 'is_wicket_keeper' => false, 'role' => 'vice_captain',  'total_matches' => 70, 'total_runs' => 2100, 'total_wickets' => 28],
                ],
            ],
        ];

        Player::unguard();
        Team::unguard();

        foreach ($teams as $teamData) {
            // Create registration team
            $team = Team::create([
                'name' => $teamData['name'],
                'short_name' => $teamData['short_name'],
                'tournament_id' => $tournament->id,
                'organization_id' => $organizationId,
                'admin_id' => 1,
            ]);

            // Create actual team
            $actualTeam = ActualTeam::create([
                'organization_id' => $organizationId,
                'tournament_id' => $tournament->id,
                'name' => $teamData['name'],
                'short_name' => $teamData['short_name'],
                'location' => $teamData['location'],
                'primary_color' => $teamData['primary_color'],
                'secondary_color' => $teamData['secondary_color'],
            ]);

            foreach ($teamData['players'] as $index => $playerData) {
                $player = Player::create([
                    'team_id' => $team->id,
                    'actual_team_id' => $actualTeam->id,
                    'name' => $playerData['name'],
                    'jersey_name' => $playerData['jersey_name'],
                    'jersey_number' => $playerData['jersey_number'],
                    'player_type_id' => $playerData['player_type_id'],
                    'batting_profile_id' => $playerData['batting_profile_id'],
                    'bowling_profile_id' => $playerData['bowling_profile_id'],
                    'is_wicket_keeper' => $playerData['is_wicket_keeper'],
                    'status' => 'approved',
                    'player_mode' => 'retained',
                    'total_matches' => $playerData['total_matches'],
                    'total_runs' => $playerData['total_runs'],
                    'total_wickets' => $playerData['total_wickets'],
                    'mobile_country_code' => '+971',
                    'mobile_national_number' => '50' . rand(1000000, 9999999),
                    'mobile_number_full' => '+97150' . rand(1000000, 9999999),
                    'email' => strtolower(str_replace(' ', '.', $playerData['name'])) . '@example.com',
                ]);

                // Link player to team via pivot
                DB::table('player_team_tournament')->insert([
                    'player_id' => $player->id,
                    'team_id' => $team->id,
                    'tournament_id' => $tournament->id,
                    'organization_id' => $organizationId,
                    'role' => $playerData['role'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->command->info("Created team: {$teamData['name']} with 11 players");
        }

        $this->command->info('Done! 3 teams with 33 players created.');
    }
}

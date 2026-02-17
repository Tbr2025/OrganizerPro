<?php

namespace Database\Seeders;

use App\Models\ActualTeam;
use App\Models\BattingProfile;
use App\Models\BowlingProfile;
use App\Models\KitSize;
use App\Models\Organization;
use App\Models\Player;
use App\Models\PlayerLocation;
use App\Models\PlayerType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestPlayersSeeder extends Seeder
{
    public function run(): void
    {
        $teams = ActualTeam::all();
        $organization = Organization::first();
        $playerTypes = PlayerType::pluck('id')->toArray();
        $battingProfiles = BattingProfile::pluck('id')->toArray();
        $bowlingProfiles = BowlingProfile::pluck('id')->toArray();
        $kitSizes = KitSize::pluck('id')->toArray();
        $locations = PlayerLocation::pluck('id')->toArray();

        // Player names for testing
        $playerNames = [
            'Rahul Sharma', 'Virat Singh', 'Mohammed Ali', 'Arun Kumar', 'Sachin Patel',
            'Rohit Gupta', 'Amit Verma', 'Suresh Nair', 'Kiran Reddy', 'Deepak Joshi',
            'Rajesh Menon', 'Vikram Iyer', 'Ankit Desai', 'Manish Kapoor', 'Sanjay Mishra',
            'Pradeep Rao', 'Nikhil Choudhary', 'Ajay Pillai', 'Ravi Shankar', 'Ashok Mehta',
            'Vivek Sinha', 'Gaurav Tiwari', 'Harish Naidu', 'Manoj Kulkarni'
        ];

        $playerIndex = 0;

        foreach ($teams as $team) {
            for ($i = 1; $i <= 12; $i++) {
                $name = $playerNames[$playerIndex] ?? "Player {$playerIndex}";
                $email = strtolower(str_replace(' ', '.', $name)) . $playerIndex . '@test.com';

                // Create User
                $username = strtolower(str_replace(' ', '_', $name)) . $playerIndex;
                $user = User::create([
                    'name' => $name,
                    'username' => $username,
                    'email' => $email,
                    'password' => Hash::make('password123'),
                    'organization_id' => $organization?->id,
                    'email_verified_at' => now(),
                ]);

                // Assign Player role
                $user->assignRole('Player');

                // Create Player record
                $player = Player::create([
                    'user_id' => $user->id,
                    'name' => $name,
                    'email' => $email,
                    'mobile_country_code' => '971',
                    'mobile_national_number' => '50' . rand(1000000, 9999999),
                    'mobile_number_full' => '97150' . rand(1000000, 9999999),
                    'jersey_name' => strtoupper(explode(' ', $name)[1] ?? $name),
                    'jersey_number' => $i,
                    'kit_size_id' => $kitSizes[array_rand($kitSizes)] ?? null,
                    'player_type_id' => $playerTypes[array_rand($playerTypes)] ?? null,
                    'batting_profile_id' => $battingProfiles[array_rand($battingProfiles)] ?? null,
                    'bowling_profile_id' => $bowlingProfiles[array_rand($bowlingProfiles)] ?? null,
                    'location_id' => $locations[array_rand($locations)] ?? null,
                    'is_wicket_keeper' => $i <= 2, // First 2 players per team are wicket keepers
                    'status' => 'approved',
                    'actual_team_id' => $team->id,
                    'player_mode' => 'sold',
                    'welcome_email_sent_at' => now(),
                    'total_matches' => rand(5, 50),
                    'total_runs' => rand(100, 2000),
                    'total_wickets' => rand(0, 50),
                ]);

                // Attach user to actual team
                $team->users()->attach($user->id, ['role' => $i <= 2 ? 'captain' : 'player']);

                $playerIndex++;
            }
        }

        $this->command->info("Created {$playerIndex} test players (12 per team)");
    }
}

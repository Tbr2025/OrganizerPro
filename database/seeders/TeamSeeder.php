<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TeamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $teams = [
            // PRE Season (DIV C)
            ['name' => 'Others', 'short_name' => 'Others', 'tournament_id' => null, 'logo' => 'logos/Others.png', 'admin_id' => 1],
            ['name' => 'Canadian Cricket Club', 'short_name' => 'CANADIAN', 'tournament_id' => null, 'logo' => 'logos/canadian.png', 'admin_id' => 1],
            ['name' => 'CARMOOZ - AKOYA CC', 'short_name' => 'AKOYA', 'tournament_id' => null, 'logo' => 'logos/akoya.png', 'admin_id' => 1],
            ['name' => 'Crazy 11s', 'short_name' => 'CRAZY11', 'tournament_id' => null, 'logo' => 'logos/crazy11.png', 'admin_id' => 1],
            ['name' => 'Jalllus Food Stuff Trading - LEGENDS CC', 'short_name' => 'LEGENDS', 'tournament_id' => null, 'logo' => 'logos/legends.png', 'admin_id' => 1],
            ['name' => 'OPAR Guruvayoor Cricket Club', 'short_name' => 'OPAR', 'tournament_id' => null, 'logo' => 'logos/opar.png', 'admin_id' => 1],
            ['name' => 'PHI Advertising', 'short_name' => 'PHI', 'tournament_id' => null, 'logo' => 'logos/phi.png', 'admin_id' => 1],
            ['name' => 'Team Four six', 'short_name' => 'FOUR6', 'tournament_id' => null, 'logo' => 'logos/four6.png', 'admin_id' => 1],
            ['name' => 'TEAM RAVANS', 'short_name' => 'RAVANS', 'tournament_id' => null, 'logo' => 'logos/ravans.png', 'admin_id' => 1],

            // PRE Season (OPEN)
            ['name' => 'AUTODEAL MCC KMCC (URT)', 'short_name' => 'KMCC', 'tournament_id' => null, 'logo' => 'logos/kmcc.png', 'admin_id' => 1],
            ['name' => 'CARA DIAMONDS INDIAN LEAGUE', 'short_name' => 'CARA', 'tournament_id' => null, 'logo' => 'logos/cara.png', 'admin_id' => 1],
            ['name' => 'Cricket Leisung', 'short_name' => 'LEISUNG', 'tournament_id' => null, 'logo' => 'logos/leisung.png', 'admin_id' => 1],
            ['name' => 'Mumbai Marathas', 'short_name' => 'MARATHAS', 'tournament_id' => null, 'logo' => 'logos/marathas.png', 'admin_id' => 1],
            ['name' => 'MUNNETT INTERIORS CC', 'short_name' => 'MUNNETT', 'tournament_id' => null, 'logo' => 'logos/munnett.png', 'admin_id' => 1],
            ['name' => 'UAE-GLOBELINK WESTSTAR', 'short_name' => 'GLOBELINK', 'tournament_id' => null, 'logo' => 'logos/globelink.png', 'admin_id' => 1],
        ];

        foreach ($teams as &$team) {
            $team['created_at'] = $now;
            $team['updated_at'] = $now;
        }

        DB::table('teams')->insert($teams);
    }
}

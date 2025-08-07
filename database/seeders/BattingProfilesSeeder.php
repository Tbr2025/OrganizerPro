<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BattingProfilesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('batting_profiles')->insert(
            [
                ['style' => 'Right-hand Bat'],
                ['style' => 'Left-hand Bat'],
                ['style' => 'Ambidextrous'],
                ['style' => 'Opening Batsman'],
                ['style' => 'Top Order Batsman'],
                ['style' => 'Middle Order Batsman'],
                ['style' => 'Lower Middle Order Batsman'],
                ['style' => 'Lower Order Batsman'],
                ['style' => 'Tailender'],
                ['style' => 'Aggressive Batsman'],
                ['style' => 'Defensive Batsman'],
                ['style' => 'Finisher'],
                ['style' => 'Anchor'],
                ['style' => 'Power Hitter'],
                ['style' => 'Technically Sound Batsman'],
                ['style' => 'Wicket-Keeper Batsman'],
                ['style' => 'Part-time Batsman'],
            ]
        );
    }
}

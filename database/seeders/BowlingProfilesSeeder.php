<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BowlingProfilesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
           DB::table('bowling_profiles')->insert([
            ['style' => 'Right-arm Fast'],
            ['style' => 'Right-arm Medium'],
            ['style' => 'Right-arm Offbreak'],
            ['style' => 'Left-arm Fast'],
            ['style' => 'Left-arm Orthodox'],
            ['style' => 'Left-arm Chinaman'],
        ]);//
    }
}

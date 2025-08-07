<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlayerTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('player_types')->insert([
            // Core Player Roles
            ['type' => 'Batsman'],
            ['type' => 'Bowler'],
            ['type' => 'All-Rounder'],
            ['type' => 'Captain'],
            ['type' => 'Vice-Captain'],
            ['type' => 'Substitute'],

            // Coaching & Team Management
            ['type' => 'Coach'],
            ['type' => 'Assistant Coach'],
            ['type' => 'Manager'],
            ['type' => 'Physiotherapist'],
            ['type' => 'Trainer'],
            ['type' => 'Analyst'],
            ['type' => 'Scout'],

            // Technical & Match Officials
            ['type' => 'Umpire'],
            ['type' => 'Match Referee'],
            ['type' => 'Scorer'],
            ['type' => 'Statistician'],

            // Media & Operations
            ['type' => 'Commentator'],
            ['type' => 'Photographer'],
            ['type' => 'Videographer'],
            ['type' => 'Media Manager'],
            ['type' => 'Social Media Manager'],
            ['type' => 'Event Coordinator'],

            // Support Staff
            ['type' => 'Ground Staff'],
            ['type' => 'Support Staff'],
            ['type' => 'Security Personnel'],
            ['type' => 'Volunteer'],

            // Ownership & Sponsorship
            ['type' => 'Team Owner'],
            ['type' => 'Sponsor Representative'],
        ]);
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlayerLocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            'Abu Dhabi',
            'Dubai',
            'Sharjah',
            'Ajman',
            'Umm Al Quwain',
            'Ras Al Khaimah',
            'Fujairah',
        ];

        foreach ($locations as $location) {
            DB::table('player_locations')->updateOrInsert(
                ['name' => $location],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}

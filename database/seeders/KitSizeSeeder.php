<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KitSizeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('kit_sizes')->insert([
            ['size' => 'S'],
            ['size' => 'M'],
            ['size' => 'L'],
            ['size' => 'XL'],
            ['size' => '2XL'],
            ['size' => '3XL'],
            ['size' => '4XL'],
        ]);
    }
}

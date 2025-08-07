<?php

namespace Database\Seeders;

use App\Models\ImageTemplateCategories;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ImageTemplateCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */

    public function run(): void
    {
        $categories = [
            'Welcome Player',
            'Man of the Match',
            'Player of the Series',
            'Best Bowler',
            'Best Batsman',
            'Star Performer',
        ];

        foreach ($categories as $category) {
            ImageTemplateCategories::firstOrCreate(['name' => $category]);
        }
    }
}

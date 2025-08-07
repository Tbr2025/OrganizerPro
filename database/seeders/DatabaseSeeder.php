<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ImageTemplate;
use App\Models\ImageTemplateCategories;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            OrganizationSeeder::class,
            UserSeeder::class,
            RolePermissionSeeder::class,
            SettingsSeeder::class,
            // ContentSeeder::class,
            KitSizeSeeder::class,
            BattingProfilesSeeder::class,
            BowlingProfilesSeeder::class,
            PlayerTypesSeeder::class,
            TeamSeeder::class,
            ImageTemplateCategorySeeder::class,

        ]);
    }
}

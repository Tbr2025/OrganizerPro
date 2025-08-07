<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
    
        User::insert([
            [
                'name' => 'Super Admin',
                'email' => 'superadmin@sportzley.com',
                'username' => 'superadmin',
                'password' => Hash::make('Super@Sportzley#2025123'),
                'organization_id' => NULL,

            ]
          
        ]);

        // Run factory to create additional users with unique details.
        // $organizations = Organization::pluck('id')->toArray();

        // User::factory()
        //     ->count(20)
        //     ->create([
        //         'organization_id' => fn() => fake()->randomElement($organizations),
        //     ]);
        // $this->command->info('Users table seeded 20 users!');
    }
}

<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrganizationSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $timestamp = now();

        foreach (Organization::all() as $org) {
            $options = [
                ['option_name' => 'site_logo', 'option_value' => '/images/logo/org-logo.png'],
                ['option_name' => 'email_signature', 'option_value' => 'Thanks, ' . $org->name],
            ];

            foreach ($options as &$opt) {
                $opt['organization_id'] = $org->id;
                $opt['created_at'] = $timestamp;
                $opt['updated_at'] = $timestamp;
                $opt['autoload'] = true;
            }

            DB::table('organization_settings')->insert($options);
        }
    }
}

<?php

namespace App\Helpers;

use App\Models\TournamentSetting;

class PlayerFormConfig
{
    public static function defaultFormFields(): array
    {
        return [
            'name'                   => ['visible' => true, 'required' => true],
            'email'                  => ['visible' => true, 'required' => true],
            'country'                => ['visible' => true, 'required' => false],
            'mobile_number'          => ['visible' => true, 'required' => true],
            'cricheroes_number'      => ['visible' => true, 'required' => false],
            'cricheroes_profile_url' => ['visible' => true, 'required' => false],
            'location'               => ['visible' => true, 'required' => false],
            'registration_team'      => ['visible' => true, 'required' => false],
            'playing_team'           => ['visible' => true, 'required' => false],
            'jersey_name'            => ['visible' => true, 'required' => false],
            'jersey_number'          => ['visible' => true, 'required' => false],
            'kit_size'               => ['visible' => true, 'required' => false],
            'batting_profile'        => ['visible' => true, 'required' => false],
            'bowling_profile'        => ['visible' => true, 'required' => false],
            'player_type'            => ['visible' => true, 'required' => false],
            'is_wicket_keeper'       => ['visible' => true, 'required' => false],
            'total_matches'          => ['visible' => true, 'required' => false],
            'total_runs'             => ['visible' => true, 'required' => false],
            'total_wickets'          => ['visible' => true, 'required' => false],
            'image'                  => ['visible' => true, 'required' => false],
            'transportation'         => ['visible' => true, 'required' => false],
            'travel_plan'            => ['visible' => true, 'required' => false],
        ];
    }

    public static function getFieldConfig(?TournamentSetting $settings): array
    {
        $defaults = self::defaultFormFields();

        if (!$settings || !$settings->registration_form_fields) {
            return $defaults;
        }

        $saved = $settings->registration_form_fields;

        // Merge saved config over defaults
        foreach ($defaults as $key => $default) {
            if (isset($saved[$key])) {
                $defaults[$key] = [
                    'visible' => (bool) ($saved[$key]['visible'] ?? $default['visible']),
                    'required' => (bool) ($saved[$key]['required'] ?? $default['required']),
                ];
            }
        }

        // Name and email are always forced visible+required
        $defaults['name'] = ['visible' => true, 'required' => true];
        $defaults['email'] = ['visible' => true, 'required' => true];

        return $defaults;
    }

    public static function fieldLabels(): array
    {
        return [
            'name'                   => 'Player Name',
            'email'                  => 'Email Address',
            'country'                => 'Country',
            'mobile_number'          => 'Mobile Number',
            'cricheroes_number'      => 'CricHeroes Number',
            'cricheroes_profile_url' => 'CricHeroes Profile URL',
            'location'               => 'Location',
            'registration_team'      => 'Registration Team',
            'playing_team'           => 'Playing Team',
            'jersey_name'            => 'Jersey Name',
            'jersey_number'          => 'Jersey Number',
            'kit_size'               => 'Kit Size',
            'batting_profile'        => 'Batting Profile',
            'bowling_profile'        => 'Bowling Profile',
            'player_type'            => 'Player Type',
            'is_wicket_keeper'       => 'Wicket Keeper',
            'total_matches'          => 'Total Matches',
            'total_runs'             => 'Total Runs',
            'total_wickets'          => 'Total Wickets',
            'image'                  => 'Player Photo',
            'transportation'         => 'Transportation',
            'travel_plan'            => 'Travel Plan',
        ];
    }

    public static function fieldGroups(): array
    {
        return [
            'Basic Info' => ['name', 'email', 'country', 'mobile_number', 'cricheroes_number', 'cricheroes_profile_url', 'location'],
            'Team' => ['registration_team', 'playing_team'],
            'Jersey & Profile' => ['jersey_name', 'jersey_number', 'kit_size', 'batting_profile', 'bowling_profile', 'player_type', 'is_wicket_keeper'],
            'Stats' => ['total_matches', 'total_runs', 'total_wickets'],
            'Other' => ['image', 'transportation', 'travel_plan'],
        ];
    }

    public static function buildValidationRules(array $fieldConfig, string $context = 'public'): array
    {
        $rules = [];

        // Name - always required
        $rules['name'] = 'required|string|max:100';

        // Email - always required
        $rules['email'] = 'required|email|max:255';

        // Country
        if ($fieldConfig['country']['visible'] ?? true) {
            $rules['country'] = ($fieldConfig['country']['required'] ?? false) ? 'required|string|max:2' : 'nullable|string|max:2';
        }

        // Mobile Number
        if ($fieldConfig['mobile_number']['visible'] ?? true) {
            if ($context === 'public') {
                $rules['mobile_number_full'] = ($fieldConfig['mobile_number']['required'] ?? true) ? 'required|string|max:20' : 'nullable|string|max:20';
            } else {
                $rules['mobile_country_code'] = ($fieldConfig['mobile_number']['required'] ?? true) ? 'required|string|max:10' : 'nullable|string|max:10';
                $rules['mobile_national_number'] = ($fieldConfig['mobile_number']['required'] ?? true) ? 'required|string|max:20' : 'nullable|string|max:20';
            }
        }

        // CricHeroes Number
        if ($fieldConfig['cricheroes_number']['visible'] ?? true) {
            if ($context === 'public') {
                $rules['cricheroes_number_full'] = 'nullable|string|max:20';
            } else {
                $rules['cricheroes_country_code'] = 'nullable|string|max:10';
                $rules['cricheroes_national_number'] = 'nullable|string|max:20';
            }
        }

        // CricHeroes Profile URL
        if ($fieldConfig['cricheroes_profile_url']['visible'] ?? true) {
            $rules['cricheroes_profile_url'] = ($fieldConfig['cricheroes_profile_url']['required'] ?? false) ? 'required|url|max:500' : 'nullable|url|max:500';
        }

        // Location
        if ($fieldConfig['location']['visible'] ?? true) {
            $rules['location_id'] = ($fieldConfig['location']['required'] ?? false) ? 'required|exists:player_locations,id' : 'nullable|exists:player_locations,id';
        }

        // Registration Team
        if ($fieldConfig['registration_team']['visible'] ?? true) {
            $rules['team_id'] = ($fieldConfig['registration_team']['required'] ?? false) ? 'required|exists:teams,id' : 'nullable|exists:teams,id';
            $rules['team_name_ref'] = 'nullable|string|max:100';
        }

        // Jersey Name
        if ($fieldConfig['jersey_name']['visible'] ?? true) {
            $rules['jersey_name'] = ($fieldConfig['jersey_name']['required'] ?? false) ? 'required|string|max:50' : 'nullable|string|max:50';
        }

        // Jersey Number
        if ($fieldConfig['jersey_number']['visible'] ?? true) {
            $rules['jersey_number'] = ($fieldConfig['jersey_number']['required'] ?? false) ? 'required|integer|min:0|max:999' : 'nullable|integer|min:0|max:999';
        }

        // Kit Size
        if ($fieldConfig['kit_size']['visible'] ?? true) {
            $rules['kit_size_id'] = ($fieldConfig['kit_size']['required'] ?? false) ? 'required|exists:kit_sizes,id' : 'nullable|exists:kit_sizes,id';
        }

        // Batting Profile
        if ($fieldConfig['batting_profile']['visible'] ?? true) {
            $rules['batting_profile_id'] = ($fieldConfig['batting_profile']['required'] ?? false) ? 'required|exists:batting_profiles,id' : 'nullable|exists:batting_profiles,id';
        }

        // Bowling Profile
        if ($fieldConfig['bowling_profile']['visible'] ?? true) {
            $rules['bowling_profile_id'] = ($fieldConfig['bowling_profile']['required'] ?? false) ? 'required|exists:bowling_profiles,id' : 'nullable|exists:bowling_profiles,id';
        }

        // Player Type
        if ($fieldConfig['player_type']['visible'] ?? true) {
            $rules['player_type_id'] = ($fieldConfig['player_type']['required'] ?? false) ? 'required|exists:player_types,id' : 'nullable|exists:player_types,id';
        }

        // Wicket Keeper
        if ($fieldConfig['is_wicket_keeper']['visible'] ?? true) {
            $rules['is_wicket_keeper'] = 'boolean';
        }

        // Stats
        if ($fieldConfig['total_matches']['visible'] ?? true) {
            $rules['total_matches'] = 'nullable|integer|min:0';
        }
        if ($fieldConfig['total_runs']['visible'] ?? true) {
            $rules['total_runs'] = 'nullable|integer|min:0';
        }
        if ($fieldConfig['total_wickets']['visible'] ?? true) {
            $rules['total_wickets'] = 'nullable|integer|min:0';
        }

        // Image
        if ($fieldConfig['image']['visible'] ?? true) {
            $rules['image'] = ($fieldConfig['image']['required'] ?? false) ? 'required|image|mimes:png,jpg,jpeg|max:6144' : 'nullable|image|mimes:png,jpg,jpeg|max:6144';
        }

        // Transportation
        if ($fieldConfig['transportation']['visible'] ?? true) {
            $rules['transportation_required'] = 'boolean';
        }

        // Travel Plan
        if ($fieldConfig['travel_plan']['visible'] ?? true) {
            $rules['no_travel_plan'] = 'boolean';
            $rules['travel_date_from'] = 'nullable|date';
            $rules['travel_date_to'] = 'nullable|date|after_or_equal:travel_date_from';
        }

        return $rules;
    }
}

<?php

namespace App\Helpers;

use App\Models\TournamentSetting;

class TeamFormConfig
{
    public static function defaultFormFields(): array
    {
        return [
            'team_name'          => ['visible' => true, 'required' => true],
            'team_short_name'    => ['visible' => true, 'required' => false],
            'team_logo'          => ['visible' => true, 'required' => false],
            'team_description'   => ['visible' => true, 'required' => false],
            'captain_name'       => ['visible' => true, 'required' => true],
            'captain_email'      => ['visible' => true, 'required' => true],
            'captain_phone'      => ['visible' => true, 'required' => true],
            'vice_captain_name'  => ['visible' => true, 'required' => false],
            'vice_captain_email' => ['visible' => true, 'required' => false],
            'vice_captain_phone' => ['visible' => true, 'required' => false],
            'terms_and_conditions' => ['visible' => false, 'required' => false],
        ];
    }

    public static function getFieldConfig(?TournamentSetting $settings): array
    {
        $defaults = self::defaultFormFields();

        if (!$settings || !$settings->team_registration_form_fields) {
            return $defaults;
        }

        $saved = $settings->team_registration_form_fields;

        foreach ($defaults as $key => $default) {
            if (isset($saved[$key])) {
                $defaults[$key] = [
                    'visible' => (bool) ($saved[$key]['visible'] ?? $default['visible']),
                    'required' => (bool) ($saved[$key]['required'] ?? $default['required']),
                ];
            }
        }

        // Team name, manager name and email are always forced visible+required
        $defaults['team_name'] = ['visible' => true, 'required' => true];
        $defaults['captain_name'] = ['visible' => true, 'required' => true];
        $defaults['captain_email'] = ['visible' => true, 'required' => true];

        return $defaults;
    }

    public static function fieldLabels(): array
    {
        return [
            'team_name'          => 'Team Name',
            'team_short_name'    => 'Short Name / Abbreviation',
            'team_logo'          => 'Team Logo',
            'team_description'   => 'Team Description',
            'captain_name'       => 'Manager Name',
            'captain_email'      => 'Manager Email',
            'captain_phone'      => 'Manager Phone',
            'vice_captain_name'  => 'Owner Name',
            'vice_captain_email' => 'Owner Email',
            'vice_captain_phone' => 'Owner Phone',
            'terms_and_conditions' => 'Terms & Conditions',
        ];
    }

    public static function fieldGroups(): array
    {
        return [
            'Team Info' => ['team_name', 'team_short_name', 'team_logo', 'team_description'],
            'Manager Details' => ['captain_name', 'captain_email', 'captain_phone'],
            'Owner Details' => ['vice_captain_name', 'vice_captain_email', 'vice_captain_phone'],
            'Other' => ['terms_and_conditions'],
        ];
    }

    public static function buildValidationRules(array $fieldConfig): array
    {
        $rules = [];

        // Team name - always required
        $rules['team_name'] = 'required|string|max:100';

        if ($fieldConfig['team_short_name']['visible'] ?? true) {
            $rules['team_short_name'] = ($fieldConfig['team_short_name']['required'] ?? false) ? 'required|string|max:10' : 'nullable|string|max:10';
        }

        if ($fieldConfig['team_logo']['visible'] ?? true) {
            $rules['team_logo'] = ($fieldConfig['team_logo']['required'] ?? false) ? 'required|image|mimes:png,jpg,jpeg|max:2048' : 'nullable|image|mimes:png,jpg,jpeg|max:2048';
        }

        if ($fieldConfig['team_description']['visible'] ?? true) {
            $rules['team_description'] = ($fieldConfig['team_description']['required'] ?? false) ? 'required|string|max:500' : 'nullable|string|max:500';
        }

        // Manager - name and email always required
        $rules['captain_name'] = 'required|string|max:100';
        $rules['captain_email'] = 'required|email|max:255';

        if ($fieldConfig['captain_phone']['visible'] ?? true) {
            $rules['captain_phone'] = ($fieldConfig['captain_phone']['required'] ?? true) ? 'required|string|max:20' : 'nullable|string|max:20';
        }

        if ($fieldConfig['vice_captain_name']['visible'] ?? true) {
            $rules['vice_captain_name'] = ($fieldConfig['vice_captain_name']['required'] ?? false) ? 'required|string|max:100' : 'nullable|string|max:100';
        }

        if ($fieldConfig['vice_captain_email']['visible'] ?? true) {
            $rules['vice_captain_email'] = ($fieldConfig['vice_captain_email']['required'] ?? false) ? 'required|email|max:255' : 'nullable|email|max:255';
        }

        if ($fieldConfig['vice_captain_phone']['visible'] ?? true) {
            $rules['vice_captain_phone'] = ($fieldConfig['vice_captain_phone']['required'] ?? false) ? 'required|string|max:20' : 'nullable|string|max:20';
        }

        if ($fieldConfig['terms_and_conditions']['visible'] ?? false) {
            $rules['terms_and_conditions'] = ($fieldConfig['terms_and_conditions']['required'] ?? false) ? 'accepted' : 'nullable|boolean';
        }

        return $rules;
    }
}

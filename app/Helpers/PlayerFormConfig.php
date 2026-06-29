<?php

namespace App\Helpers;

use App\Models\TournamentSetting;

class PlayerFormConfig
{
    public static function defaultFormFields(): array
    {
        return [
            'name'                   => ['visible' => true, 'required' => true],
            'first_name'             => ['visible' => true, 'required' => true],
            'last_name'              => ['visible' => true, 'required' => true],
            'email'                  => ['visible' => true, 'required' => true],
            'date_of_birth'          => ['visible' => true, 'required' => true],
            'country'                => ['visible' => true, 'required' => false],
            'state'                  => ['visible' => true, 'required' => false],
            'visa_status'            => ['visible' => true, 'required' => false],
            'visa_expiry'            => ['visible' => true, 'required' => false],
            'employer_name'          => ['visible' => true, 'required' => false],
            'employer_address'       => ['visible' => true, 'required' => false],
            'employer_position'      => ['visible' => true, 'required' => false],
            'available_saturday'     => ['visible' => true, 'required' => false],
            'available_sunday'       => ['visible' => true, 'required' => false],
            'played_ys_ipl_s1'       => ['visible' => true, 'required' => false],
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
            'terms_and_conditions'   => ['visible' => false, 'required' => false],
        ];
    }

    public static function getFieldConfig(?TournamentSetting $settings): array
    {
        $defaults = self::defaultFormFields();
        $labels = self::fieldLabels();
        $saved = ($settings && $settings->registration_form_fields) ? $settings->registration_form_fields : [];

        $defaultOrder = self::defaultFieldOrder();

        $config = [];
        foreach ($defaults as $key => $default) {
            $savedField = is_array($saved[$key] ?? null) ? $saved[$key] : [];
            $label = $savedField['label'] ?? null;

            $config[$key] = [
                'visible' => (bool) ($savedField['visible'] ?? $default['visible']),
                'required' => (bool) ($savedField['required'] ?? $default['required']),
                // Custom label per tournament, falling back to the default label.
                'label' => ($label !== null && $label !== '') ? $label : ($labels[$key] ?? $key),
                // Saved order within its section, else default index within the section.
                'order' => isset($savedField['order']) ? (int) $savedField['order'] : ($defaultOrder[$key] ?? 0),
            ];
        }

        // Name (composed), first/last and email are always forced visible+required
        foreach (['name', 'first_name', 'last_name', 'email'] as $forced) {
            $config[$forced]['visible'] = true;
            $config[$forced]['required'] = true;
        }

        return $config;
    }

    /** Default order index of each field within its section (from fieldGroups). */
    public static function defaultFieldOrder(): array
    {
        $order = [];
        foreach (self::fieldGroups() as $fields) {
            foreach (array_values($fields) as $i => $key) {
                $order[$key] = $i;
            }
        }

        return $order;
    }

    /**
     * Ordered form layout: sections (in saved order) each with their field keys
     * (in saved order). Field→section membership comes from fieldGroups().
     *
     * @return array<int, array{key:string,title:string,fields:array<int,string>}>
     */
    public static function getFormLayout(?TournamentSetting $settings, bool $visibleOnly = false): array
    {
        $groups = self::fieldGroups();
        $titles = self::getSectionLabels($settings);
        $fieldConfig = self::getFieldConfig($settings);

        $savedSectionOrder = ($settings && $settings->registration_form_fields)
            ? ($settings->registration_form_fields['_section_order'] ?? [])
            : [];

        // Order sections: saved order first (valid keys only), then any remaining.
        $sectionKeys = array_values(array_filter($savedSectionOrder, fn ($k) => isset($groups[$k])));
        foreach (array_keys($groups) as $k) {
            if (! in_array($k, $sectionKeys, true)) {
                $sectionKeys[] = $k;
            }
        }

        $layout = [];
        foreach ($sectionKeys as $sectionKey) {
            $fields = $groups[$sectionKey];

            if ($visibleOnly) {
                $fields = array_values(array_filter($fields, fn ($k) => $fieldConfig[$k]['visible'] ?? true));
            }

            // Sort by configured order, stable on default index as tiebreak.
            usort($fields, function ($a, $b) use ($fieldConfig) {
                return ($fieldConfig[$a]['order'] ?? 0) <=> ($fieldConfig[$b]['order'] ?? 0);
            });

            $layout[] = [
                'key' => $sectionKey,
                'title' => $titles[$sectionKey] ?? $sectionKey,
                'fields' => $fields,
            ];
        }

        return $layout;
    }

    /**
     * Section titles for the registration form, with per-tournament overrides
     * (saved under registration_form_fields['_sections']).
     */
    public static function getSectionLabels(?TournamentSetting $settings): array
    {
        $saved = ($settings && $settings->registration_form_fields)
            ? ($settings->registration_form_fields['_sections'] ?? [])
            : [];

        $out = [];
        foreach (array_keys(self::fieldGroups()) as $group) {
            $out[$group] = (isset($saved[$group]) && $saved[$group] !== '') ? $saved[$group] : $group;
        }

        return $out;
    }

    public static function fieldLabels(): array
    {
        return [
            'name'                   => 'Player Name',
            'first_name'             => 'First Name',
            'last_name'              => 'Last Name',
            'email'                  => 'Email Address',
            'date_of_birth'          => 'Date of Birth',
            'country'                => 'Nationality',
            'state'                  => 'State / Province',
            'visa_status'            => 'Visa Status',
            'visa_expiry'            => 'Visa Validity (Expiry Date)',
            'employer_name'          => 'Employer Name',
            'employer_address'       => 'Employer Address',
            'employer_position'      => 'Position',
            'available_saturday'     => 'I am available to play on Saturdays',
            'available_sunday'       => 'I am available to play on Sundays',
            'played_ys_ipl_s1'       => 'Have you played YS IPL Season 1?',
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
            'is_wicket_keeper'       => 'I am a wicket keeper',
            'total_matches'          => 'Total Matches',
            'total_runs'             => 'Total Runs',
            'total_wickets'          => 'Total Wickets',
            'image'                  => 'Player Photo',
            'transportation'         => 'I need transportation to the venue',
            'travel_plan'            => 'I have no travel plans (available throughout)',
            'terms_and_conditions'   => 'I agree to the Terms & Conditions',
        ];
    }

    public static function fieldGroups(): array
    {
        // Groups double as the registration form's visual sections (titles are editable).
        return [
            'Basic Information' => ['first_name', 'last_name', 'email', 'date_of_birth', 'country', 'state', 'mobile_number', 'cricheroes_number', 'cricheroes_profile_url', 'location', 'registration_team', 'playing_team'],
            'Visa & Employment' => ['visa_status', 'visa_expiry', 'employer_name', 'employer_address', 'employer_position'],
            'Availability' => ['available_saturday', 'available_sunday', 'played_ys_ipl_s1'],
            'Jersey Information' => ['jersey_name', 'jersey_number', 'kit_size'],
            'Player Profile' => ['player_type', 'batting_profile', 'bowling_profile', 'is_wicket_keeper'],
            'Leather Ball Experience' => ['total_matches', 'total_runs', 'total_wickets'],
            'Travel & Transportation' => ['transportation', 'travel_plan'],
            'Player Photo' => ['image'],
            'Terms & Conditions' => ['terms_and_conditions'],
        ];
    }

    public static function buildValidationRules(array $fieldConfig, string $context = 'public'): array
    {
        $rules = [];

        // Name - always required (composed server-side from first + last)
        $rules['name'] = 'required|string|max:100';

        // First / Last name - always required (they replace the single name field)
        $rules['first_name'] = 'required|string|max:100';
        $rules['last_name'] = 'required|string|max:100';

        // Email - always required
        $rules['email'] = 'required|email|max:255';

        // Date of Birth
        if ($fieldConfig['date_of_birth']['visible'] ?? true) {
            $rules['date_of_birth'] = ($fieldConfig['date_of_birth']['required'] ?? false) ? 'required|date|before:today' : 'nullable|date|before:today';
        }

        // Country
        if ($fieldConfig['country']['visible'] ?? true) {
            $rules['country'] = ($fieldConfig['country']['required'] ?? false) ? 'required|string|max:2' : 'nullable|string|max:2';
        }

        // Indian State
        if ($fieldConfig['state']['visible'] ?? true) {
            $rules['state'] = ($fieldConfig['state']['required'] ?? false) ? 'required|string|max:100' : 'nullable|string|max:100';
        }

        // Visa Status
        if ($fieldConfig['visa_status']['visible'] ?? true) {
            $rules['visa_status'] = ($fieldConfig['visa_status']['required'] ?? false) ? 'required|in:work_visa,visit_visa' : 'nullable|in:work_visa,visit_visa';
        }

        // Visa validity — required only when the visa is a visit visa.
        if ($fieldConfig['visa_expiry']['visible'] ?? true) {
            $rules['visa_expiry'] = 'nullable|date|required_if:visa_status,visit_visa';
        }

        // Employer details — required only when the visa is a work visa.
        if ($fieldConfig['employer_name']['visible'] ?? true) {
            $rules['employer_name'] = 'nullable|string|max:255|required_if:visa_status,work_visa';
        }
        if ($fieldConfig['employer_address']['visible'] ?? true) {
            $rules['employer_address'] = 'nullable|string|max:500|required_if:visa_status,work_visa';
        }
        if ($fieldConfig['employer_position']['visible'] ?? true) {
            $rules['employer_position'] = 'nullable|string|max:255|required_if:visa_status,work_visa';
        }

        // Availability (separate per-day flags)
        if ($fieldConfig['available_saturday']['visible'] ?? true) {
            $rules['available_saturday'] = 'nullable|boolean';
        }
        if ($fieldConfig['available_sunday']['visible'] ?? true) {
            $rules['available_sunday'] = 'nullable|boolean';
        }
        if ($fieldConfig['played_ys_ipl_s1']['visible'] ?? true) {
            $rules['played_ys_ipl_s1'] = 'nullable|boolean';
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

        // Terms & Conditions
        if ($fieldConfig['terms_and_conditions']['visible'] ?? false) {
            $rules['terms_and_conditions'] = ($fieldConfig['terms_and_conditions']['required'] ?? false) ? 'accepted' : 'nullable|boolean';
        }

        return $rules;
    }
}

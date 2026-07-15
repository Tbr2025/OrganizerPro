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
            'country'                => ['visible' => true, 'required' => true],
            'state'                  => ['visible' => true, 'required' => true],
            'visa_status'            => ['visible' => true, 'required' => true],
            'visa_expiry'            => ['visible' => true, 'required' => true],
            'employer_name'          => ['visible' => true, 'required' => true],
            'employer_address'       => ['visible' => true, 'required' => true],
            'employer_position'      => ['visible' => true, 'required' => true],
            'available_saturday'     => ['visible' => true, 'required' => true],
            'available_sunday'       => ['visible' => true, 'required' => true],
            'played_ys_ipl_s1'       => ['visible' => true, 'required' => true],
            'mobile_number'          => ['visible' => true, 'required' => true],
            'cricheroes_number'      => ['visible' => true, 'required' => true],
            'cricheroes_profile_url' => ['visible' => true, 'required' => true],
            'location'               => ['visible' => true, 'required' => true],
            'registration_team'      => ['visible' => true, 'required' => true],
            'playing_team'           => ['visible' => true, 'required' => true],
            'jersey_name'            => ['visible' => true, 'required' => true],
            'jersey_number'          => ['visible' => true, 'required' => true],
            'tshirt_size'            => ['visible' => true, 'required' => true],
            'pant_size'              => ['visible' => true, 'required' => true],
            'batting_profile'        => ['visible' => true, 'required' => true],
            'batting_mode'           => ['visible' => true, 'required' => true],
            'preferred_batting_position' => ['visible' => true, 'required' => true],
            'bowling_profile'        => ['visible' => true, 'required' => true],
            'player_type'            => ['visible' => true, 'required' => true],
            'is_wicket_keeper'       => ['visible' => true, 'required' => true],
            'total_matches'          => ['visible' => true, 'required' => true],
            'total_runs'             => ['visible' => true, 'required' => true],
            'total_wickets'          => ['visible' => true, 'required' => true],
            'image'                  => ['visible' => true, 'required' => true],
            'transportation'         => ['visible' => true, 'required' => true],
            'travel_plan'            => ['visible' => true, 'required' => true],
            'terms_and_conditions'   => ['visible' => false, 'required' => false],
        ];
    }

    /**
     * Must-have fields: always visible + required, cannot be hidden/disabled in
     * the builder, and cannot be edited by the player after registration.
     *
     * @return array<int, string>
     */
    public static function lockedFields(): array
    {
        return ['name', 'first_name', 'last_name', 'email', 'mobile_number'];
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

        // Section-level visibility: hiding a whole group hides all its fields on
        // the public form AND excludes them from validation. Applied before the
        // must-have override so identity fields can never be hidden.
        $sectionVisible = is_array($saved['_section_visible'] ?? null) ? $saved['_section_visible'] : [];
        foreach (self::fieldGroups() as $sectionKey => $sectionFields) {
            if (array_key_exists($sectionKey, $sectionVisible) && ! $sectionVisible[$sectionKey]) {
                foreach ($sectionFields as $f) {
                    if (isset($config[$f])) {
                        $config[$f]['visible'] = false;
                    }
                }
            }
        }

        // Must-have identity/contact fields — always visible + required and locked
        // from being toggled or edited (see lockedFields()).
        foreach (self::lockedFields() as $forced) {
            if (isset($config[$forced])) {
                $config[$forced]['visible'] = true;
                $config[$forced]['required'] = true;
            }
        }

        // Terms & Conditions: auto-show (and require acceptance) whenever the
        // tournament has T&C content — no separate form-builder toggle needed.
        if ($settings && trim((string) $settings->terms_and_conditions_content) !== '') {
            $config['terms_and_conditions']['visible'] = true;
            $config['terms_and_conditions']['required'] = true;
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
                // Skip a section entirely when none of its fields are visible
                // (e.g. the whole group was hidden in settings).
                if (empty($fields)) {
                    continue;
                }
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
            'tshirt_size'            => 'T-Shirt Size',
            'pant_size'              => 'Pant Size',
            'batting_profile'        => 'Batting Dominant Hand',
            'batting_mode'           => 'Batting Mode',
            'preferred_batting_position' => 'Preferred Batting Position',
            'bowling_profile'        => 'Bowling Profile',
            'player_type'            => 'Player Type',
            'is_wicket_keeper'       => 'I am a wicket keeper',
            'total_matches'          => 'Total Matches',
            'total_runs'             => 'Total Runs',
            'total_wickets'          => 'Total Wickets',
            'image'                  => 'Player Photo',
            'transportation'         => 'Transportation to the Venue',
            'travel_plan'            => 'Do you have any travel plans?',
            'terms_and_conditions'   => 'I agree to the Terms & Conditions',
        ];
    }

    /**
     * Parse an admin-managed size list from global settings (comma/newline
     * separated), falling back to sensible defaults when unset.
     *
     * @return array<int, string>
     */
    public static function sizeOptions(string $settingKey, array $default): array
    {
        $raw = (string) config('settings.' . $settingKey, '');
        if (trim($raw) === '') {
            return $default;
        }
        $parts = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $raw)), fn ($v) => $v !== ''));

        return $parts ?: $default;
    }

    /** Default T-shirt size options. */
    public static function defaultTshirtSizes(): array
    {
        return ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'];
    }

    /** Default pant/waist size options. */
    public static function defaultPantSizes(): array
    {
        return ['28', '30', '32', '34', '36', '38', '40', '42'];
    }

    public static function fieldGroups(): array
    {
        // Groups double as the registration form's visual sections (titles are editable).
        return [
            'Basic Information' => ['first_name', 'last_name', 'email', 'date_of_birth', 'country', 'state', 'mobile_number', 'cricheroes_number', 'cricheroes_profile_url', 'location', 'registration_team', 'playing_team'],
            'Visa & Employment' => ['visa_status', 'visa_expiry', 'employer_name', 'employer_address', 'employer_position'],
            'Availability' => ['available_saturday', 'available_sunday', 'played_ys_ipl_s1'],
            'Jersey Information' => ['jersey_name', 'jersey_number', 'tshirt_size', 'pant_size'],
            'Player Profile' => ['player_type', 'batting_profile', 'batting_mode', 'preferred_batting_position', 'bowling_profile', 'is_wicket_keeper'],
            'Leather Ball Experience' => ['total_matches', 'total_runs', 'total_wickets'],
            'Travel & Transportation' => ['transportation', 'travel_plan'],
            'Player Photo' => ['image'],
            'Terms & Conditions' => ['terms_and_conditions'],
        ];
    }

    public static function buildValidationRules(array $fieldConfig, string $context = 'public', ?TournamentSetting $settings = null): array
    {
        $rules = [];

        // Name - always required (composed server-side from first + last)
        $rules['name'] = 'required|string|max:100';

        // First / Last name - always required (they replace the single name field)
        $rules['first_name'] = 'required|string|max:100';
        $rules['last_name'] = 'required|string|max:100';

        // Email - always required
        $rules['email'] = 'required|email|max:255';

        // Date of Birth — block future dates and enforce the tournament's
        // configurable minimum / maximum age when set.
        if ($fieldConfig['date_of_birth']['visible'] ?? true) {
            $dobRules = [($fieldConfig['date_of_birth']['required'] ?? false) ? 'required' : 'nullable', 'date'];
            $minAge = $settings?->min_age;
            $maxAge = $settings?->max_age;
            // Minimum age → the latest DOB allowed (also blocks future dates).
            if ($minAge) {
                $dobRules[] = 'before_or_equal:' . now()->subYears((int) $minAge)->toDateString();
            } else {
                $dobRules[] = 'before:today';
            }
            // Maximum age → the earliest DOB allowed.
            if ($maxAge) {
                $dobRules[] = 'after_or_equal:' . now()->subYears((int) $maxAge)->toDateString();
            }
            $rules['date_of_birth'] = implode('|', $dobRules);
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

        // Visa validity — conditionally required only when the visa is a visit
        // visa AND the admin left the field marked as required.
        if ($fieldConfig['visa_expiry']['visible'] ?? true) {
            $rules['visa_expiry'] = ($fieldConfig['visa_expiry']['required'] ?? false)
                ? 'nullable|date|required_if:visa_status,visit_visa'
                : 'nullable|date';
        }

        // Employer details — conditionally required only when the visa is a work
        // visa AND the admin left the field marked as required.
        if ($fieldConfig['employer_name']['visible'] ?? true) {
            $rules['employer_name'] = ($fieldConfig['employer_name']['required'] ?? false)
                ? 'nullable|string|max:255|required_if:visa_status,work_visa'
                : 'nullable|string|max:255';
        }
        if ($fieldConfig['employer_address']['visible'] ?? true) {
            $rules['employer_address'] = ($fieldConfig['employer_address']['required'] ?? false)
                ? 'nullable|string|max:500|required_if:visa_status,work_visa'
                : 'nullable|string|max:500';
        }
        if ($fieldConfig['employer_position']['visible'] ?? true) {
            $rules['employer_position'] = ($fieldConfig['employer_position']['required'] ?? false)
                ? 'nullable|string|max:255|required_if:visa_status,work_visa'
                : 'nullable|string|max:255';
        }

        // Availability (separate per-day flags)
        if ($fieldConfig['available_saturday']['visible'] ?? true) {
            $rules['available_saturday'] = 'nullable|boolean';
        }
        if ($fieldConfig['available_sunday']['visible'] ?? true) {
            $rules['available_sunday'] = 'nullable|boolean';
        }
        if ($fieldConfig['played_ys_ipl_s1']['visible'] ?? true) {
            $req = ($fieldConfig['played_ys_ipl_s1']['required'] ?? false) ? 'required' : 'nullable';
            $rules['played_ys_ipl_s1'] = $req . '|boolean';
        }

        // Mobile Number (public form uses country-code dropdown + national number)
        if ($fieldConfig['mobile_number']['visible'] ?? true) {
            $mobileReq = $fieldConfig['mobile_number']['required'] ?? true;
            $rules['mobile_country_code'] = $mobileReq ? 'required|string|max:10' : 'nullable|string|max:10';
            $rules['mobile_national_number'] = $mobileReq ? 'required|string|max:20' : 'nullable|string|max:20';
        }

        // CricHeroes Number (same split pattern)
        if ($fieldConfig['cricheroes_number']['visible'] ?? true) {
            $rules['cricheroes_country_code'] = 'nullable|string|max:10';
            $rules['cricheroes_national_number'] = 'nullable|string|max:20';
        }

        // CricHeroes Profile URL
        if ($fieldConfig['cricheroes_profile_url']['visible'] ?? true) {
            $rules['cricheroes_profile_url'] = ($fieldConfig['cricheroes_profile_url']['required'] ?? false) ? 'required|url|max:500' : 'nullable|url|max:500';
        }

        // Location
        if ($fieldConfig['location']['visible'] ?? true) {
            $rules['location_id'] = ($fieldConfig['location']['required'] ?? false) ? 'required|exists:player_locations,id' : 'nullable|exists:player_locations,id';
        }

        // Registration Team. team_id stays nullable because the applicant may pick
        // "Other" (free text) or type a team when no teams are pre-defined; the
        // HTML required attribute handles the client-side prompt.
        if ($fieldConfig['registration_team']['visible'] ?? true) {
            $rules['team_id'] = 'nullable|exists:teams,id';
            $rules['team_name_ref'] = 'nullable|string|max:100';
        }

        // Playing Team (actual team the player will play for — supports "other")
        if ($fieldConfig['playing_team']['visible'] ?? true) {
            $req = ($fieldConfig['playing_team']['required'] ?? false) ? 'required' : 'nullable';
            $rules['actual_team_id'] = $req;
            $rules['playing_team_name_ref'] = 'nullable|string|max:100';
        }

        // Jersey Name
        if ($fieldConfig['jersey_name']['visible'] ?? true) {
            $rules['jersey_name'] = ($fieldConfig['jersey_name']['required'] ?? false) ? 'required|string|max:50' : 'nullable|string|max:50';
        }

        // Jersey Number
        if ($fieldConfig['jersey_number']['visible'] ?? true) {
            $rules['jersey_number'] = ($fieldConfig['jersey_number']['required'] ?? false) ? 'required|integer|min:0|max:999' : 'nullable|integer|min:0|max:999';
        }

        // T-Shirt Size (admin-managed list, stored as a string)
        if ($fieldConfig['tshirt_size']['visible'] ?? true) {
            $rules['tshirt_size'] = ($fieldConfig['tshirt_size']['required'] ?? false) ? 'required|string|max:50' : 'nullable|string|max:50';
        }
        // Pant Size (admin-managed list, stored as a string)
        if ($fieldConfig['pant_size']['visible'] ?? true) {
            $rules['pant_size'] = ($fieldConfig['pant_size']['required'] ?? false) ? 'required|string|max:50' : 'nullable|string|max:50';
        }

        // Batting Profile
        if ($fieldConfig['batting_profile']['visible'] ?? true) {
            $rules['batting_profile_id'] = ($fieldConfig['batting_profile']['required'] ?? false) ? 'required|exists:batting_profiles,id' : 'nullable|exists:batting_profiles,id';
        }

        // Batting Mode
        if ($fieldConfig['batting_mode']['visible'] ?? true) {
            $rules['batting_mode'] = 'nullable|in:Aggressive Batsman,Defensive Batsman,Finisher,Anchor,Power Hitter';
        }

        // Preferred Batting Position (multi-select, max 3)
        if ($fieldConfig['preferred_batting_position']['visible'] ?? true) {
            $rules['preferred_batting_positions'] = 'nullable|array|max:3';
            $rules['preferred_batting_positions.*'] = "in:Opener,3,4,5,6,7,8,I'm Flexible";
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
            $rules['transportation_mode'] = 'nullable|in:self,required';
        }

        // Travel Plan
        if ($fieldConfig['travel_plan']['visible'] ?? true) {
            $rules['has_travel_plan'] = 'nullable|in:no,yes';
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

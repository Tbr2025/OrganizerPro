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

    /** Must-have team fields: always visible+required and locked in the builder. */
    public static function lockedFields(): array
    {
        return ['team_name', 'captain_name', 'captain_email'];
    }

    public static function getFieldConfig(?TournamentSetting $settings): array
    {
        $defaults = self::defaultFormFields();
        $labels = self::fieldLabels();
        $saved = ($settings && $settings->team_registration_form_fields) ? $settings->team_registration_form_fields : [];
        $defaultOrder = self::defaultFieldOrder();

        $config = [];
        foreach ($defaults as $key => $default) {
            $savedField = is_array($saved[$key] ?? null) ? $saved[$key] : [];
            $label = $savedField['label'] ?? null;

            $config[$key] = [
                'visible' => (bool) ($savedField['visible'] ?? $default['visible']),
                'required' => (bool) ($savedField['required'] ?? $default['required']),
                'label' => ($label !== null && $label !== '') ? $label : ($labels[$key] ?? $key),
                'order' => isset($savedField['order']) ? (int) $savedField['order'] : ($defaultOrder[$key] ?? 0),
            ];
        }

        // Section-level visibility: hiding a whole group hides all its fields on
        // the public team form AND excludes them from validation.
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

        // Must-have team fields — always visible + required and locked.
        foreach (self::lockedFields() as $forced) {
            if (isset($config[$forced])) {
                $config[$forced]['visible'] = true;
                $config[$forced]['required'] = true;
            }
        }

        // Terms & Conditions: auto-show + require when the tournament has TEAM T&C content.
        if ($settings && trim((string) $settings->team_terms_and_conditions_content) !== '') {
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

    /** Section titles for the team form, with per-tournament overrides. */
    public static function getSectionLabels(?TournamentSetting $settings): array
    {
        $saved = ($settings && $settings->team_registration_form_fields)
            ? ($settings->team_registration_form_fields['_sections'] ?? [])
            : [];

        $out = [];
        foreach (array_keys(self::fieldGroups()) as $group) {
            $out[$group] = (isset($saved[$group]) && $saved[$group] !== '') ? $saved[$group] : $group;
        }

        return $out;
    }

    /**
     * Ordered form layout for the team form.
     *
     * @return array<int, array{key:string,title:string,fields:array<int,string>}>
     */
    public static function getFormLayout(?TournamentSetting $settings, bool $visibleOnly = false): array
    {
        $groups = self::fieldGroups();
        $titles = self::getSectionLabels($settings);
        $fieldConfig = self::getFieldConfig($settings);

        $savedSectionOrder = ($settings && $settings->team_registration_form_fields)
            ? ($settings->team_registration_form_fields['_section_order'] ?? [])
            : [];

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
                if (empty($fields)) {
                    continue;
                }
            }

            usort($fields, fn ($a, $b) => ($fieldConfig[$a]['order'] ?? 0) <=> ($fieldConfig[$b]['order'] ?? 0));

            $layout[] = ['key' => $sectionKey, 'title' => $titles[$sectionKey] ?? $sectionKey, 'fields' => $fields];
        }

        return $layout;
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
        // Groups double as the team form's visual sections (titles are editable).
        return [
            'Team Information' => ['team_name', 'team_short_name', 'team_logo', 'team_description'],
            'Team Manager Details' => ['captain_name', 'captain_email', 'captain_phone'],
            'Team Owner Details' => ['vice_captain_name', 'vice_captain_email', 'vice_captain_phone'],
            'Terms & Conditions' => ['terms_and_conditions'],
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

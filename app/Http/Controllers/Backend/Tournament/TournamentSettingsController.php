<?php

namespace App\Http\Controllers\Backend\Tournament;

use App\Helpers\PlayerFormConfig;
use App\Helpers\TeamFormConfig;
use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\TournamentSetting;
use App\Services\LogoProcessingService;
use App\Services\Poster\TournamentFlyerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class TournamentSettingsController extends Controller
{
    public function __construct(
        private readonly TournamentFlyerService $flyerService
    ) {}

    public function edit(Tournament $tournament): View
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        $settings = $tournament->settings ?? $tournament->settings()->create([]);
        $fieldConfig = PlayerFormConfig::getFieldConfig($settings);
        $sectionLabels = PlayerFormConfig::getSectionLabels($settings);
        $teamFieldConfig = TeamFormConfig::getFieldConfig($settings);
        $teamSectionLabels = TeamFormConfig::getSectionLabels($settings);

        return view('backend.pages.tournaments.settings.edit', [
            'tournament' => $tournament,
            'settings' => $settings,
            'fieldConfig' => $fieldConfig,
            'sectionLabels' => $sectionLabels,
            'teamFieldConfig' => $teamFieldConfig,
            'teamSectionLabels' => $teamSectionLabels,
            'breadcrumbs' => [
                'title' => __('Tournament Settings'),
                'items' => [
                    ['label' => __('Tournaments'), 'url' => route('admin.tournaments.index')],
                    ['label' => $tournament->name, 'url' => route('admin.tournaments.dashboard', $tournament)],
                ],
            ],
        ]);
    }

    public function update(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        $validated = $request->validate([
            // Branding
            'logo' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
            'background_image' => 'nullable|image|mimes:png,jpg,jpeg|max:5120',
            'flyer_image' => 'nullable|image|mimes:png,jpg,jpeg|max:5120',
            'og_image' => 'nullable|image|mimes:png,jpg,jpeg|max:5120',
            'photo_sample' => 'nullable|image|mimes:png,jpg,jpeg|max:5120',
            'photo_guidelines' => 'nullable|string|max:2000',
            'team_photo_sample' => 'nullable|image|mimes:png,jpg,jpeg|max:5120',
            'team_photo_guidelines' => 'nullable|string|max:2000',
            'primary_color' => 'nullable|string|max:7',
            'secondary_color' => 'nullable|string|max:7',
            'accent_color' => 'nullable|string|max:7',

            // Registration
            'player_registration_open' => 'boolean',
            'team_registration_open' => 'boolean',
            'registration_deadline' => 'nullable|date',
            'max_players_per_team' => 'integer|min:1|max:50',
            'min_players_per_team' => 'integer|min:1|max:50',
            'default_country' => 'nullable|string|max:2',
            'min_age' => 'nullable|integer|min:1|max:100',
            'max_age' => 'nullable|integer|min:1|max:100',

            // Fixture Settings
            'format' => 'in:group_knockout,league,knockout',
            'number_of_groups' => 'integer|min:1|max:16',
            'teams_per_group' => 'integer|min:2|max:20',
            'matches_per_week' => 'integer|min:1|max:50',
            'number_of_grounds' => 'integer|min:1|max:10',
            'has_quarter_finals' => 'boolean',
            'has_semi_finals' => 'boolean',
            'has_third_place' => 'boolean',
            'overs_per_match' => 'integer|min:1|max:50',

            // Points
            'points_per_win' => 'integer|min:0|max:10',
            'points_per_tie' => 'integer|min:0|max:10',
            'points_per_no_result' => 'integer|min:0|max:10',
            'points_per_loss' => 'integer|min:0|max:10',

            // Notifications
            'match_poster_days_before' => 'integer|min:1|max:14',
            'send_match_reminders' => 'boolean',
            'send_result_notifications' => 'boolean',

            // Summary
            'summary_update_mode' => 'in:manual,automatic',

            // Social
            'description' => 'nullable|string|max:1000',
            'rules' => 'nullable|string|max:5000',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:20',

            // Terms & Conditions
            'terms_and_conditions_content' => 'nullable|string|max:10000',
            'team_terms_and_conditions_content' => 'nullable|string|max:10000',
        ]);

        $settings = $tournament->settings ?? $tournament->settings()->create([]);

        // Build registration form fields config from checkboxes + custom labels + order
        if ($request->has('form_fields')) {
            $formFields = $this->buildFormFieldsConfig(
                $request,
                'form_fields',
                'form_sections',
                'form_section_order',
                PlayerFormConfig::defaultFormFields(),
                PlayerFormConfig::fieldLabels(),
                PlayerFormConfig::lockedFields()
            );
            $settings->update(['registration_form_fields' => $formFields]);
        }

        // Build team registration form fields config (labels + order + sections)
        if ($request->has('team_form_fields')) {
            $teamFormFields = $this->buildFormFieldsConfig(
                $request,
                'team_form_fields',
                'team_form_sections',
                'team_section_order',
                TeamFormConfig::defaultFormFields(),
                TeamFormConfig::fieldLabels(),
                TeamFormConfig::lockedFields()
            );
            $settings->update(['team_registration_form_fields' => $teamFormFields]);
        }

        // Registration page theme (colors/gradients/banner)
        if ($request->has('registration_theme') || $request->hasFile('registration_banner') || $request->boolean('remove_registration_banner')) {
            $theme = is_array($settings->registration_theme) ? $settings->registration_theme : [];

            foreach ((array) $request->input('registration_theme', []) as $key => $value) {
                $theme[$key] = ($value === '' || $value === null) ? null : (string) $value;
            }

            // Banner image upload / removal
            $existingBanner = $theme['banner_image'] ?? null;
            if ($request->boolean('remove_registration_banner') && $existingBanner) {
                Storage::disk('public')->delete($existingBanner);
                $theme['banner_image'] = null;
            }
            if ($request->hasFile('registration_banner')) {
                if ($existingBanner) {
                    Storage::disk('public')->delete($existingBanner);
                }
                $theme['banner_image'] = $request->file('registration_banner')->store('registration_banners', 'public');
            }

            $settings->update(['registration_theme' => $theme]);
        }

        // Handle file uploads
        if ($request->hasFile('logo')) {
            $validated['logo'] = LogoProcessingService::processLogo($request->file('logo'), 'tournament_logos', $settings->logo);
        }

        if ($request->hasFile('background_image')) {
            if ($settings->background_image) {
                Storage::disk('public')->delete($settings->background_image);
            }
            $validated['background_image'] = $request->file('background_image')->store('tournament_backgrounds', 'public');
        }

        if ($request->hasFile('flyer_image')) {
            if ($settings->flyer_image) {
                Storage::disk('public')->delete($settings->flyer_image);
            }
            $validated['flyer_image'] = $request->file('flyer_image')->store('tournament_flyers', 'public');
        }

        if ($request->hasFile('og_image')) {
            if ($settings->og_image) {
                Storage::disk('public')->delete($settings->og_image);
            }
            $validated['og_image'] = $request->file('og_image')->store('tournament_og', 'public');
        }

        if ($request->hasFile('photo_sample')) {
            if ($settings->photo_sample_path) {
                Storage::disk('public')->delete($settings->photo_sample_path);
            }
            $validated['photo_sample_path'] = $request->file('photo_sample')->store('photo_samples', 'public');
        }
        unset($validated['photo_sample']); // not a column

        if ($request->hasFile('team_photo_sample')) {
            if ($settings->team_photo_sample_path) {
                Storage::disk('public')->delete($settings->team_photo_sample_path);
            }
            $validated['team_photo_sample_path'] = $request->file('team_photo_sample')->store('photo_samples', 'public');
        }
        unset($validated['team_photo_sample']); // not a column

        // Handle boolean fields
        $validated['player_registration_open'] = $request->boolean('player_registration_open');
        $validated['team_registration_open'] = $request->boolean('team_registration_open');
        $validated['has_quarter_finals'] = $request->boolean('has_quarter_finals');
        $validated['has_semi_finals'] = $request->boolean('has_semi_finals');
        $validated['has_third_place'] = $request->boolean('has_third_place');
        $validated['send_match_reminders'] = $request->boolean('send_match_reminders');
        $validated['send_result_notifications'] = $request->boolean('send_result_notifications');

        $settings->update($validated);

        return redirect()->back()->with('success', __('Tournament settings updated successfully.'));
    }

    public function generateFlyer(Tournament $tournament): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        try {
            $path = $this->flyerService->generate($tournament);
            return redirect()->back()->with('success', __('Tournament flyer generated successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to generate flyer: ') . $e->getMessage());
        }
    }

    /**
     * Build a registration form-fields config (visible/required/label/order +
     * section titles + section order) from the submitted builder inputs.
     */
    protected function buildFormFieldsConfig(
        Request $request,
        string $fieldsKey,
        string $sectionsKey,
        string $sectionOrderKey,
        array $defaults,
        array $defaultLabels,
        array $lockedFields
    ): array {
        $config = [];
        foreach ($defaults as $key => $default) {
            $label = trim((string) $request->input("{$fieldsKey}.{$key}.label", ''));
            $order = $request->input("{$fieldsKey}.{$key}.order");

            $config[$key] = [
                'visible' => $request->has("{$fieldsKey}.{$key}.visible"),
                'required' => $request->has("{$fieldsKey}.{$key}.required"),
                // Only store a label override when it differs from the default.
                'label' => ($label !== '' && $label !== ($defaultLabels[$key] ?? '')) ? $label : null,
                'order' => is_numeric($order) ? (int) $order : null,
            ];
        }

        // Force locked fields visible+required (keep any custom label/order).
        foreach ($lockedFields as $forced) {
            $config[$forced]['visible'] = true;
            $config[$forced]['required'] = true;
        }

        // Custom section titles (reserved _sections key) + per-section visibility.
        // A section title input is rendered for every section, so its keys are the
        // full section list; a section is visible only when its checkbox is present.
        $sectionVisibleKey = str_replace('sections', 'section_visible', $sectionsKey);
        $sections = [];
        $sectionVisible = [];
        foreach ($request->input($sectionsKey, []) as $group => $title) {
            $title = trim((string) $title);
            if ($title !== '') {
                $sections[$group] = $title;
            }
            $sectionVisible[$group] = $request->has("{$sectionVisibleKey}.{$group}");
        }
        $config['_sections'] = $sections;
        $config['_section_visible'] = $sectionVisible;

        // Section order (reserved _section_order key) — JSON array of section keys.
        $decoded = json_decode((string) $request->input($sectionOrderKey, ''), true);
        $config['_section_order'] = is_array($decoded) ? array_values($decoded) : [];

        return $config;
    }

    public function updateStatus(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['tournament.edit']);

        $validated = $request->validate([
            'status' => 'required|in:draft,registration,active,completed',
        ]);

        $tournament->update($validated);

        return redirect()->back()->with('success', __('Tournament status updated.'));
    }
}

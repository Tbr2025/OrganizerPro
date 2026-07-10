<?php

namespace App\Http\Controllers\Public;

use App\Helpers\PlayerFormConfig;
use App\Helpers\TeamFormConfig;
use App\Http\Controllers\Controller;
use App\Models\ActualTeam;
use App\Models\BattingProfile;
use App\Models\BowlingProfile;
use App\Models\KitSize;
use App\Models\PlayerLocation;
use App\Models\PlayerType;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\Tournament\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    public function __construct(
        private readonly RegistrationService $registrationService
    ) {}

    /**
     * Show player registration form
     */
    public function playerForm(Tournament $tournament): View
    {
        $settings = $tournament->settings;

        // Check if registration is open
        if (!$this->registrationService->isPlayerRegistrationOpen($tournament)) {
            return view('public.registration.closed', [
                'tournament' => $tournament,
                'type' => 'player',
                'tournamentStatus' => $settings->player_registration_status ?? 'closed',
            ]);
        }

        $fieldConfig = PlayerFormConfig::getFieldConfig($settings);

        // Playing Team options: teams linked to this tournament (column or pivot);
        // fall back to the organization's teams so the field still renders when
        // none are linked yet.
        $actualTeams = ActualTeam::forTournament($tournament->id)->orderBy('name')->get();
        if ($actualTeams->isEmpty()) {
            $actualTeams = ActualTeam::where('organization_id', $tournament->organization_id)->orderBy('name')->get();
        }

        return view('public.registration.player', [
            'tournament' => $tournament,
            'settings' => $settings,
            'fieldConfig' => $fieldConfig,
            'battingProfiles' => BattingProfile::all(),
            'bowlingProfiles' => BowlingProfile::all(),
            'playerTypes' => PlayerType::all(),
            'kitSizes' => KitSize::all(),
            'locations' => PlayerLocation::where(function($query) use ($tournament) {
                $query->whereNull('organization_id')
                      ->orWhere('organization_id', $tournament->organization_id);
            })->get(),
            'teams' => Team::where('tournament_id', $tournament->id)->get(),
            'actualTeams' => $actualTeams,
            // Per-tournament default nationality, falling back to the global setting.
            'defaultCountry' => ($settings?->default_country) ?: config('settings.default_country', 'IN'),
        ]);
    }

    /**
     * Store player registration
     */
    public function storePlayer(Request $request, Tournament $tournament): RedirectResponse
    {
        // Check if registration is open
        if (!$this->registrationService->isPlayerRegistrationOpen($tournament)) {
            return redirect()->back()->with('error', __('Player registration is closed.'));
        }

        // Compose the internal full name from first + last so the locked `name` rule passes.
        $request->merge(['name' => trim($request->input('first_name', '') . ' ' . $request->input('last_name', ''))]);

        // "No travel plans" → clear any travel dates so the cross-field rule never fires.
        if ($request->boolean('no_travel_plan')) {
            $request->merge(['travel_date_from' => null, 'travel_date_to' => null]);
        }

        // "Other" team choice submits team_id="other" (not a real id) — treat as
        // free-text only so the exists:teams rule doesn't reject it.
        if ($request->input('team_id') === 'other') {
            $request->merge(['team_id' => null]);
        }

        $fieldConfig = PlayerFormConfig::getFieldConfig($tournament->settings);
        $rules = PlayerFormConfig::buildValidationRules($fieldConfig, 'public', $tournament->settings);

        // If image was pre-processed via AJAX, relax the file validation
        if ($request->filled('processed_image_path')) {
            $rules['image'] = 'nullable';
            $rules['processed_image_path'] = 'required|string|max:500';
        }

        // When the tournament has T&C content, a typed signature is required.
        if (!empty($tournament->settings?->terms_and_conditions_content)) {
            $rules['consent_name'] = 'required|string|max:150';
        }

        // Custom (admin-defined) fields — validation rules by type.
        $customFields = $tournament->customFields()->where('visible', true)->where('form', 'player')->get();
        foreach ($customFields as $cf) {
            $key = 'custom_fields.' . $cf->id;
            if ($cf->type === 'checkbox') {
                $rules[$key] = $cf->required ? 'accepted' : 'nullable';
                continue;
            }
            $parts = [$cf->required ? 'required' : 'nullable'];
            if ($cf->type === 'number') {
                $parts[] = 'numeric';
            } elseif ($cf->type === 'date') {
                $parts[] = 'date';
            } elseif ($cf->type === 'dropdown' && ! empty($cf->options)) {
                $parts[] = 'in:' . implode(',', $cf->options);
            } else {
                $parts[] = 'string';
                $parts[] = 'max:1000';
            }
            $rules[$key] = implode('|', $parts);
        }

        $validated = $request->validate($rules);

        // Collect custom field answers keyed by cf_<id> for storage/verification.
        $customValues = [];
        foreach ($customFields as $cf) {
            $val = $cf->type === 'checkbox'
                ? ($request->boolean('custom_fields.' . $cf->id) ? '1' : '0')
                : $request->input('custom_fields.' . $cf->id);
            if ($val !== null && $val !== '') {
                $customValues['cf_' . $cf->id] = $val;
            }
        }
        $validated['custom_field_values'] = $customValues;

        $validated['is_wicket_keeper'] = $request->boolean('is_wicket_keeper');
        $validated['transportation_required'] = $request->boolean('transportation_required');
        $validated['no_travel_plan'] = $request->boolean('no_travel_plan');
        $validated['available_saturday'] = $request->boolean('available_saturday');
        $validated['available_sunday'] = $request->boolean('available_sunday');
        $validated['available_weekends'] = $request->boolean('available_saturday') || $request->boolean('available_sunday');
        $validated['played_ys_ipl_s1'] = $request->boolean('played_ys_ipl_s1');

        // Digitally-signed consent: capture typed name + IP + a snapshot of the
        // T&C content the applicant accepted (timestamp set in the service).
        if ($request->filled('consent_name')) {
            $validated['consent_name'] = $request->input('consent_name');
            $validated['consent_ip'] = $request->ip();
            $validated['consent_snapshot'] = $tournament->settings?->terms_and_conditions_content;
        }

        // Handle image — pre-processed path from AJAX upload, or fallback to raw file
        if ($request->filled('processed_image_path')
            && Storage::disk('public')->exists($request->input('processed_image_path'))) {
            $validated['image_path'] = $request->input('processed_image_path');
        } elseif ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('player_images', 'public');
        }

        try {
            $registration = $this->registrationService->registerPlayer($tournament, $validated);

            return redirect()->route('public.tournament.registration.player.success', [
                'tournament' => $tournament->slug,
            ])->with('success', __('Registration submitted successfully!'));
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', __('Registration failed: ') . $e->getMessage());
        }
    }

    /**
     * Show team registration form
     */
    public function teamForm(Tournament $tournament): View
    {
        $settings = $tournament->settings;

        // Check if registration is open
        if (!$this->registrationService->isTeamRegistrationOpen($tournament)) {
            return view('public.registration.closed', [
                'tournament' => $tournament,
                'type' => 'team',
                'tournamentStatus' => $settings->team_registration_status ?? 'closed',
            ]);
        }

        $teamFieldConfig = TeamFormConfig::getFieldConfig($settings);

        return view('public.registration.team', [
            'tournament' => $tournament,
            'settings' => $settings,
            'teamFieldConfig' => $teamFieldConfig,
        ]);
    }

    /**
     * Store team registration
     */
    public function storeTeam(Request $request, Tournament $tournament): RedirectResponse
    {
        // Check if registration is open
        if (!$this->registrationService->isTeamRegistrationOpen($tournament)) {
            return redirect()->back()->with('error', __('Team registration is closed.'));
        }

        $teamFieldConfig = TeamFormConfig::getFieldConfig($tournament->settings);
        $rules = TeamFormConfig::buildValidationRules($teamFieldConfig);
        // Typed signature required when the team T&C content is configured.
        if (!empty($tournament->settings?->team_terms_and_conditions_content)) {
            $rules['consent_name'] = 'required|string|max:150';
        }

        // Custom (admin-defined) team fields — validation rules by type.
        $customFields = $tournament->customFields()->where('visible', true)->where('form', 'team')->get();
        foreach ($customFields as $cf) {
            $key = 'custom_fields.' . $cf->id;
            if ($cf->type === 'checkbox') {
                $rules[$key] = $cf->required ? 'accepted' : 'nullable';
                continue;
            }
            $parts = [$cf->required ? 'required' : 'nullable'];
            if ($cf->type === 'number') {
                $parts[] = 'numeric';
            } elseif ($cf->type === 'date') {
                $parts[] = 'date';
            } elseif ($cf->type === 'dropdown' && ! empty($cf->options)) {
                $parts[] = 'in:' . implode(',', $cf->options);
            } else {
                $parts[] = 'string';
                $parts[] = 'max:1000';
            }
            $rules[$key] = implode('|', $parts);
        }

        $validated = $request->validate($rules);

        // Handle cropped team logo (base64 from cropper)
        if ($request->filled('team_logo_cropped')) {
            $base64 = $request->input('team_logo_cropped');
            $imageData = preg_replace('#^data:image/\w+;base64,#i', '', $base64);
            $imageData = base64_decode($imageData);
            if ($imageData) {
                $filename = 'team_logos/' . Str::random(40) . '.png';
                Storage::disk('public')->put($filename, $imageData);
                $validated['team_logo'] = new \Illuminate\Http\UploadedFile(
                    Storage::disk('public')->path($filename), basename($filename), 'image/png', null, true
                );
                // Override: store path directly since file is already saved
                $validated['team_logo_path'] = $filename;
            }
        }

        // Digitally-signed consent capture (typed name + IP + T&C snapshot).
        if ($request->filled('consent_name')) {
            $validated['consent_name'] = $request->input('consent_name');
            $validated['consent_ip'] = $request->ip();
            $validated['consent_snapshot'] = $tournament->settings?->team_terms_and_conditions_content;
        }

        // Collect custom field answers keyed by cf_<id>.
        $customValues = [];
        foreach ($customFields as $cf) {
            $val = $cf->type === 'checkbox'
                ? ($request->boolean('custom_fields.' . $cf->id) ? '1' : '0')
                : $request->input('custom_fields.' . $cf->id);
            if ($val !== null && $val !== '') {
                $customValues['cf_' . $cf->id] = $val;
            }
        }
        $validated['custom_field_values'] = $customValues;

        try {
            $registration = $this->registrationService->registerTeam($tournament, $validated);

            return redirect()->route('public.tournament.registration.team.success', [
                'tournament' => $tournament->slug,
            ])->with('success', __('Team registration submitted successfully!'));
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', __('Registration failed: ') . $e->getMessage());
        }
    }

    /**
     * Show registration success page
     */
    public function success(Tournament $tournament, string $type): View
    {
        return view('public.registration.success', [
            'tournament' => $tournament,
            'type' => $type,
            'settings' => $tournament->settings,
        ]);
    }
}

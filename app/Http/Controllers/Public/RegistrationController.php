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
use App\Models\Player;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Services\Tournament\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    public function __construct(
        private readonly RegistrationService $registrationService
    ) {
    }

    /**
     * Show player registration form
     */
    public function playerForm(Request $request, Tournament $tournament): View
    {
        $settings = $tournament->settings;

        // Check if registration is open
        if (! $this->registrationService->isPlayerRegistrationOpen($tournament)) {
            // Show tournament-level status if that's what's blocking, otherwise per-type status
            $tsStatus = $settings->tournament_status ?? 'open';
            $displayStatus = $tsStatus !== 'open' ? $tsStatus : ($settings->player_registration_status ?? 'closed');
            return view('public.registration.closed', [
                'tournament' => $tournament,
                'type' => 'player',
                'tournamentStatus' => $displayStatus,
            ]);
        }

        $fieldConfig = PlayerFormConfig::getFieldConfig($settings);

        // Playing Team options: only show teams from open tournaments
        $actualTeams = ActualTeam::where('organization_id', $tournament->organization_id)
            ->whereHas('tournament', function ($q) {
                $q->where('type', 'open');
            })
            ->orderBy('name')
            ->get();

        // Prefill values (used when team managers redirect here to register themselves)
        $prefill = [
            'first_name' => $request->query('prefill_first_name'),
            'last_name' => $request->query('prefill_last_name'),
            'email' => $request->query('prefill_email'),
        ];

        return view('public.registration.player', [
            'tournament' => $tournament,
            'settings' => $settings,
            'fieldConfig' => $fieldConfig,
            'battingProfiles' => BattingProfile::whereIn('style', ['Right-hand Bat', 'Left-hand Bat', 'Ambidextrous'])->get(),
            'bowlingProfiles' => BowlingProfile::all(),
            'playerTypes' => PlayerType::whereIn('type', ['Batsman', 'Bowler', 'All-Rounder'])->get(),
            'kitSizes' => KitSize::all(),
            'locations' => PlayerLocation::where(function ($query) use ($tournament) {
                $query->whereNull('organization_id')
                      ->orWhere('organization_id', $tournament->organization_id);
            })->get(),
            'teams' => Team::where('tournament_id', $tournament->id)->get(),
            'actualTeams' => $actualTeams,
            // Per-tournament default nationality, falling back to the global setting.
            'defaultCountry' => ($settings?->default_country) ?: config('settings.default_country', 'IN'),
            'defaultPhoneCountry' => ($settings?->default_phone_country) ?: ($settings?->default_country) ?: config('settings.default_country', 'IN'),
            'prefill' => $prefill,
        ]);
    }

    /**
     * Store player registration
     */
    public function storePlayer(Request $request, Tournament $tournament): RedirectResponse
    {
        // Check if registration is open
        if (! $this->registrationService->isPlayerRegistrationOpen($tournament)) {
            return redirect()->back()->with('error', __('Player registration is closed.'));
        }

        // Verify Turnstile CAPTCHA (skip if keys not configured).
        if (config('turnstile.secret_key') && ! app()->environment('local')) {
            $turnstileResponse = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => config('turnstile.secret_key'),
                'response' => $request->input('cf-turnstile-response', ''),
                'remoteip' => $request->ip(),
            ]);

            if (! $turnstileResponse->json('success')) {
                return redirect()->back()->withInput()->with('error', __('CAPTCHA verification failed. Please try again.'));
            }
        }

        // Prevent duplicate registrations: one email per tournament (unless rejected).
        $existingPlayer = Player::where('email', $request->input('email'))->first();
        if ($existingPlayer) {
            $activeRegistration = TournamentRegistration::where('tournament_id', $tournament->id)
                ->where('player_id', $existingPlayer->id)
                ->where('type', 'player')
                ->whereIn('status', ['pending', 'approved', 'queued'])
                ->first();

            if ($activeRegistration) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', __('You have already registered for this tournament. Your registration is currently :status.', [
                        'status' => $activeRegistration->status,
                    ]));
            }
        }

        // Compose the internal full name from first + last so the locked `name` rule passes.
        $request->merge(['name' => trim($request->input('first_name', '') . ' ' . $request->input('last_name', ''))]);

        // Merge country-code + national number into the full number fields.
        $mobileCode = str_replace('+', '', $request->input('mobile_country_code', ''));
        $mobileNat = $request->input('mobile_national_number', '');
        $request->merge(['mobile_number_full' => $mobileCode . $mobileNat]);

        $cricCode = str_replace('+', '', $request->input('cricheroes_country_code', ''));
        $cricNat = $request->input('cricheroes_national_number', '');
        if ($cricCode || $cricNat) {
            $request->merge(['cricheroes_number_full' => $cricCode . $cricNat]);
        }

        // Map transportation dropdown to the existing boolean column.
        $request->merge([
            'transportation_required' => $request->input('transportation_mode') === 'required',
        ]);

        // Map travel plan dropdown → existing columns.
        $hasTravelPlan = $request->input('has_travel_plan') === 'yes';
        $request->merge([
            'no_travel_plan' => ! $hasTravelPlan,
        ]);
        if (! $hasTravelPlan) {
            $request->merge(['travel_date_from' => null, 'travel_date_to' => null]);
        }

        // "Other" team choice submits team_id="other" (not a real id) — treat as
        // free-text only so the exists:teams rule doesn't reject it.
        if ($request->input('team_id') === 'other') {
            $request->merge(['team_id' => null]);
        }

        // "Other" playing team — store free-text name, clear actual_team_id.
        $playingTeamIsOther = $request->input('actual_team_id') === 'other';
        if ($playingTeamIsOther) {
            $request->merge(['actual_team_id' => null]);
        }

        $fieldConfig = PlayerFormConfig::getFieldConfig($tournament->settings);
        $rules = PlayerFormConfig::buildValidationRules($fieldConfig, 'public', $tournament->settings);

        // When "Others" is selected, actual_team_id is null — make it optional
        // and require the free-text team name instead.
        if ($playingTeamIsOther) {
            $rules['actual_team_id'] = 'nullable';
            $rules['playing_team_name_ref'] = 'required|string|max:100';
        }

        // Image is always uploaded via AJAX (file input has no name attribute),
        // so validate via processed_image_path instead of the 'image' file key.
        $imageRequired = ($fieldConfig['image']['required'] ?? false) && ($fieldConfig['image']['visible'] ?? true);
        if ($request->filled('processed_image_path')) {
            $rules['image'] = 'nullable';
            $rules['processed_image_path'] = 'required|string|max:500';
        } elseif ($imageRequired) {
            $rules['image'] = 'nullable';
            $rules['processed_image_path'] = 'required';
        }

        // When the tournament has T&C content, a typed signature is required.
        if (! empty($tournament->settings?->terms_and_conditions_content)) {
            $rules['consent_name'] = 'required|string|max:150';
        }

        // Custom (admin-defined) fields — rules come from the field's own definition.
        $customFields = $tournament->customFields()->where('visible', true)->where('form', 'player')->get();
        $customAnswers = $this->customFieldAnswers($request, $customFields);
        $rules = array_merge($rules, $this->customFieldRules($customFields, $customAnswers));

        $validated = $request->validate($rules, [
            'processed_image_path.required' => 'Please upload a player photo.',
        ]);

        // Collect custom field answers keyed by cf_<id> for storage/verification.
        $validated['custom_field_values'] = $this->collectCustomFieldValues($request, $customFields, $customAnswers, $tournament);

        // Resolve "Other" size selections to the custom value
        if (($validated['tshirt_size'] ?? null) === 'Other' && ! empty($validated['tshirt_size_custom'])) {
            $validated['tshirt_size'] = $validated['tshirt_size_custom'];
        }
        if (($validated['pant_size'] ?? null) === 'Other' && ! empty($validated['pant_size_custom'])) {
            $validated['pant_size'] = $validated['pant_size_custom'];
        }
        unset($validated['tshirt_size_custom'], $validated['pant_size_custom']);

        $validated['is_wicket_keeper'] = $request->boolean('is_wicket_keeper');
        $validated['transportation_required'] = $request->boolean('transportation_required');
        $validated['no_travel_plan'] = $request->boolean('no_travel_plan');
        $validated['available_saturday'] = $request->boolean('available_saturday');
        $validated['available_sunday'] = $request->boolean('available_sunday');
        $validated['available_weekends'] = $request->boolean('available_saturday') || $request->boolean('available_sunday');
        $validated['played_ys_ipl_s1'] = $request->boolean('played_ys_ipl_s1');

        // Re-attach merged full-number fields (not in validation rules, so stripped by validate()).
        $validated['mobile_number_full'] = $request->input('mobile_number_full');
        $validated['cricheroes_number_full'] = $request->input('cricheroes_number_full');

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
        if (! $this->registrationService->isTeamRegistrationOpen($tournament)) {
            $tsStatus = $settings->tournament_status ?? 'open';
            $displayStatus = $tsStatus !== 'open' ? $tsStatus : ($settings->team_registration_status ?? 'closed');
            return view('public.registration.closed', [
                'tournament' => $tournament,
                'type' => 'team',
                'tournamentStatus' => $displayStatus,
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
        if (! $this->registrationService->isTeamRegistrationOpen($tournament)) {
            return redirect()->back()->with('error', __('Team registration is closed.'));
        }

        // Verify Turnstile CAPTCHA (skip if keys not configured).
        if (config('turnstile.secret_key') && ! app()->environment('local')) {
            $turnstileResponse = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => config('turnstile.secret_key'),
                'response' => $request->input('cf-turnstile-response', ''),
                'remoteip' => $request->ip(),
            ]);

            if (! $turnstileResponse->json('success')) {
                return redirect()->back()->withInput()->with('error', __('CAPTCHA verification failed. Please try again.'));
            }
        }

        $teamFieldConfig = TeamFormConfig::getFieldConfig($tournament->settings);
        $rules = TeamFormConfig::buildValidationRules($teamFieldConfig);
        // Typed signature required when the team T&C content is configured.
        if (! empty($tournament->settings?->team_terms_and_conditions_content)) {
            $rules['consent_name'] = 'required|string|max:150';
        }

        // Custom (admin-defined) team fields — rules come from the field's own definition.
        $customFields = $tournament->customFields()->where('visible', true)->where('form', 'team')->get();
        $customAnswers = $this->customFieldAnswers($request, $customFields);
        $rules = array_merge($rules, $this->customFieldRules($customFields, $customAnswers));

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
                    Storage::disk('public')->path($filename),
                    basename($filename),
                    'image/png',
                    null,
                    true
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
        $validated['custom_field_values'] = $this->collectCustomFieldValues($request, $customFields, $customAnswers, $tournament);

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

    /*
     * ---------------------------------------------------------------------
     * Custom (admin-defined) registration fields
     *
     * Shared by both the player and the team form, which had the same block
     * copied into each and so drifted apart every time a type was added.
     * ---------------------------------------------------------------------
     */

    /**
     * The answers so far, in the shape conditions are written against.
     *
     * Read from the RAW request, before validation, because a field's conditions decide whether
     * it is validated at all. Keyed by custom field id AND by core input name, so a condition can
     * key off either "Playing Role" (a custom field) or `jersey_name` (a built-in one).
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\TournamentCustomField>  $customFields
     * @return array<string, mixed>
     */
    protected function customFieldAnswers(Request $request, $customFields): array
    {
        // Core inputs first, so a custom field id can never be shadowed by one of them.
        $answers = $request->except(['custom_fields', '_token', 'password', 'password_confirmation']);

        foreach ($customFields as $cf) {
            $key = 'custom_fields.' . $cf->id;

            // A file has no readable value; for conditions it is simply present or absent.
            $answers[(string) $cf->id] = $cf->isFile()
                ? ($request->hasFile($key) ? 'uploaded' : '')
                : $request->input($key);
        }

        return $answers;
    }

    /**
     * Validation rules for the custom fields that are actually being asked.
     *
     * A field hidden by its own conditions contributes NO rules — otherwise a required question
     * the registrant was never shown would reject the form with an error pointing at a field that
     * is not on screen. Layout-only types (heading, divider) contribute nothing either.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\TournamentCustomField>  $customFields
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    protected function customFieldRules($customFields, array $answers): array
    {
        $rules = [];

        foreach ($customFields as $cf) {
            if ($cf->isLayoutOnly() || ! $cf->isVisibleGiven($answers)) {
                continue;
            }

            $fieldRules = $cf->validationRules();

            if ($fieldRules === []) {
                continue;
            }

            $rules['custom_fields.' . $cf->id] = $fieldRules;

            // For a multi-choice field the rules above describe the LIST; each chosen value must
            // also be one of the options on offer.
            if ($cf->isMultiValue() && ! empty($cf->options)) {
                $rules['custom_fields.' . $cf->id . '.*'] = ['string', \Illuminate\Validation\Rule::in($cf->options)];
            }
        }

        return $rules;
    }

    /**
     * The answers to store, keyed cf_<id>.
     *
     * A hidden field stores nothing: if the question was not asked, an answer left over from an
     * earlier attempt (or posted by hand) must not be recorded as though it had been.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\TournamentCustomField>  $customFields
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    protected function collectCustomFieldValues(Request $request, $customFields, array $answers, Tournament $tournament): array
    {
        $values = [];

        foreach ($customFields as $cf) {
            if ($cf->isLayoutOnly() || ! $cf->isVisibleGiven($answers)) {
                continue;
            }

            $key = 'custom_fields.' . $cf->id;

            if ($cf->isFile()) {
                if ($request->hasFile($key)) {
                    // Kept out of the image folders: this is a document (certificate, ID proof),
                    // not a player photo, and nothing downstream should treat it as one.
                    $values['cf_' . $cf->id] = $request->file($key)
                        ->store('registration_files/' . $tournament->id, 'public');
                }
                continue;
            }

            if ($cf->type === 'checkbox') {
                $values['cf_' . $cf->id] = $request->boolean($key) ? '1' : '0';
                continue;
            }

            $val = $request->input($key);

            if ($cf->isMultiValue()) {
                $val = array_values(array_filter(
                    is_array($val) ? $val : [],
                    fn ($v) => $v !== null && $v !== ''
                ));

                if ($val !== []) {
                    $values['cf_' . $cf->id] = $val;
                }

                continue;
            }

            if ($val !== null && $val !== '') {
                $values['cf_' . $cf->id] = $val;
            }
        }

        return $values;
    }
}

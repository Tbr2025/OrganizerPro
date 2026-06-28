<?php

namespace App\Http\Controllers\Public;

use App\Helpers\PlayerFormConfig;
use App\Helpers\TeamFormConfig;
use App\Http\Controllers\Controller;
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
            ]);
        }

        $fieldConfig = PlayerFormConfig::getFieldConfig($settings);

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
            'defaultCountry' => config('settings.default_country', ''),
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

        $fieldConfig = PlayerFormConfig::getFieldConfig($tournament->settings);
        $rules = PlayerFormConfig::buildValidationRules($fieldConfig, 'public');

        // If image was pre-processed via AJAX, relax the file validation
        if ($request->filled('processed_image_path')) {
            $rules['image'] = 'nullable';
            $rules['processed_image_path'] = 'required|string|max:500';
        }

        $validated = $request->validate($rules);

        $validated['is_wicket_keeper'] = $request->boolean('is_wicket_keeper');
        $validated['transportation_required'] = $request->boolean('transportation_required');
        $validated['no_travel_plan'] = $request->boolean('no_travel_plan');
        $validated['available_saturday'] = $request->boolean('available_saturday');
        $validated['available_sunday'] = $request->boolean('available_sunday');
        $validated['available_weekends'] = $request->boolean('available_saturday') || $request->boolean('available_sunday');
        $validated['played_ys_ipl_s1'] = $request->boolean('played_ys_ipl_s1');

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
        $validated = $request->validate($rules);

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

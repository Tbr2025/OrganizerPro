<?php

namespace App\Http\Controllers\Public;

use App\Helpers\PlayerFormConfig;
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

        $fieldConfig = PlayerFormConfig::getFieldConfig($tournament->settings);
        $rules = PlayerFormConfig::buildValidationRules($fieldConfig, 'public');

        $validated = $request->validate($rules);

        $validated['is_wicket_keeper'] = $request->boolean('is_wicket_keeper');
        $validated['transportation_required'] = $request->boolean('transportation_required');
        $validated['no_travel_plan'] = $request->boolean('no_travel_plan');

        // Handle image upload
        if ($request->hasFile('image')) {
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

        return view('public.registration.team', [
            'tournament' => $tournament,
            'settings' => $settings,
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

        $validated = $request->validate([
            'team_name' => 'required|string|max:100',
            'team_short_name' => 'nullable|string|max:10',
            'team_logo' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
            'captain_name' => 'required|string|max:100',
            'captain_email' => 'required|email|max:255',
            'captain_phone' => 'required|string|max:20',
            'vice_captain_name' => 'nullable|string|max:100',
            'vice_captain_email' => 'nullable|email|max:255',
            'vice_captain_phone' => 'nullable|string|max:20',
            'team_description' => 'nullable|string|max:500',
        ]);

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
        ]);
    }
}

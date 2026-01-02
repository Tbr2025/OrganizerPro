<?php

namespace App\Http\Controllers\Backend\Tournament;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\TournamentSetting;
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

        return view('backend.pages.tournaments.settings.edit', [
            'tournament' => $tournament,
            'settings' => $settings,
            'breadcrumbs' => [
                'title' => __('Tournament Settings'),
                'items' => [
                    ['label' => __('Tournaments'), 'url' => route('admin.tournaments.index')],
                    ['label' => $tournament->name, 'url' => route('admin.tournaments.show', $tournament)],
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
            'primary_color' => 'nullable|string|max:7',
            'secondary_color' => 'nullable|string|max:7',

            // Registration
            'player_registration_open' => 'boolean',
            'team_registration_open' => 'boolean',
            'registration_deadline' => 'nullable|date',
            'max_players_per_team' => 'integer|min:1|max:50',
            'min_players_per_team' => 'integer|min:1|max:50',

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

            // Social
            'description' => 'nullable|string|max:1000',
            'rules' => 'nullable|string|max:5000',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:20',
        ]);

        $settings = $tournament->settings ?? $tournament->settings()->create([]);

        // Handle file uploads
        if ($request->hasFile('logo')) {
            if ($settings->logo) {
                Storage::disk('public')->delete($settings->logo);
            }
            $validated['logo'] = $request->file('logo')->store('tournament_logos', 'public');
        }

        if ($request->hasFile('background_image')) {
            if ($settings->background_image) {
                Storage::disk('public')->delete($settings->background_image);
            }
            $validated['background_image'] = $request->file('background_image')->store('tournament_backgrounds', 'public');
        }

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

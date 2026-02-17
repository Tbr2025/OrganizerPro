<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Mail\NewRegistrationAdminMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class PublicTournamentRegistrationController extends Controller
{
    /**
     * Show the registration form for a tournament
     */
    public function showForm(Tournament $tournament)
    {
        // Check if registration is open
        $settings = $tournament->settings;

        if (!$settings || (!$settings->player_registration_open && !$settings->team_registration_open)) {
            return view('public.tournament.registration-closed', compact('tournament'));
        }

        return view('public.tournament.register', [
            'tournament' => $tournament,
            'playerRegistrationOpen' => $settings->player_registration_open ?? false,
            'teamRegistrationOpen' => $settings->team_registration_open ?? false,
        ]);
    }

    /**
     * Handle player registration submission
     */
    public function registerPlayer(Request $request, Tournament $tournament)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'batting_style' => 'nullable|string|max:50',
            'bowling_style' => 'nullable|string|max:50',
            'playing_role' => 'nullable|string|max:50',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        // Create or find player
        $player = Player::firstOrCreate(
            ['email' => $validated['email']],
            [
                'name' => $validated['name'],
                'mobile_number_full' => $validated['phone'] ?? null,
                'status' => 'pending',
            ]
        );

        // Handle photo upload
        if ($request->hasFile('photo')) {
            $player->image_path = $request->file('photo')->store('player_images', 'public');
            $player->save();
        }

        // Check for existing registration
        $existingRegistration = TournamentRegistration::where('tournament_id', $tournament->id)
            ->where('player_id', $player->id)
            ->first();

        if ($existingRegistration) {
            return redirect()->back()->with('error', 'You have already registered for this tournament.');
        }

        // Create registration
        $registration = TournamentRegistration::create([
            'tournament_id' => $tournament->id,
            'type' => 'player',
            'player_id' => $player->id,
            'status' => 'pending',
        ]);

        // Send email notification to admin
        $this->notifyAdmin($tournament, $registration, 'player');

        return redirect()->route('public.tournament.registration.success', $tournament)
            ->with('success', 'Registration submitted successfully! You will receive an email once approved.');
    }

    /**
     * Handle team registration submission
     */
    public function registerTeam(Request $request, Tournament $tournament)
    {
        $validated = $request->validate([
            'team_name' => 'required|string|max:255',
            'team_short_name' => 'nullable|string|max:10',
            'team_logo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'captain_name' => 'required|string|max:255',
            'captain_email' => 'required|email|max:255',
            'captain_phone' => 'required|string|max:20',
            'vice_captain_name' => 'nullable|string|max:255',
            'vice_captain_phone' => 'nullable|string|max:20',
            'team_description' => 'nullable|string|max:1000',
        ]);

        // Check for existing team registration with same name
        $existingRegistration = TournamentRegistration::where('tournament_id', $tournament->id)
            ->where('type', 'team')
            ->where('team_name', $validated['team_name'])
            ->first();

        if ($existingRegistration) {
            return redirect()->back()->with('error', 'A team with this name has already registered.');
        }

        // Handle team logo upload
        $logoPath = null;
        if ($request->hasFile('team_logo')) {
            $logoPath = $request->file('team_logo')->store('team_logos', 'public');
        }

        // Create registration
        $registration = TournamentRegistration::create([
            'tournament_id' => $tournament->id,
            'type' => 'team',
            'team_name' => $validated['team_name'],
            'team_short_name' => $validated['team_short_name'],
            'team_logo' => $logoPath,
            'captain_name' => $validated['captain_name'],
            'captain_email' => $validated['captain_email'],
            'captain_phone' => $validated['captain_phone'],
            'vice_captain_name' => $validated['vice_captain_name'],
            'vice_captain_phone' => $validated['vice_captain_phone'],
            'team_description' => $validated['team_description'],
            'status' => 'pending',
        ]);

        // Send email notification to admin
        $this->notifyAdmin($tournament, $registration, 'team');

        return redirect()->route('public.tournament.registration.success', $tournament)
            ->with('success', 'Team registration submitted successfully! You will receive an email once approved.');
    }

    /**
     * Show registration success page
     */
    public function success(Tournament $tournament)
    {
        return view('public.tournament.registration-success', compact('tournament'));
    }

    /**
     * Notify admin about new registration
     */
    private function notifyAdmin(Tournament $tournament, TournamentRegistration $registration, string $type)
    {
        // Get admin email from tournament settings or organization
        $adminEmail = $tournament->settings?->contact_email
            ?? $tournament->organization?->email
            ?? config('mail.admin_email');

        if ($adminEmail) {
            try {
                Mail::to($adminEmail)->send(new NewRegistrationAdminMail($tournament, $registration, $type));
            } catch (\Exception $e) {
                \Log::error('Failed to send admin notification: ' . $e->getMessage());
            }
        }
    }
}

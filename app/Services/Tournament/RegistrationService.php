<?php

namespace App\Services\Tournament;

use App\Mail\PlayerWelcomeMail;
use App\Models\ActualTeam;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class RegistrationService
{
    /**
     * Register a player for a tournament
     */
    public function registerPlayer(Tournament $tournament, array $data): TournamentRegistration
    {
        return DB::transaction(function () use ($tournament, $data) {
            // Create user if not exists
            $user = User::where('email', $data['email'])->first();

            if (!$user) {
                $password = Str::random(12);
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'username' => Str::slug($data['name']) . '-' . Str::random(5),
                    'password' => Hash::make($password),
                    'organization_id' => $tournament->organization_id,
                ]);
            }

            // Create player if not exists
            $player = Player::where('email', $data['email'])->first();

            if (!$player) {
                $player = Player::create([
                    'user_id' => $user->id,
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'mobile_number_full' => $data['mobile_number'] ?? null,
                    'jersey_name' => $data['jersey_name'] ?? $data['name'],
                    'batting_profile_id' => $data['batting_profile_id'] ?? null,
                    'bowling_profile_id' => $data['bowling_profile_id'] ?? null,
                    'player_type_id' => $data['player_type_id'] ?? null,
                    'is_wicket_keeper' => $data['is_wicket_keeper'] ?? false,
                ]);
            }

            // Create registration
            return TournamentRegistration::create([
                'tournament_id' => $tournament->id,
                'type' => 'player',
                'player_id' => $player->id,
                'status' => 'pending',
            ]);
        });
    }

    /**
     * Register a team for a tournament
     */
    public function registerTeam(Tournament $tournament, array $data): TournamentRegistration
    {
        $logoPath = null;
        if (isset($data['team_logo']) && $data['team_logo']) {
            $logoPath = $data['team_logo']->store('team_logos', 'public');
        }

        return TournamentRegistration::create([
            'tournament_id' => $tournament->id,
            'type' => 'team',
            'team_name' => $data['team_name'],
            'team_short_name' => $data['team_short_name'] ?? null,
            'team_logo' => $logoPath,
            'captain_name' => $data['captain_name'],
            'captain_email' => $data['captain_email'],
            'captain_phone' => $data['captain_phone'],
            'vice_captain_name' => $data['vice_captain_name'] ?? null,
            'vice_captain_phone' => $data['vice_captain_phone'] ?? null,
            'team_description' => $data['team_description'] ?? null,
            'status' => 'pending',
        ]);
    }

    /**
     * Approve a player registration
     */
    public function approvePlayerRegistration(TournamentRegistration $registration, int $approvedBy): bool
    {
        if (!$registration->isPlayerRegistration() || !$registration->isPending()) {
            return false;
        }

        return DB::transaction(function () use ($registration, $approvedBy) {
            $registration->update([
                'status' => 'approved',
                'processed_at' => now(),
                'processed_by' => $approvedBy,
            ]);

            // TODO: Send welcome email with image
            // $this->sendWelcomeEmail($registration->player);

            return true;
        });
    }

    /**
     * Approve a team registration and create ActualTeam
     */
    public function approveTeamRegistration(TournamentRegistration $registration, int $approvedBy): ?ActualTeam
    {
        if (!$registration->isTeamRegistration() || !$registration->isPending()) {
            return null;
        }

        return DB::transaction(function () use ($registration, $approvedBy) {
            $tournament = $registration->tournament;

            // Create ActualTeam
            $actualTeam = ActualTeam::create([
                'organization_id' => $tournament->organization_id,
                'tournament_id' => $tournament->id,
                'name' => $registration->team_name,
                'team_logo' => $registration->team_logo,
            ]);

            // Update registration
            $registration->update([
                'status' => 'approved',
                'processed_at' => now(),
                'processed_by' => $approvedBy,
                'actual_team_id' => $actualTeam->id,
            ]);

            // Create user for captain if doesn't exist
            $captainUser = User::where('email', $registration->captain_email)->first();
            if (!$captainUser) {
                $password = Str::random(12);
                $captainUser = User::create([
                    'name' => $registration->captain_name,
                    'email' => $registration->captain_email,
                    'username' => Str::slug($registration->captain_name) . '-' . Str::random(5),
                    'password' => Hash::make($password),
                    'organization_id' => $tournament->organization_id,
                ]);
            }

            // Associate captain with team
            $actualTeam->users()->attach($captainUser->id, ['role' => 'captain']);

            return $actualTeam;
        });
    }

    /**
     * Reject a registration
     */
    public function rejectRegistration(TournamentRegistration $registration, int $rejectedBy, ?string $remarks = null): bool
    {
        if (!$registration->isPending()) {
            return false;
        }

        return $registration->update([
            'status' => 'rejected',
            'processed_at' => now(),
            'processed_by' => $rejectedBy,
            'remarks' => $remarks,
        ]);
    }

    /**
     * Get pending registrations for a tournament
     */
    public function getPendingRegistrations(Tournament $tournament, ?string $type = null)
    {
        $query = $tournament->registrations()->pending()->with(['player', 'processedBy']);

        if ($type) {
            $query->where('type', $type);
        }

        return $query->latest()->get();
    }

    /**
     * Check if registration is open for a tournament
     */
    public function isRegistrationOpen(Tournament $tournament): bool
    {
        $settings = $tournament->settings;

        if (!$settings) {
            return false;
        }

        return $settings->isRegistrationOpen();
    }

    /**
     * Check if player registration is open
     */
    public function isPlayerRegistrationOpen(Tournament $tournament): bool
    {
        $settings = $tournament->settings;

        if (!$settings) {
            return false;
        }

        if ($settings->registration_deadline && $settings->registration_deadline->isPast()) {
            return false;
        }

        return $settings->player_registration_open;
    }

    /**
     * Check if team registration is open
     */
    public function isTeamRegistrationOpen(Tournament $tournament): bool
    {
        $settings = $tournament->settings;

        if (!$settings) {
            return false;
        }

        if ($settings->registration_deadline && $settings->registration_deadline->isPast()) {
            return false;
        }

        return $settings->team_registration_open;
    }
}

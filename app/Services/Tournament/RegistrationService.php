<?php

namespace App\Services\Tournament;

use App\Mail\ApplicationUnderReviewMail;
use App\Mail\NewRegistrationAdminMail;
use App\Mail\PlayerWelcomeMail;
use App\Mail\RegistrationApprovedMail;
use App\Mail\TeamManagerCredentialsMail;
use App\Models\ActualTeam;
use App\Models\BouncedEmail;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;
use App\Notifications\GeneralNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class RegistrationService
{
    /**
     * Send mail only if the recipient has no permanent bounce on record.
     */
    protected function safeMail(string $email, \Illuminate\Mail\Mailable $mailable): void
    {
        if (BouncedEmail::isBounced($email)) {
            Log::warning("Skipped email to bounced address: {$email}", ['mailable' => get_class($mailable)]);
            return;
        }

        Mail::to($email)->send($mailable);
    }

    /**
     * Register a player for a tournament
     */
    public function registerPlayer(Tournament $tournament, array $data): TournamentRegistration
    {
        return DB::transaction(function () use ($tournament, $data) {
            // Create user if not exists
            $user = User::where('email', $data['email'])->first();

            // Holds the plaintext password only when a brand-new account is created,
            // so we can email the player their login credentials.
            $newUserPassword = null;

            if (!$user) {
                $password = Str::random(12);
                $newUserPassword = $password;
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
                    'organization_id' => $tournament->organization_id,
                    'user_id' => $user->id,
                    'name' => $data['name'],
                    'first_name' => $data['first_name'] ?? null,
                    'last_name' => $data['last_name'] ?? null,
                    'date_of_birth' => $data['date_of_birth'] ?? null,
                    'email' => $data['email'],
                    'country' => $data['country'] ?? null,
                    'state' => $data['state'] ?? null,
                    'visa_status' => $data['visa_status'] ?? null,
                    'visa_expiry' => $data['visa_expiry'] ?? null,
                    'employer_name' => $data['employer_name'] ?? null,
                    'employer_address' => $data['employer_address'] ?? null,
                    'employer_position' => $data['employer_position'] ?? null,
                    'available_saturday' => $data['available_saturday'] ?? false,
                    'available_sunday' => $data['available_sunday'] ?? false,
                    'available_weekends' => ($data['available_saturday'] ?? false) || ($data['available_sunday'] ?? false),
                    'played_ys_ipl_s1' => $data['played_ys_ipl_s1'] ?? false,
                    'mobile_country_code' => $data['mobile_country_code'] ?? null,
                    'mobile_national_number' => $data['mobile_national_number'] ?? null,
                    'mobile_number_full' => $data['mobile_number_full'] ?? null,
                    'cricheroes_country_code' => $data['cricheroes_country_code'] ?? null,
                    'cricheroes_national_number' => $data['cricheroes_national_number'] ?? null,
                    'cricheroes_number_full' => $data['cricheroes_number_full'] ?? null,
                    'cricheroes_profile_url' => $data['cricheroes_profile_url'] ?? null,
                    'jersey_name' => $data['jersey_name'] ?? $data['name'],
                    'jersey_number' => $data['jersey_number'] ?? null,
                    'batting_profile_id' => $data['batting_profile_id'] ?? null,
                    'bowling_profile_id' => $data['bowling_profile_id'] ?? null,
                    'player_type_id' => $data['player_type_id'] ?? null,
                    'kit_size_id' => $data['kit_size_id'] ?? null,
                    'tshirt_size' => $data['tshirt_size'] ?? null,
                    'pant_size' => $data['pant_size'] ?? null,
                    'location_id' => $data['location_id'] ?? null,
                    'team_id' => $data['team_id'] ?? null,
                    'team_name_ref' => $data['team_name_ref'] ?? null,
                    'actual_team_id' => $data['actual_team_id'] ?? null,
                    'playing_team_name_ref' => $data['playing_team_name_ref'] ?? null,
                    'batting_mode' => $data['batting_mode'] ?? null,
                    'preferred_batting_positions' => $data['preferred_batting_positions'] ?? null,
                    'is_wicket_keeper' => $data['is_wicket_keeper'] ?? false,
                    'transportation_required' => $data['transportation_required'] ?? false,
                    'no_travel_plan' => $data['no_travel_plan'] ?? false,
                    'travel_date_from' => $data['travel_date_from'] ?? null,
                    'travel_date_to' => $data['travel_date_to'] ?? null,
                    'image_path' => $data['image_path'] ?? null,
                    'total_matches' => $data['total_matches'] ?? 0,
                    'total_runs' => $data['total_runs'] ?? 0,
                    'total_wickets' => $data['total_wickets'] ?? 0,
                    'status' => 'pending',
                ]);
            } else {
                // Update existing player with new data if provided
                $updateData = array_filter([
                    'name' => $data['name'] ?? null,
                    'first_name' => $data['first_name'] ?? null,
                    'last_name' => $data['last_name'] ?? null,
                    'date_of_birth' => $data['date_of_birth'] ?? null,
                    'country' => $data['country'] ?? null,
                    'state' => $data['state'] ?? null,
                    'visa_status' => $data['visa_status'] ?? null,
                    'visa_expiry' => $data['visa_expiry'] ?? null,
                    'employer_name' => $data['employer_name'] ?? null,
                    'employer_address' => $data['employer_address'] ?? null,
                    'employer_position' => $data['employer_position'] ?? null,
                    'mobile_country_code' => $data['mobile_country_code'] ?? null,
                    'mobile_national_number' => $data['mobile_national_number'] ?? null,
                    'mobile_number_full' => $data['mobile_number_full'] ?? null,
                    'cricheroes_country_code' => $data['cricheroes_country_code'] ?? null,
                    'cricheroes_national_number' => $data['cricheroes_national_number'] ?? null,
                    'cricheroes_number_full' => $data['cricheroes_number_full'] ?? null,
                    'cricheroes_profile_url' => $data['cricheroes_profile_url'] ?? null,
                    'jersey_name' => $data['jersey_name'] ?? null,
                    'jersey_number' => $data['jersey_number'] ?? null,
                    'batting_profile_id' => $data['batting_profile_id'] ?? null,
                    'bowling_profile_id' => $data['bowling_profile_id'] ?? null,
                    'player_type_id' => $data['player_type_id'] ?? null,
                    'kit_size_id' => $data['kit_size_id'] ?? null,
                    'tshirt_size' => $data['tshirt_size'] ?? null,
                    'pant_size' => $data['pant_size'] ?? null,
                    'location_id' => $data['location_id'] ?? null,
                    'team_id' => $data['team_id'] ?? null,
                    'team_name_ref' => $data['team_name_ref'] ?? null,
                    'actual_team_id' => $data['actual_team_id'] ?? null,
                    'playing_team_name_ref' => $data['playing_team_name_ref'] ?? null,
                    'batting_mode' => $data['batting_mode'] ?? null,
                    'preferred_batting_positions' => $data['preferred_batting_positions'] ?? null,
                    'is_wicket_keeper' => $data['is_wicket_keeper'] ?? null,
                    'transportation_required' => $data['transportation_required'] ?? null,
                    'no_travel_plan' => $data['no_travel_plan'] ?? null,
                    'travel_date_from' => $data['travel_date_from'] ?? null,
                    'travel_date_to' => $data['travel_date_to'] ?? null,
                    'image_path' => $data['image_path'] ?? null,
                    'total_matches' => $data['total_matches'] ?? null,
                    'total_runs' => $data['total_runs'] ?? null,
                    'total_wickets' => $data['total_wickets'] ?? null,
                ], fn($value) => $value !== null);

                // Booleans set explicitly (array_filter would drop a false value).
                if (array_key_exists('available_saturday', $data) || array_key_exists('available_sunday', $data)) {
                    $sat = (bool) ($data['available_saturday'] ?? false);
                    $sun = (bool) ($data['available_sunday'] ?? false);
                    $updateData['available_saturday'] = $sat;
                    $updateData['available_sunday'] = $sun;
                    $updateData['available_weekends'] = $sat || $sun;
                }
                if (array_key_exists('played_ys_ipl_s1', $data)) {
                    $updateData['played_ys_ipl_s1'] = (bool) $data['played_ys_ipl_s1'];
                }

                if (!empty($updateData)) {
                    $player->update($updateData);
                }
            }

            // Create registration
            $registration = TournamentRegistration::create([
                'tournament_id' => $tournament->id,
                'organization_id' => $tournament->organization_id,
                'type' => 'player',
                'player_id' => $player->id,
                'status' => 'pending',
                // Digitally-signed consent (typed name + timestamp + IP + T&C snapshot)
                'consent_name' => $data['consent_name'] ?? null,
                'consent_signed_at' => !empty($data['consent_name']) ? now() : null,
                'consent_ip' => $data['consent_ip'] ?? null,
                'consent_snapshot' => $data['consent_snapshot'] ?? null,
                // Answers to tournament custom fields
                'custom_field_values' => $data['custom_field_values'] ?? null,
            ]);

            // Send admin notification
            $this->notifyAdminOfNewRegistration($tournament, $registration, 'player');

            // Tell the applicant their application is in the queue / under review.
            $this->sendApplicationUnderReviewEmail($tournament, $registration, $data['email'] ?? null, $data['name'] ?? 'Player');

            // Email login credentials only when a new account was just created.
            if ($newUserPassword) {
                try {
                    $this->safeMail($user->email, new \App\Mail\PlayerCredentialsMail($user, $newUserPassword, $tournament));
                } catch (\Throwable $e) {
                    Log::error('Failed to send player credentials email: ' . $e->getMessage());
                }
            }

            // In-app notification for admins
            $this->notifyAdminsInApp(
                "New player registration: {$data['name']} for {$tournament->name}",
                route('admin.tournaments.registrations.show', [$tournament, $registration]),
                'player'
            );

            return $registration;
        });
    }

    /**
     * Register a team for a tournament
     */
    public function registerTeam(Tournament $tournament, array $data): TournamentRegistration
    {
        $logoPath = null;
        if (!empty($data['team_logo_path'])) {
            $logoPath = $data['team_logo_path'];
        } elseif (isset($data['team_logo']) && $data['team_logo']) {
            $logoPath = $data['team_logo']->store('team_logos', 'public');
        }

        $registration = TournamentRegistration::create([
            'tournament_id' => $tournament->id,
            'organization_id' => $tournament->organization_id,
            'type' => 'team',
            'team_name' => $data['team_name'],
            'team_short_name' => $data['team_short_name'] ?? null,
            'team_logo' => $logoPath,
            'captain_name' => $data['captain_name'],
            'captain_email' => $data['captain_email'],
            'captain_phone' => $data['captain_phone'],
            'vice_captain_name' => $data['vice_captain_name'] ?? null,
            'vice_captain_email' => $data['vice_captain_email'] ?? null,
            'vice_captain_phone' => $data['vice_captain_phone'] ?? null,
            'team_description' => $data['team_description'] ?? null,
            'status' => 'pending',
            // Digitally-signed consent
            'consent_name' => $data['consent_name'] ?? null,
            'consent_signed_at' => !empty($data['consent_name']) ? now() : null,
            'consent_ip' => $data['consent_ip'] ?? null,
            'consent_snapshot' => $data['consent_snapshot'] ?? null,
            'custom_field_values' => $data['custom_field_values'] ?? null,
        ]);

        // Send admin notification
        $this->notifyAdminOfNewRegistration($tournament, $registration, 'team');

        // Tell the captain their team application is in the queue / under review.
        $this->sendApplicationUnderReviewEmail($tournament, $registration, $data['captain_email'] ?? null, $data['captain_name'] ?? 'Captain');

        // In-app notification for admins
        $this->notifyAdminsInApp(
            "New team registration: {$data['team_name']} for {$tournament->name}",
            route('admin.tournaments.registrations.show', [$tournament, $registration]),
            'team'
        );

        return $registration;
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

            // Mark the player approved and give their user the Player role.
            $player = $registration->player;
            if ($player) {
                $player->update(['status' => 'approved', 'approved_by' => $approvedBy]);

                if ($player->user) {
                    $role = \App\Models\Role::firstOrCreate(['name' => 'player']);
                    if (! $player->user->hasRole('player')) {
                        $player->user->assignRole($role);
                    }
                }
            }

            // Send approval email to player
            $this->sendApprovalEmail($registration);

            // Send the greeting / invite card (generated poster attached).
            $this->sendGreetingCard($registration);

            return true;
        });
    }

    /**
     * Approve a team registration and create ActualTeam
     */
    public function approveTeamRegistration(TournamentRegistration $registration, int $approvedBy, ?int $captainUserId = null): ?ActualTeam
    {
        if (!$registration->isTeamRegistration() || !$registration->isPending()) {
            return null;
        }

        return DB::transaction(function () use ($registration, $approvedBy, $captainUserId) {
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

            if ($captainUserId) {
                // Use the selected registered player as captain
                $actualTeam->users()->attach($captainUserId, ['role' => 'captain']);
            }

            // Create/find the Manager user from captain_email
            {
                $managerUser = User::where('email', $registration->captain_email)->first();
                $isNewManager = false;
                $managerPassword = null;

                if (!$managerUser) {
                    $isNewManager = true;
                    $managerPassword = Str::random(12);
                    $managerUser = User::create([
                        'name' => $registration->captain_name,
                        'email' => $registration->captain_email,
                        'username' => Str::slug($registration->captain_name) . '-' . Str::random(5),
                        'password' => Hash::make($managerPassword),
                        'organization_id' => $tournament->organization_id,
                    ]);
                }

                // Assign Team Manager Spatie role (controls menu/page access)
                if (!$managerUser->hasRole('Team Manager')) {
                    $managerUser->assignRole('Team Manager');
                }

                // Determine pivot role: if owner email same as manager email, upgrade to Owner
                $managerPivotRole = 'Manager';
                if ($registration->vice_captain_email && strtolower($registration->vice_captain_email) === strtolower($registration->captain_email)) {
                    $managerPivotRole = 'Owner';
                }

                if (!$actualTeam->users()->where('user_id', $managerUser->id)->exists()) {
                    $actualTeam->users()->attach($managerUser->id, ['role' => $managerPivotRole]);
                }

                // Send credentials (new user) or login notification (existing user)
                $this->sendTeamManagerCredentials($managerUser, $managerPassword, $tournament, $actualTeam, 'Team Manager');
            }

            // Create/find the Owner user from vice_captain_email (if provided and different from manager)
            if ($registration->vice_captain_email && strtolower($registration->vice_captain_email) !== strtolower($registration->captain_email)) {
                $ownerUser = User::where('email', $registration->vice_captain_email)->first();
                $isNewOwner = false;
                $ownerPassword = null;

                if (!$ownerUser) {
                    $isNewOwner = true;
                    $ownerPassword = Str::random(12);
                    $ownerUser = User::create([
                        'name' => $registration->vice_captain_name ?? 'Team Owner',
                        'email' => $registration->vice_captain_email,
                        'username' => Str::slug($registration->vice_captain_name ?? 'team-owner') . '-' . Str::random(5),
                        'password' => Hash::make($ownerPassword),
                        'organization_id' => $tournament->organization_id,
                    ]);
                }

                if (!$ownerUser->hasRole('Team Manager')) {
                    $ownerUser->assignRole('Team Manager');
                }

                if (!$actualTeam->users()->where('user_id', $ownerUser->id)->exists()) {
                    $actualTeam->users()->attach($ownerUser->id, ['role' => 'Owner']);
                }

                // Send credentials (new user) or login notification (existing user)
                $this->sendTeamManagerCredentials($ownerUser, $ownerPassword, $tournament, $actualTeam, 'Team Owner');
            }

            // Send approval email to captain
            $this->sendApprovalEmail($registration);

            // In-app notification to Manager and Owner
            $dashboardUrl = route('team-manager.dashboard');
            $teamMembers = $actualTeam->users()->get();
            foreach ($teamMembers as $member) {
                $role = $member->pivot->role;
                if (in_array($role, ['Manager', 'Owner'])) {
                    $member->notify(new GeneralNotification(
                        "Your team {$actualTeam->name} has been approved for {$tournament->name}",
                        $dashboardUrl,
                        'team-approved'
                    ));
                }
            }

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

        $updated = $registration->update([
            'status' => 'rejected',
            'processed_at' => now(),
            'processed_by' => $rejectedBy,
            'remarks' => $remarks,
        ]);

        if ($updated) {
            $this->sendStatusEmail($registration->fresh(), 'rejected', $remarks);
        }

        return $updated;
    }

    /**
     * Place a pending registration in the queue (waitlist) and notify the applicant.
     */
    public function queueRegistration(TournamentRegistration $registration, int $processedBy, ?string $remarks = null): bool
    {
        if (!$registration->isPending()) {
            return false;
        }

        $updated = $registration->update([
            'status' => 'queued',
            'processed_at' => now(),
            'processed_by' => $processedBy,
            'remarks' => $remarks,
        ]);

        if ($updated) {
            $this->sendStatusEmail($registration->fresh(), 'queued', $remarks);
        }

        return $updated;
    }

    /**
     * Email the applicant a status update (rejected / queued). Best-effort — a mail
     * failure never blocks the status change.
     */
    protected function sendStatusEmail(TournamentRegistration $registration, string $status, ?string $remarks = null): void
    {
        $tournament = $registration->tournament;

        if ($registration->isTeamRegistration()) {
            $email = $registration->captain_email;
            $name = $registration->captain_name;
        } else {
            $email = $registration->player?->email;
            $name = $registration->player?->name;
        }

        if (! $email) {
            return;
        }

        try {
            $this->safeMail($email, new \App\Mail\RegistrationStatusMail($tournament, $registration, $status, $name, $remarks));
        } catch (\Throwable $e) {
            Log::error('Failed to send registration status email: ' . $e->getMessage());
        }
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
        // If tournament status is 'registration', allow registration
        if ($tournament->status === 'registration') {
            return true;
        }

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

        // Tournament-level status gate: if not 'open', all registration is blocked
        if ($settings && ($settings->tournament_status ?? 'open') !== 'open') {
            return false;
        }

        // Per-type registration status: if set and not 'open', registration is closed
        if ($settings && ($settings->player_registration_status ?? null)) {
            if ($settings->player_registration_status !== 'open') {
                return false;
            }
        } elseif ($settings && !$settings->player_registration_open) {
            // Fallback to boolean toggle for backward compatibility
            if ($tournament->status !== 'registration') {
                return false;
            }
        }

        // Check deadline
        if ($settings && $settings->registration_deadline && $settings->registration_deadline->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Check if team registration is open
     */
    public function isTeamRegistrationOpen(Tournament $tournament): bool
    {
        $settings = $tournament->settings;

        // Tournament-level status gate: if not 'open', all registration is blocked
        if ($settings && ($settings->tournament_status ?? 'open') !== 'open') {
            return false;
        }

        // Per-type registration status: if set and not 'open', registration is closed
        if ($settings && ($settings->team_registration_status ?? null)) {
            if ($settings->team_registration_status !== 'open') {
                return false;
            }
        } elseif ($settings && !$settings->team_registration_open) {
            // Fallback to boolean toggle for backward compatibility
            if ($tournament->status !== 'registration') {
                return false;
            }
        }

        // Check deadline
        if ($settings && $settings->registration_deadline && $settings->registration_deadline->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Send in-app notification to all Superadmin and Admin users
     */
    protected function notifyAdminsInApp(string $message, ?string $page, string $icon = 'info'): void
    {
        try {
            $admins = User::role(['Superadmin', 'Admin'])->get();
            Notification::send($admins, new GeneralNotification($message, $page, $icon));
        } catch (\Exception $e) {
            Log::error('Failed to send in-app admin notification: ' . $e->getMessage());
        }
    }

    /**
     * Send admin notification for new registration
     */
    protected function notifyAdminOfNewRegistration(Tournament $tournament, TournamentRegistration $registration, string $type): void
    {
        // Get admin email from tournament settings or organization
        $adminEmail = $tournament->settings?->contact_email
            ?? $tournament->organization?->email
            ?? config('mail.admin_email');

        if ($adminEmail) {
            try {
                Mail::to($adminEmail)->send(new NewRegistrationAdminMail($tournament, $registration, $type));
            } catch (\Exception $e) {
                Log::error('Failed to send admin notification for new registration: ' . $e->getMessage());
            }
        }
    }

    /**
     * Send team manager credentials email
     */
    protected function sendTeamManagerCredentials(User $user, ?string $password, Tournament $tournament, ActualTeam $team, string $roleName = 'Team Manager'): void
    {
        try {
            $this->safeMail($user->email, new TeamManagerCredentialsMail($user, $password, $tournament, $team, $roleName));
        } catch (\Exception $e) {
            Log::error('Failed to send team manager credentials: ' . $e->getMessage());
        }
    }

    /**
     * Send approval email to registrant
     */
    protected function sendApprovalEmail(TournamentRegistration $registration): void
    {
        $tournament = $registration->tournament;
        $email = null;

        if ($registration->type === 'player' && $registration->player) {
            $email = $registration->player->email;
        } elseif ($registration->type === 'team') {
            $email = $registration->captain_email;
        }

        if ($email) {
            try {
                $this->safeMail($email, new RegistrationApprovedMail($tournament, $registration));
            } catch (\Exception $e) {
                Log::error('Failed to send approval email: ' . $e->getMessage());
            }
        }
    }

    /**
     * Notify an applicant on submission that their application is queued / under review.
     */
    protected function sendApplicationUnderReviewEmail(Tournament $tournament, TournamentRegistration $registration, ?string $email, string $name): void
    {
        if (! $email) {
            return;
        }

        try {
            $this->safeMail($email, new ApplicationUnderReviewMail($tournament, $registration, $name));
        } catch (\Throwable $e) {
            Log::error('Failed to send under-review email: ' . $e->getMessage());
        }
    }

    /**
     * Send the greeting / welcome card (with generated poster) on player approval.
     * Failures are logged, never block approval.
     */
    protected function sendGreetingCard(TournamentRegistration $registration): void
    {
        try {
            // Respect the tournament's auto_send_welcome_cards setting.
            // When auto mode is off, the card won't be sent automatically on approval.
            app(\App\Services\Notification\TournamentNotificationService::class)
                ->sendWelcomeCard($registration);
        } catch (\Throwable $e) {
            // Never let card generation (TypeError/GD/etc.) break the approval.
            Log::error('Failed to send greeting card: ' . $e->getMessage());
        }
    }
}

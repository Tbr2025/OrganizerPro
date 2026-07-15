<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\BattingProfile;
use App\Models\BowlingProfile;
use App\Models\ImageTemplate;
use App\Models\KitSize;
use App\Models\PlayerLocation;
use App\Models\PlayerType;
use App\Models\Team;
use App\Models\TournamentRegistration;
use App\Models\User;
use App\Notifications\PlayerUpdatedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class PlayerProfileController extends Controller
{
    // public function edit()
    // {
    //     $user = Auth::user();

    //     // Optional: enforce role check in controller for extra security
    //     if (!$user->hasRole('Player')) {
    //         abort(403, 'Unauthorized access');
    //     }

    //     // Get the related player model (assuming a user hasOne player)
    //     $player = $user->player;

    //     if (!$player) {
    //         abort(404, 'Player profile not found');
    //     }

    //     return view('backend.pages.profileplayers.edit', [
    //         'player' => $player,
    //         'teams' => Team::all(),
    //         'kitSizes' => KitSize::all(),
    //         'battingProfiles' => BattingProfile::all(),
    //         'bowlingProfiles' => BowlingProfile::all(),
    //         'playerTypes' => PlayerType::all(),
    //         'breadcrumbs' => [
    //             'title' => __('Edit Player'),
    //             'items' => [
    //                 ['label' => __('Players'), 'url' => route('admin.players.index')],
    //             ],
    //         ],
    //     ]);
    // }


    public function edit()
    {
        $user = Auth::user();

        // Get the related player model
        $player = $user->player;

        if (!$player) {
            abort(404, 'Player profile not found.');
        }

        // Allow Player, Team Manager, and Team Owner roles (managers register as players too)
        if (!$user->hasAnyRole(['Player', 'Team Manager', 'Team Owner'])) {
            abort(403, 'Unauthorized access.');
        }

        // --- MODIFICATION START ---
        // Create an array to hold the verification status of each field.
        // The view will use this to disable inputs that are already verified.
        $verifiedFields = [
            'name' => (bool) $player->verified_name,
            'email' => (bool) $player->verified_email,
            'mobile_number_full' => (bool) $player->verified_mobile_number_full,
            'cricheroes_number_full' => (bool) $player->verified_cricheroes_number_full,
            'cricheroes_profile_url' => (bool) $player->verified_cricheroes_profile_url,
            'jersey_name' => (bool) $player->verified_jersey_name,
            'jersey_number' => (bool) $player->verified_jersey_number,
            'kit_size_id' => (bool) $player->verified_kit_size_id,
            'batting_profile_id' => (bool) $player->verified_batting_profile_id,
            'bowling_profile_id' => (bool) $player->verified_bowling_profile_id,
            'player_type_id' => (bool) $player->verified_player_type_id,
            'team_id' => (bool) $player->verified_team_id,
            'team_name_ref' => (bool) $player->verified_team_name_ref,
            'is_wicket_keeper' => (bool) $player->verified_is_wicket_keeper,
            'transportation_required' => (bool) $player->verified_transportation_required,
            'no_travel_plan' => (bool) $player->verified_no_travel_plan,
            'travel_date_from' => (bool) $player->verified_travel_date_from,
            'travel_date_to' => (bool) $player->verified_travel_date_to,
            'location_id' => (bool) $player->verified_location_id,
            'total_matches' => (bool) $player->verified_total_matches,
            'total_runs' => (bool) $player->verified_total_runs,
            'total_wickets' => (bool) $player->verified_total_wickets,
            'image_path' => (bool) $player->verified_image_path,
        ];
        // --- MODIFICATION END ---

        // The tournaments this player registered for — edits are scoped to one of
        // them and approved by that tournament's admin.
        $registrations = $player->registrations()->with('tournament')->latest()->get();
        $selectedRegistration = $registrations->firstWhere('id', (int) request('registration_id'))
            ?? $registrations->first();

        // Once a registration is approved the player can no longer edit their
        // details for it (contact info + password remain editable via Account).
        $isLocked = $selectedRegistration && $selectedRegistration->isApproved();

        $settings = $selectedRegistration?->tournament?->settings;

        // Fields the admin has verified for the selected registration are locked
        // for editing (single source of truth, matching the admin verify UI).
        $verifiedKeys = (array) ($selectedRegistration?->verified_fields ?? []);

        // Pass all necessary data to the view
        return view('backend.pages.profileplayers.edit', [
            'player' => $player,
            'teams' => Team::all(),
            'templates' => ImageTemplate::all(),
            'locations' => PlayerLocation::all(),
            'kitSizes' => KitSize::all(),
            'battingProfiles' => BattingProfile::all(),
            'bowlingProfiles' => BowlingProfile::all(),
            'playerTypes' => PlayerType::all(),
            'registrations' => $registrations,
            'selectedRegistration' => $selectedRegistration,
            'isLocked' => $isLocked,
            // Sectioned layout (mirrors the admin registration detail).
            'layout' => \App\Helpers\PlayerFormConfig::getFormLayout($settings, true),
            'fieldConfig' => \App\Helpers\PlayerFormConfig::getFieldConfig($settings),
            'lockedFieldKeys' => \App\Helpers\PlayerFormConfig::lockedFields(),
            'verifiedKeys' => $verifiedKeys,
            'pendingChanges' => (array) ($selectedRegistration?->pending_changes ?? []),
            'countries' => config('countries.list', []),
            'visaList' => config('registration.visa_statuses', []),
            'tshirtOptions' => \App\Helpers\PlayerFormConfig::sizeOptions('tshirt_sizes', \App\Helpers\PlayerFormConfig::defaultTshirtSizes()),
            'pantOptions' => \App\Helpers\PlayerFormConfig::sizeOptions('pant_sizes', \App\Helpers\PlayerFormConfig::defaultPantSizes()),
            'actualTeams' => \App\Models\ActualTeam::where('tournament_id', $selectedRegistration?->tournament_id)->orderBy('name')->get(),
            'customFields' => $selectedRegistration?->tournament?->customFields?->where('form', 'player')->where('visible', true) ?? collect(),
            'customValues' => (array) ($selectedRegistration?->custom_field_values ?? []),
            'breadcrumbs' => [
                'title' => __('Edit My Profile'),
            ],
            'verifiedFields' => $verifiedFields,
        ]);
    }


    public function update(Request $request)
    {
        $player = Auth::user()->player;
        abort_if(! $player, 404);

        // Edits are scoped to a tournament the player registered for; that
        // tournament's admin approves the change.
        $registration = TournamentRegistration::where('player_id', $player->id)
            ->where('id', $request->input('registration_id'))
            ->first();
        if (! $registration) {
            return back()->with('error', __('Please choose which tournament this update is for.'))->withInput();
        }

        // Locked once approved — only contact info & password can change (via Account).
        if ($registration->isApproved()) {
            return redirect()->route('profileplayers.edit', ['registration_id' => $registration->id])
                ->with('error', __('Your registration has been accepted, so these details are locked. To change contact details or your password, use Account settings.'));
        }

        // "Other" team choice submits team_id="other" — treat as free text only.
        if ($request->input('team_id') === 'other') {
            $request->merge(['team_id' => null]);
        }

        // Lenient validation — requiredness was enforced at registration; here the
        // player only refines. Locked/verified fields aren't rendered so they never
        // submit (see $present gating below).
        $validated = $request->validate([
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date',
            'country' => 'nullable|string|max:2',
            'state' => 'nullable|string|max:100',
            'mobile_number_full' => 'nullable|string|max:20',
            'cricheroes_number_full' => 'nullable|string|max:20',
            'cricheroes_profile_url' => 'nullable|url|max:500',
            'location_id' => 'nullable|exists:player_locations,id',
            'team_id' => 'nullable|exists:teams,id',
            'team_name_ref' => 'nullable|string|max:100',
            'actual_team_id' => 'nullable|exists:actual_teams,id',
            'visa_status' => 'nullable|in:work_visa,visit_visa',
            'visa_expiry' => 'nullable|date',
            'employer_name' => 'nullable|string|max:255',
            'employer_address' => 'nullable|string|max:500',
            'employer_position' => 'nullable|string|max:255',
            'jersey_name' => 'nullable|string|max:50',
            'jersey_number' => 'nullable|integer|min:0|max:999',
            'tshirt_size' => 'nullable|string|max:50',
            'pant_size' => 'nullable|string|max:50',
            'batting_profile_id' => 'nullable|exists:batting_profiles,id',
            'bowling_profile_id' => 'nullable|exists:bowling_profiles,id',
            'player_type_id' => 'nullable|exists:player_types,id',
            'total_matches' => 'nullable|integer|min:0',
            'total_runs' => 'nullable|integer|min:0',
            'total_wickets' => 'nullable|integer|min:0',
            'travel_date_from' => 'nullable|date',
            'travel_date_to' => 'nullable|date|after_or_equal:travel_date_from',
            'image_path' => 'nullable|string|max:500',
        ]);

        // Booleans / Yes-No radios.
        $validated['is_wicket_keeper'] = $request->boolean('wicket_keeper');
        $validated['transportation_required'] = $request->boolean('need_transportation');
        $validated['no_travel_plan'] = $request->boolean('no_travel_plan');
        $validated['available_saturday'] = $request->boolean('available_saturday');
        $validated['available_sunday'] = $request->boolean('available_sunday');
        $validated['played_ys_ipl_s1'] = $request->boolean('played_ys_ipl_s1');

        // Image: the AJAX upload already stored the file; only treat it as a change
        // when a valid path was submitted (or an explicit clear).
        if (!empty($validated['image_path']) && is_string($validated['image_path'])) {
            if (! Storage::disk('public')->exists($validated['image_path'])) {
                unset($validated['image_path']);
            }
        } elseif ($request->boolean('clear_image')) {
            $validated['image_path'] = null;
        } else {
            unset($validated['image_path']);
        }

        // Only consider columns for fields that were actually rendered & editable
        // (each editable control emits a hidden __present[] marker). This prevents
        // hidden sections / locked fields from being treated as "cleared".
        $present = array_values((array) $request->input('__present', []));
        $pending = [];
        foreach ($present as $field) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }
            $new = $validated[$field];
            $old = $player->{$field};
            if ((string) $old !== (string) $new) {
                $pending[$field] = $new;
            }
        }

        if (empty($pending)) {
            return redirect()->route('profileplayers.edit', ['registration_id' => $registration->id])
                ->with('info', 'No changes detected to submit.');
        }

        // Queue the changes for approval (merge with any already-pending set).
        $registration->update([
            'pending_changes' => array_merge((array) $registration->pending_changes, $pending),
            'pending_changes_submitted_at' => now(),
        ]);

        $reviewUrl = route('admin.tournaments.registrations.show', [$registration->tournament_id, $registration->id]);

        // In-app notification for Superadmin & Admin.
        $notifyUsers = User::role(['Superadmin', 'Admin'])->get();
        foreach ($notifyUsers as $notifyUser) {
            $notifyUser->notify(new PlayerUpdatedNotification($player, auth()->user(), $reviewUrl));
        }

        // Email the organizer/admins the requested changes (field → current → requested).
        $changeList = [];
        foreach ($pending as $col => $newVal) {
            $changeList[] = [
                'label' => $this->humanColumn($col),
                'old' => $this->readableChangeValue($col, $player->{$col}),
                'new' => $this->readableChangeValue($col, $newVal),
            ];
        }
        $tournament = $registration->tournament;
        $recipients = collect([
            $tournament?->settings?->contact_email,
            $tournament?->organization?->email,
        ])->merge($notifyUsers->pluck('email'))->filter()->unique()->values();

        foreach ($recipients as $to) {
            try {
                Mail::to($to)->send(new \App\Mail\PlayerChangeRequestMail($tournament, $registration, $changeList, $reviewUrl));
            } catch (\Throwable $e) {
                Log::error('Failed to email player change request: ' . $e->getMessage());
            }
        }

        return redirect()->route('profileplayers.edit', ['registration_id' => $registration->id])
            ->with('success', 'Your changes were submitted and are awaiting admin approval. They will reflect once approved.');
    }

    /** Human label for a Player column (for the change-request email). */
    protected function humanColumn(string $col): string
    {
        return [
            'mobile_number_full' => 'Mobile Number',
            'cricheroes_number_full' => 'CricHeroes Number',
            'cricheroes_profile_url' => 'CricHeroes Profile URL',
            'location_id' => 'Location',
            'team_name_ref' => 'Registration Team',
            'actual_team_id' => 'Playing Team',
            'date_of_birth' => 'Date of Birth',
            'visa_status' => 'Visa Status',
            'visa_expiry' => 'Visa Validity',
            'tshirt_size' => 'T-Shirt Size',
            'pant_size' => 'Pant Size',
            'batting_profile_id' => 'Batting Profile',
            'bowling_profile_id' => 'Bowling Profile',
            'player_type_id' => 'Player Type',
            'is_wicket_keeper' => 'Wicket Keeper',
            'transportation_required' => 'Transportation Required',
            'no_travel_plan' => 'No Travel Plan',
            'available_saturday' => 'Available Saturday',
            'available_sunday' => 'Available Sunday',
            'played_ys_ipl_s1' => 'Played YS IPL Season 1',
            'image_path' => 'Profile Photo',
        ][$col] ?? ucwords(str_replace('_', ' ', $col));
    }

    /** Resolve a Player column value to a human-readable string for the email. */
    protected function readableChangeValue(string $col, $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        return match ($col) {
            'is_wicket_keeper', 'transportation_required', 'no_travel_plan',
            'available_saturday', 'available_sunday', 'played_ys_ipl_s1' => $value ? 'Yes' : 'No',
            'country' => config('countries.list.' . $value, $value),
            'visa_status' => config('registration.visa_statuses.' . $value, $value),
            'location_id' => optional(\App\Models\PlayerLocation::find($value))->name ?? (string) $value,
            'actual_team_id' => optional(\App\Models\ActualTeam::find($value))->name ?? (string) $value,
            'batting_profile_id' => optional(\App\Models\BattingProfile::find($value))->name ?? optional(\App\Models\BattingProfile::find($value))->style ?? (string) $value,
            'bowling_profile_id' => optional(\App\Models\BowlingProfile::find($value))->name ?? optional(\App\Models\BowlingProfile::find($value))->style ?? (string) $value,
            'player_type_id' => optional(\App\Models\PlayerType::find($value))->name ?? optional(\App\Models\PlayerType::find($value))->type ?? (string) $value,
            'image_path' => 'Photo updated',
            default => (string) $value,
        };
    }
}

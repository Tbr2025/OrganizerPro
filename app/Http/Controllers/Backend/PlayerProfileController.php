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
use App\Models\User;
use App\Notifications\PlayerUpdatedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
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

        // Enforce role check for extra security
        if (!$user->hasRole('Player')) {
            abort(403, 'Unauthorized access. Only players can edit their profile.');
        }

        // Get the related player model
        $player = $user->player;

        if (!$player) {
            abort(404, 'Player profile not found for the current user.');
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

        // Pass all necessary data to the view
        return view('backend.pages.profileplayers.edit', [
            'player' => $player,
            'teams' => Team::all(),
            'templates' => ImageTemplate::all(),
            'locations' => PlayerLocation::all(), // Added this missing model
            'kitSizes' => KitSize::all(),
            'battingProfiles' => BattingProfile::all(),
            'bowlingProfiles' => BowlingProfile::all(),
            'playerTypes' => PlayerType::all(),
            'breadcrumbs' => [
                'title' => __('Edit My Profile'), // Adjusted title for context

            ],
            'verifiedFields' => $verifiedFields, // Pass the new array to the view
        ]);
    }


    public function update(Request $request)
    {
        $player = Auth::user()->player;



        // Map of field => is_verified (e.g. DB: verified_name = true)
        $verifiedFields = [
            'name' => $player->verified_name,
            'mobile_number_full' => $player->verified_mobile_number_full,
            'jersey_name' => $player->verified_jersey_name,
            'jersey_number' => $player->verified_jersey_number,
            'cricheroes_number_full' => $player->verified_cricheroes_number_full,
            'cricheroes_profile_url' => $player->verified_cricheroes_profile_url,
            'kit_size_id' => $player->verified_kit_size_id,
            'team_name_ref' => $player->verified_team_name_ref,
            'location_id' => $player->verified_location_id,
            'total_matches' => $player->verified_total_matches,
            'total_runs' => $player->verified_total_runs,
            'total_wickets' => $player->verified_total_wickets,
            'travel_date_from' => $player->verified_travel_date_from,
            'travel_date_to' => $player->verified_travel_date_to,
            'no_travel_plan' => $player->verified_no_travel_plan,
        ];

        $rules = [];

        if (!($verifiedFields['name'] ?? false)) {
            $rules['name'] = 'required|string|max:100';
        }

        if (!($verifiedFields['mobile_number_full'] ?? false)) {

            $rules['mobile_number_full'] = [
                'required',
                'numeric',
                'digits_between:7,15',
                Rule::unique('players', 'mobile_number_full')->ignore($player->id),
            ];
        }

        if (!($verifiedFields['jersey_name'] ?? false)) {
            $rules['jersey_name'] = 'required|string|max:50';
        }

        if (!($verifiedFields['cricheroes_number_full'] ?? false)) {
            $rules['cricheroes_number_full'] = [
                'nullable',
                'numeric',
                'digits_between:7,15',
                Rule::unique('players', 'cricheroes_number_full')
                    ->whereNotNull('cricheroes_number_full')
                    ->ignore($player->id),
            ];
        }

        if (!($verifiedFields['cricheroes_profile_url'] ?? false)) {
            $rules['cricheroes_profile_url'] = 'nullable|url|max:500';
        }

        if (!($verifiedFields['jersey_number'] ?? false)) {
            $rules['jersey_number'] = 'nullable|integer|min:0|max:999';
        }

        // Always-validated fields (unless verified)
        if (!($verifiedFields['team_name_ref'] ?? false)) {
            $rules['team_name_ref'] = 'nullable|string|max:100';
        }

        if (!($verifiedFields['location_id'] ?? false)) {
            $rules['location_id'] = 'nullable|exists:player_locations,id';
        }

        if (!($verifiedFields['total_matches'] ?? false)) {
            $rules['total_matches'] = 'nullable|integer|min:0';
        }

        if (!($verifiedFields['total_runs'] ?? false)) {
            $rules['total_runs'] = 'nullable|integer|min:0';
        }

        if (!($verifiedFields['total_wickets'] ?? false)) {
            $rules['total_wickets'] = 'nullable|integer|min:0';
        }

        if (!($verifiedFields['travel_date_from'] ?? false)) {
            $rules['travel_date_from'] = 'nullable|date';
        }

        if (!($verifiedFields['travel_date_to'] ?? false)) {
            $rules['travel_date_to'] = 'nullable|date|after_or_equal:travel_date_from';
        }

        if (!($verifiedFields['no_travel_plan'] ?? false)) {
            $rules['no_travel_plan'] = 'nullable';
        }
        if (!($verifiedFields['kit_size_id'] ?? false)) {
            $rules['kit_size_id'] = 'required|exists:kit_sizes,id';
        }

        if (!($player->verified_batting_profile_id ?? false)) {
            $rules['batting_profile_id'] = 'required|exists:batting_profiles,id';
        }

        if (!($player->verified_bowling_profile_id ?? false)) {
            $rules['bowling_profile_id'] = 'required|exists:bowling_profiles,id';
        }

        if (!($player->verified_player_type_id ?? false)) {
            $rules['player_type_id'] = 'required|exists:player_types,id';
        }

        $rules['image_path'] = 'nullable|string|max:500';
        $rules['is_wicket_keeper'] = 'nullable';
        $rules['transportation_required'] = 'nullable';

        $validated = $request->validate($rules, [
            'mobile_number_full.unique' => 'This mobile number is already registered.',
            'cricheroes_number_full.unique' => 'This CricHeroes number is already registered.',
            'image_path.mimes' => 'The profile image must be a PNG or JPG file.',
            'image_path.max' => 'The profile image size cannot be more than 6MB.',
        ]);


        // Image path comes pre-processed from AJAX upload (string path)
        if (!empty($validated['image_path']) && is_string($validated['image_path'])) {
            if (Storage::disk('public')->exists($validated['image_path'])) {
                // Delete old image if different
                if ($player->image_path && $player->image_path !== $validated['image_path']
                    && Storage::disk('public')->exists($player->image_path)) {
                    Storage::disk('public')->delete($player->image_path);
                }
                $player->image_path = $validated['image_path'];
            } else {
                unset($validated['image_path']);
            }
        }

        // Clear image if requested
        if ($request->boolean('clear_image')) {
            if ($player->image_path && Storage::disk('public')->exists($player->image_path)) {
                Storage::disk('public')->delete($player->image_path);
            }
            $validated['image_path'] = null;
        }

        $validated['is_wicket_keeper'] = $request->boolean('wicket_keeper');
        $validated['transportation_required'] = $request->boolean('need_transportation');
        $validated['no_travel_plan'] = $request->boolean('no_travel_plan');

        $player->update($validated);
        // Only notify Superadmin and Admin
        $notifyUsers = User::role(['Superadmin', 'Admin'])->get();

        foreach ($notifyUsers as $notifyUser) {
            $notifyUser->notify(
                new PlayerUpdatedNotification(
                    $player,
                    auth()->user(),
                    route('admin.players.edit', $player->id)
                )
            );
        }
        return redirect()->route('profileplayers.edit')->with('success', 'Profile updated.');
    }
}

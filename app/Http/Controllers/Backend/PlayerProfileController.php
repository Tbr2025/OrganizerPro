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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

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
            'jersey_name' => (bool) $player->verified_jersey_name,
            'kit_size_id' => (bool) $player->verified_kit_size_id,
            'batting_profile_id' => (bool) $player->verified_batting_profile_id,
            'bowling_profile_id' => (bool) $player->verified_bowling_profile_id,
            'player_type_id' => (bool) $player->verified_player_type_id,
            'team_id' => (bool) $player->verified_team_id,
            'is_wicket_keeper' => (bool) $player->verified_is_wicket_keeper,
            'transportation_required' => (bool) $player->verified_transportation_required,
            'no_travel_plan' => (bool) $player->verified_no_travel_plan,
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

        // Construct full phone numbers
        $mobileFull = preg_replace('/\D+/', '', (string) $request->input('mobile_country_code') . (string) $request->input('mobile_national_number'));
        $cricheroesFull = null;

        if ($request->filled(['cricheroes_country_code', 'cricheroes_national_number'])) {
            $cricheroesFull = preg_replace('/\D+/', '', (string) $request->input('cricheroes_country_code') . (string) $request->input('cricheroes_national_number'));
        }

        $request->merge([
            'mobile_number_full' => $mobileFull,
            'cricheroes_number_full' => $cricheroesFull,
        ]);

        // Map of field => is_verified (e.g. DB: verified_name = true)
        $verifiedFields = [
            'name' => $player->verified_name,
            'mobile_number_full' => $player->verified_mobile_number_full,
            'jersey_name' => $player->verified_jersey_name,
            'cricheroes_number_full' => $player->verified_cricheroes_number_full,
            'kit_size_id' => $player->verified_kit_size_id,
            // Extend for more fields if needed
        ];

        $rules = [];

        if (!($verifiedFields['name'] ?? false)) {
            $rules['name'] = 'required|string|max:100';
        }

        if (!($verifiedFields['mobile_number_full'] ?? false)) {
            $rules['mobile_country_code'] = 'required|string|max:10';
            $rules['mobile_national_number'] = 'required|string|max:20';
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
            $rules['cricheroes_country_code'] = 'nullable|string|max:10';
            $rules['cricheroes_national_number'] = 'nullable|string|max:20';
            $rules['cricheroes_number_full'] = [
                'nullable',
                'numeric',
                'digits_between:7,15',
                Rule::unique('players', 'cricheroes_number_full')
                    ->whereNotNull('cricheroes_number_full')
                    ->ignore($player->id),
            ];
        }

        // Always-validated fields
        $rules['team_id'] = 'nullable|exists:teams,id';
        $rules['team_name_ref'] = 'nullable|string|max:100';
        $rules['kit_size_id'] = 'required|exists:kit_sizes,id';
        $rules['batting_profile_id'] = 'required|exists:batting_profiles,id';
        $rules['bowling_profile_id'] = 'required|exists:bowling_profiles,id';
        $rules['player_type_id'] = 'required|exists:player_types,id';
        $rules['image_path'] = 'nullable|image|mimes:png,jpg,jpeg|max:6144';
        $rules['wicket_keeper'] = 'nullable';
        $rules['need_transportation'] = 'nullable';

        $validated = $request->validate($rules, [
            'mobile_number_full.unique' => 'This mobile number is already registered.',
            'cricheroes_number_full.unique' => 'This CricHeroes number is already registered.',
            'image_path.mimes' => 'The profile image must be a PNG or JPG file.',
            'image_path.max' => 'The profile image size cannot be more than 6MB.',
        ]);

        // Clear image if requested
        if ($request->boolean('clear_image')) {
            if ($player->image_path && Storage::disk('public')->exists($player->image_path)) {
                Storage::disk('public')->delete($player->image_path);
            }
            $validated['image_path'] = null;
        }

        $validated['is_wicket_keeper'] = $request->boolean('wicket_keeper');
        $validated['transportation_required'] = $request->boolean('need_transportation');

        $player->update($validated);

        return redirect()->route('profileplayers.edit')->with('success', 'Profile updated.');
    }
}

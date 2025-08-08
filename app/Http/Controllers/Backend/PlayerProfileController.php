<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\BattingProfile;
use App\Models\BowlingProfile;
use App\Models\KitSize;
use App\Models\PlayerType;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PlayerProfileController extends Controller
{
    public function edit()
    {
        $user = Auth::user();

        // Optional: enforce role check in controller for extra security
        if (!$user->hasRole('Player')) {
            abort(403, 'Unauthorized access');
        }

        // Get the related player model (assuming a user hasOne player)
        $player = $user->player;

        if (!$player) {
            abort(404, 'Player profile not found');
        }

        return view('backend.pages.profileplayers.edit', [
            'player' => $player,
            'teams' => Team::all(),
            'kitSizes' => KitSize::all(),
            'battingProfiles' => BattingProfile::all(),
            'bowlingProfiles' => BowlingProfile::all(),
            'playerTypes' => PlayerType::all(),
            'breadcrumbs' => [
                'title' => __('Edit Player'),
                'items' => [
                    ['label' => __('Players'), 'url' => route('admin.players.index')],
                ],
            ],
        ]);
    }




    public function update(Request $request)
    {

        $player = Auth::user()->player; // Assuming one-to-one relation: User hasOne Player

        // Sanitize and combine phone numbers
        $mobileFull = preg_replace('/\D+/', '', (string)$request->input('mobile_country_code') . (string)$request->input('mobile_national_number'));
        $cricheroesFull = null;

        if ($request->filled(['cricheroes_country_code', 'cricheroes_national_number'])) {
            $cricheroesFull = preg_replace('/\D+/', '', (string)$request->input('cricheroes_country_code') . (string)$request->input('cricheroes_national_number'));
        }

        // Merge for validation
        $request->merge([
            'mobile_number_full' => $mobileFull,
            'cricheroes_number_full' => $cricheroesFull,
        ]);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'mobile_country_code' => 'required|string|max:10',
            'mobile_national_number' => 'required|string|max:20',
            'mobile_number_full' => [
                'required',
                'numeric',
                'digits_between:7,15',
                Rule::unique('players', 'mobile_number_full')->ignore($player->id),
            ],
            'team_id' => 'nullable|exists:teams,id',
            'team_name_ref' => 'nullable|string|max:100',
            'jersey_name' => 'required|string|max:50',
            'kit_size_id' => 'required|exists:kit_sizes,id',
            'batting_profile_id' => 'required|exists:batting_profiles,id',
            'bowling_profile_id' => 'required|exists:bowling_profiles,id',
            'player_type_id' => 'required|exists:player_types,id',

            'cricheroes_country_code' => 'nullable|string|max:10',
            'cricheroes_national_number' => 'nullable|string|max:20',
            'cricheroes_number_full' => [
                'nullable',
                'numeric',
                'digits_between:7,15',
                Rule::unique('players', 'cricheroes_number_full')
                    ->whereNotNull('cricheroes_number_full')
                    ->ignore($player->id),
            ],

            'image_path' => [
                'nullable',
                'image',
                'mimes:png,jpg,jpeg',
                'max:6144',
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->hasFile('image_path')) {
                        $image = getimagesize($request->file('image_path')->getPathname());
                        if (!$image) {
                            return $fail('The uploaded file is not a valid image.');
                        }

                        $width = $image[0];
                        $height = $image[1];
                        $actualRatio = $width / $height;
                        $expectedRatio = 3 / 4;
                        $tolerance = 0.20 * $expectedRatio;

                        if (abs($actualRatio - $expectedRatio) > $tolerance) {
                            $fail('The image must have a 3:4 aspect ratio (e.g., 600X800 or 900x1200).');
                        }
                    }
                },
            ],

            'wicket_keeper' => 'nullable',
            'need_transportation' => 'nullable',
        ], [
            'mobile_number_full.unique' => 'This mobile number is already registered.',
            'cricheroes_number_full.unique' => 'This CricHeroes number is already registered.',
            'image_path.mimes' => 'The profile image must be a PNG file.',
            'image_path.max' => 'The profile image size cannot be more than 6MB.',
        ]);

        // Handle new image upload
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

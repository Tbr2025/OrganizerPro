<?php

namespace App\Http\Controllers;

use App\Models\BattingProfile;
use App\Models\BowlingProfile;
use App\Models\KitSize;
use App\Models\Location;
use App\Models\Player;
use App\Models\PlayerLocation;
use App\Models\PlayerType;
use App\Models\Team;
use App\Models\User;
use App\Notifications\CustomVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PublicPlayerController extends Controller
{
    public function showForm()
    {
        return view('public.player-register', [
            'teams' => Team::all(),
            'locations' => PlayerLocation::all(),
            'kitSizes' => KitSize::all(),
            'battingProfiles' => BattingProfile::all(),
            'bowlingProfiles' => BowlingProfile::all(),
            'playerTypes' => PlayerType::whereIn('type', ['Bowler', 'Batsman', 'All-Rounder', 'Wicket Keeper'])->get(),
        ]);
    }

    public function store(Request $request)
    {
        // Combine mobile and CricHeroes full numbers
        $mobileFull = preg_replace('/\D+/', '', (string)$request->input('mobile_country_code') . (string)$request->input('mobile_national_number'));
        $cricheroesFull = null;

        if ($request->filled(['cricheroes_country_code', 'cricheroes_national_number'])) {
            $cricheroesFull = preg_replace('/\D+/', '', (string)$request->input('cricheroes_country_code') . (string)$request->input('cricheroes_national_number'));
        }

        $request->merge([
            'mobile_number_full' => $mobileFull,
            'cricheroes_number_full' => $cricheroesFull,
        ]);

        $noTravel = $request->boolean('no_travel_plan');

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:players,email|unique:users,email',
            'location_id' => 'required|exists:player_locations,id',
            'total_matches' => 'required|integer|min:0',
            'total_runs' => 'required|integer|min:0',
            'total_wickets' => 'required|integer|min:0',
            'team_name_ref' => 'nullable|string|max:100',
            'mobile_country_code' => 'required|string|max:10',
            'mobile_national_number' => 'required|string|max:20',
            'mobile_number_full' => ['required', 'numeric', 'digits_between:7,15', 'unique:players,mobile_number_full'],
            'team_id' => 'nullable|exists:teams,id',
            'jersey_name' => 'required|string|max:50',
            'kit_size_id' => 'required|exists:kit_sizes,id',
            'batting_profile_id' => 'required|exists:batting_profiles,id',
            'bowling_profile_id' => 'required|exists:bowling_profiles,id',
            'player_type_id' => 'required|exists:player_types,id',

            'accept_availability' => 'accepted',
            'accept_auction_commitment' => 'accepted',
            'no_travel_plan' => 'nullable|boolean',
            'travel_date_from' => $noTravel ? 'nullable' : 'required|date',
            'travel_date_to' => $noTravel ? 'nullable' : 'required|date|after_or_equal:travel_date_from',

            'cricheroes_country_code' => 'required|string|max:10',
            'cricheroes_national_number' => 'nullable|string|max:20',
            'cricheroes_number_full' => [
                'nullable',
                'numeric',
                'digits_between:7,15',
                Rule::unique('players', 'cricheroes_number_full')->whereNotNull('cricheroes_number_full')
            ],

            'image' => [
                'required',
                'image',
                'mimes:png,jpg,jpeg',
                'max:6144',
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->hasFile('image')) {
                        $image = getimagesize($request->file('image')->getPathname());
                        if (!$image) return $fail('The uploaded file is not a valid image.');

                        $actualRatio = $image[0] / $image[1];
                        $expectedRatio = 3 / 4;
                        if (abs($actualRatio - $expectedRatio) > (0.20 * $expectedRatio)) {
                            $fail('The image must have a 3:4 aspect ratio (e.g., 600x800 or 900x1200).');
                        }
                    }
                },
            ],

            'wicket_keeper' => 'nullable|boolean',
            'need_transportation' => 'nullable|boolean',
        ], [
            // 🔁 Custom Error Messages
            'email.unique' => 'The email address has already been used.',
            'mobile_number_full.unique' => 'This mobile number is already registered.',
            'cricheroes_number_full.unique' => 'This CricHeroes number is already registered.',

            'image.required' => 'Profile image is required.',
            'image.mimes' => 'The profile image must be a PNG, JPG, or JPEG file.',
            'image.max' => 'The profile image size cannot be more than 6MB.',

            // 💡 Custom Dropdown Error Messages
            'kit_size_id.required' => 'Please select your Jersey Size.',
            'kit_size_id.exists' => 'The selected Jersey Size is invalid.',

            'batting_profile_id.required' => 'Please select your batting profile.',
            'batting_profile_id.exists' => 'The selected batting profile is invalid.',

            'bowling_profile_id.required' => 'Please select your bowling profile.',
            'bowling_profile_id.exists' => 'The selected bowling profile is invalid.',

            'player_type_id.required' => 'Please select your player type.',
            'player_type_id.exists' => 'The selected player type is invalid.',
            'location_id.required' => 'Please select your location.',
            'location_id.exists' => 'The selected location is invalid.',

        ]);


        // Log::info('--- Background Removal Process Starting ---');
        $finalImagePath = null;

        $imageFile = $validated['image']; // Get the validated file object
        $originalTempName = 'original-' . Str::random(10) . '.' . $imageFile->getClientOriginalExtension();
        $imageFile->storeAs('public/player_images', $originalTempName); // Save temporarily
        $inputPath = storage_path('app/public/player_images/' . $originalTempName);

        // Define paths and command exactly like in the working test
        $outputFilename = 'processed-' . Str::random(10) . '.png';
        $outputPath = storage_path('app/public/player_images/' . $outputFilename);
        $pythonBinary = '/var/www/OrganizerPro/rembg-env/bin/python'; // Absolute path to venv python
        $pythonScript = resource_path('scripts/remove_bg.py');
        $cachePath = storage_path('app/rembg_cache');
        File::ensureDirectoryExists($cachePath); // Ensure cache dir exists

        $command = 'U2NET_HOME=' . escapeshellarg($cachePath) . ' ' .
            escapeshellcmd($pythonBinary) . ' ' .
            escapeshellarg($pythonScript) . ' ' .
            escapeshellarg($inputPath) . ' ' .
            escapeshellarg($outputPath) . ' 2>&1'; // Crucially, capture errors

        Log::info('Executing shell command: ' . $command);
        $shellOutput = shell_exec($command);

        if (!File::exists($outputPath)) {
            Log::error("Background removal FAILED. Shell Output: " . $shellOutput);
            File::delete($inputPath); // Clean up the temp file
            // Return user to form with an error
            return back()->withInput()->withErrors(['image' => 'Could not process the uploaded image. Please try a different one.']);
        }

        try {
            // ✅ Resize by width only (425px), maintain aspect ratio
            $targetWidth = 425;

            // Load source image
            $sourceImage = imagecreatefrompng($outputPath);
            list($sourceWidth, $sourceHeight) = getimagesize($outputPath);

            // Calculate height to maintain aspect ratio
            $aspectRatio = $sourceHeight / $sourceWidth;
            $targetHeight = intval($targetWidth * $aspectRatio);

            // Create blank canvas
            $resizedImage = imagecreatetruecolor($targetWidth, $targetHeight);
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);

            // Resize
            imagecopyresampled(
                $resizedImage,
                $sourceImage,
                0,
                0,
                0,
                0,
                $targetWidth,
                $targetHeight,
                $sourceWidth,
                $sourceHeight
            );

            // Save resized image
            imagepng($resizedImage, $outputPath);

            // Clean up
            imagedestroy($resizedImage);
            imagedestroy($sourceImage);
        } catch (\Throwable $e) {
            Log::error("Resizing failed: " . $e->getMessage());
            return back()->withInput()->withErrors(['image' => 'Image resizing failed.']);
        }


        Log::info("Background removal successful. Output: " . $shellOutput);
        $finalImagePath = 'player_images/' . $outputFilename;
        File::delete($inputPath); // Clean up the temp original file


        // --- 4. Create Database Records (Only after image is processed) ---
        $username = Str::slug(Str::before($validated['email'], '@'), '_');
        if (User::where('username', $username)->exists()) {
            $username .= '_' . Str::random(5);
        }
        $password = Str::random(12);
        $password = Str::random(12);
        $user = User::create([
            'name' => $validated['name'],
            'username' => $username,
            'email' => $validated['email'],
            'password' => Hash::make($password),
            'email_verified_at' => null,
        ]);



        // Build player data
        $player = Player::create(array_merge([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'total_matches' => $validated['total_matches'],
            'total_runs' => $validated['total_runs'],
            'total_wickets' => $validated['total_wickets'],
            'location_id' => $validated['location_id'],
            'mobile_country_code' => $validated['mobile_country_code'],
            'mobile_national_number' => $validated['mobile_national_number'],
            'mobile_number_full' => $validated['mobile_number_full'],
            'player_type_id' => $validated['player_type_id'],
            'image_path' => $finalImagePath,
            'is_wicket_keeper' => $request->boolean('wicket_keeper'),
            'transportation_required' => $request->boolean('need_transportation'),
            'status' => 'pending',
            'user_id' => $user->id,
            'created_by' => null,
            'no_travel_plan' => $noTravel,
            'travel_date_from' => $validated['travel_date_from'] ?? null,
            'travel_date_to' => $validated['travel_date_to'] ?? null,
        ], collect([
            'team_id',
            'team_name_ref',
            'jersey_name',
            'kit_size_id',
            'batting_profile_id',
            'bowling_profile_id',
            'cricheroes_country_code',
            'cricheroes_national_number',
            'cricheroes_number_full'
        ])->mapWithKeys(fn($field) => [$field => $validated[$field] ?? null])->toArray()));

        // // Remove image background via Python script
        // $outputFilename = 'processed-' . Str::random(8) . '.png';
        // $outputPath = storage_path('app/public/player_images/' . $outputFilename);

        // $pythonScript = base_path('resources/scripts/remove_bg.py');
        // $pythonBinary = PHP_OS_FAMILY === 'Windows' ? base_path('venv/Scripts/python.exe') : 'python3';
        // $command = "\"{$pythonBinary}\" \"{$pythonScript}\" \"{$inputPath}\" \"{$outputPath}\"";

        // try {
        //     shell_exec($command);

        //     if (file_exists($outputPath)) {
        //         @unlink($inputPath); // delete original
        //         $player->update(['image_path' => 'player_images/' . $outputFilename]);
        //     } else {
        //         throw new \Exception('Background removal failed.');
        //     }
        // } catch (\Exception $e) {
        //     Log::error("Background removal error: " . $e->getMessage());
        // }

        $user->assignRole('player');
        $user->notify(new CustomVerifyEmail($password));

        return redirect()->to(route('player.register.form', [], false) . '#registration-form')
            ->with('success', 'Thank you for registering! Please check your email to verify your address. Your application is pending review.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\ActualTeam;
use App\Models\BattingProfile;
use App\Models\BowlingProfile;
use App\Models\KitSize;
use App\Models\Player;
use App\Models\PlayerLocation;
use App\Models\PlayerType;
use App\Mail\PlayerJoinRequestMail;
use App\Notifications\GeneralNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublicTeamJoinController extends Controller
{
    public function showForm($inviteCode)
    {
        $team = ActualTeam::where('invite_code', $inviteCode)
            ->with('tournament.settings', 'tournament.organization')
            ->firstOrFail();

        $locations = PlayerLocation::orderBy('name')->get();
        $kitSizes = KitSize::all();
        $battingProfiles = BattingProfile::all();
        $bowlingProfiles = BowlingProfile::all();
        $playerTypes = PlayerType::all();

        return view('public.team.join', compact(
            'team',
            'locations',
            'kitSizes',
            'battingProfiles',
            'bowlingProfiles',
            'playerTypes'
        ));
    }

    public function store($inviteCode, Request $request)
    {
        $team = ActualTeam::where('invite_code', $inviteCode)
            ->with('tournament')
            ->firstOrFail();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:players,email',
            'mobile_number_full' => 'required|string|max:20',
            'cricheroes_number_full' => 'nullable|string|max:20',
            'cricheroes_profile_url' => 'nullable|url|max:500',
            'location_id' => 'nullable|exists:player_locations,id',
            'jersey_name' => 'nullable|string|max:50',
            'jersey_number' => 'nullable|integer|min:0|max:999',
            'kit_size_id' => 'nullable|exists:kit_sizes,id',
            'player_type_id' => 'nullable|exists:player_types,id',
            'batting_profile_id' => 'nullable|exists:batting_profiles,id',
            'bowling_profile_id' => 'nullable|exists:bowling_profiles,id',
            'is_wicket_keeper' => 'nullable|boolean',
            'total_matches' => 'nullable|integer|min:0',
            'total_runs' => 'nullable|integer|min:0',
            'total_wickets' => 'nullable|integer|min:0',
            'transportation_required' => 'nullable|boolean',
            'no_travel_plan' => 'nullable|boolean',
            'travel_date_from' => 'nullable|date',
            'travel_date_to' => 'nullable|date|after_or_equal:travel_date_from',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:6144',
        ]);

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'mobile_number_full' => $validated['mobile_number_full'],
            'cricheroes_number_full' => $validated['cricheroes_number_full'] ?? null,
            'cricheroes_profile_url' => $validated['cricheroes_profile_url'] ?? null,
            'location_id' => $validated['location_id'] ?? null,
            'jersey_name' => $validated['jersey_name'] ?? null,
            'jersey_number' => $validated['jersey_number'] ?? null,
            'kit_size_id' => $validated['kit_size_id'] ?? null,
            'player_type_id' => $validated['player_type_id'] ?? null,
            'batting_profile_id' => $validated['batting_profile_id'] ?? null,
            'bowling_profile_id' => $validated['bowling_profile_id'] ?? null,
            'is_wicket_keeper' => $request->boolean('is_wicket_keeper'),
            'total_matches' => $validated['total_matches'] ?? 0,
            'total_runs' => $validated['total_runs'] ?? 0,
            'total_wickets' => $validated['total_wickets'] ?? 0,
            'transportation_required' => $request->boolean('transportation_required'),
            'no_travel_plan' => $request->boolean('no_travel_plan'),
            'travel_date_from' => $validated['travel_date_from'] ?? null,
            'travel_date_to' => $validated['travel_date_to'] ?? null,
            'actual_team_id' => $team->id,
            'player_mode' => 'normal',
            'status' => 'pending',
        ];

        if ($request->hasFile('image')) {
            $imageFile = $request->file('image');

            // Save original upload
            $originalFilename = 'original-' . Str::random(10) . '.' . $imageFile->getClientOriginalExtension();
            $imageFile->storeAs('public/player_images', $originalFilename);
            $inputPath = storage_path('app/public/player_images/' . $originalFilename);

            // Define output path for background-removed image
            $outputFilename = 'processed-' . Str::random(10) . '.png';
            $outputPath = storage_path('app/public/player_images/' . $outputFilename);

            $pythonScript = resource_path('scripts/remove_bg.py');
            $rembgEnv = base_path('rembg-env/bin/python');
            $pythonBinary = file_exists($rembgEnv) ? $rembgEnv : 'python3';

            if (app()->environment('production')) {
                $cachePath = storage_path('app/rembg_cache');
                File::ensureDirectoryExists($cachePath);

                $command = 'U2NET_HOME=' . escapeshellarg($cachePath) . ' ' .
                    escapeshellcmd($pythonBinary) . ' ' .
                    escapeshellarg($pythonScript) . ' ' .
                    escapeshellarg($inputPath) . ' ' .
                    escapeshellarg($outputPath) . ' 2>&1';
            } else {
                $localPython = PHP_OS_FAMILY === 'Windows'
                    ? base_path('venv/Scripts/python.exe')
                    : 'python3';

                $command = "\"{$localPython}\" \"{$pythonScript}\" \"{$inputPath}\" \"{$outputPath}\"";
            }

            try {
                set_time_limit(300);
                $output = shell_exec($command);

                if (File::exists($outputPath)) {
                    File::delete($inputPath);

                    // Resize while preserving transparency
                    $sourceImage = imagecreatefrompng($outputPath);
                    $origWidth = imagesx($sourceImage);
                    $origHeight = imagesy($sourceImage);

                    $targetWidth = 425;
                    $scale = $targetWidth / $origWidth;
                    $newWidth = $targetWidth;
                    $newHeight = (int)($origHeight * $scale);

                    $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
                    imagealphablending($resizedImage, false);
                    imagesavealpha($resizedImage, true);
                    imagecopyresampled($resizedImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
                    imagepng($resizedImage, $outputPath);

                    imagedestroy($sourceImage);
                    imagedestroy($resizedImage);

                    $data['image_path'] = 'player_images/' . $outputFilename;
                } else {
                    throw new \Exception('Background removal script failed. Output: ' . $output);
                }
            } catch (\Exception $e) {
                Log::error("Background removal failed (team join): " . $e->getMessage());
                // Fallback: use original image as-is
                $data['image_path'] = 'player_images/' . $originalFilename;
            }
        }

        $player = Player::create($data);

        // Notify team Owner/Manager via database + email
        $dashboardUrl = route('team-manager.dashboard');
        $teamMembers = $team->users()->get();
        foreach ($teamMembers as $member) {
            $role = $member->pivot->role;
            if (in_array($role, ['Owner', 'Manager', 'captain'])) {
                $member->notify(new GeneralNotification(
                    "{$player->name} has requested to join {$team->name}",
                    $dashboardUrl,
                    'player-join-request'
                ));

                // Send email notification
                try {
                    Mail::to($member->email)->send(new PlayerJoinRequestMail($player, $team, $dashboardUrl));
                } catch (\Exception $e) {
                    Log::error("Failed to send join request email to {$member->email}: " . $e->getMessage());
                }
            }
        }

        return redirect()->route('public.team.join.success', $inviteCode);
    }

    public function success($inviteCode)
    {
        $team = ActualTeam::where('invite_code', $inviteCode)
            ->with('tournament.settings')
            ->firstOrFail();

        return view('public.team.join-success', compact('team'));
    }
}

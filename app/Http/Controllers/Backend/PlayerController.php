<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Mail\PlayerVerificationStatusMail;
use App\Mail\PlayerWelcomeMail;
use App\Models\BattingProfile;
use App\Models\BowlingProfile;
use App\Models\ImageTemplate;
use App\Models\KitSize;
use App\Models\Matches;
use App\Models\Player;
use App\Models\PlayerLocation;
use App\Models\PlayerType;
use App\Models\Role;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use App\Notifications\CustomVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\ImageManager;

class PlayerController extends Controller
{

    // public function index(): View
    // {
    //     $this->checkAuthorization(Auth::user(), ['player.view']);

    //     $filters = [
    //         'search'       => request('search'),
    //         'team_name'    => request('team_name'),
    //         'role'         => request('role'),
    //         'status'       => request('status'),
    //         'updated_sort' => request('updated_sort'),
    //     ];

    //     $query = Player::with(['user.organization', 'team', 'playerType']);

    //     $user = Auth::user();

    //     // 🔹 Restrict to players where player's user has same organization_id
    //     if (!$user->hasRole('Superadmin') && $user->organization_id) {
    //         $query->whereHas('user', function ($q) use ($user) {
    //             $q->where('organization_id', $user->organization_id);
    //         });
    //     }

    //     $players = $query
    //         ->when($filters['search'], function ($q) use ($filters) {
    //             $q->where(function ($q) use ($filters) {
    //                 $q->where('name', 'like', "%{$filters['search']}%")
    //                     ->orWhere('email', 'like', "%{$filters['search']}%");
    //             });
    //         })
    //         ->when($filters['team_name'], function ($q) use ($filters) {
    //             $q->whereHas('team', function ($teamQuery) use ($filters) {
    //                 $teamQuery->where('name', $filters['team_name']);
    //             });
    //         })
    //         ->when($filters['role'], function ($q) use ($filters) {
    //             $q->whereHas('playerType', function ($typeQuery) use ($filters) {
    //                 $typeQuery->where('type', $filters['role']);
    //             });
    //         })
    //         ->when($filters['status'], function ($q) use ($filters) {
    //             if ($filters['status'] === 'verified') {
    //                 $q->whereNotNull('welcome_email_sent_at');
    //             } elseif ($filters['status'] === 'pending') {
    //                 $q->whereNull('welcome_email_sent_at');
    //             }
    //         })
    //         ->when($filters['updated_sort'], function ($q) use ($filters) {
    //             if (in_array($filters['updated_sort'], ['asc', 'desc'])) {
    //                 $q->orderBy('updated_at', $filters['updated_sort']);
    //             }
    //         }, function ($q) {
    //             $q->orderBy('updated_at', 'desc');
    //         })
    //         ->paginate(20)
    //         ->appends($filters);

    //     $teams = Team::orderBy('name')->get();
    //     $roles = PlayerType::orderBy('type')->get();

    //     return view('backend.pages.players.index', [
    //         'players'     => $players,
    //         'teams'       => $teams,
    //         'roles'       => $roles,
    //         'breadcrumbs' => [
    //             'title' => __('Players'),
    //         ],
    //     ]);
    // }
    public function index(): View
    {
        $this->checkAuthorization(Auth::user(), ['player.view']);

        // 1. Read all potential filters from the request
        $filters = [
            'search'           => request('search'),
            'team_name'        => request('team_name'),
            'role'             => request('role'),
            'batting_profile'  => request('batting_profile'),
            'bowling_profile'  => request('bowling_profile'),
            'status'           => request('status'),
            'updated_sort'     => request('updated_sort'),
        ];

        // 2. Start the base query with all necessary relationships for performance
        $query = Player::with(['user.organization', 'team', 'playerType', 'location', 'battingProfile', 'bowlingProfile']);
        $user = Auth::user();

        // 3. Apply role-based data scoping
        if ($user->hasRole('Superadmin')) {
            // Superadmins see all players. No initial scope is applied.
        } elseif ($user->hasAnyRole(['Admin'])) {
            // Admins see all players from their own organization, including retained players.
            if ($user->organization_id) {
                $query->whereHas('user', fn($q) => $q->where('organization_id', $user->organization_id));
            } else {
                $query->whereRaw('1 = 0'); // See no players if not assigned to an org
            }
        } else { // This handles 'Team Manager' and any other non-privileged roles
            // These users have the most restricted view.
            if ($user->organization_id) {
                // a) Must be in their organization
                $query->whereHas('user', fn($q) => $q->where('organization_id', $user->organization_id));

                // b) Must be an "onboarded" player
                $query->whereNotNull('welcome_email_sent_at');

                // c) Must NOT be a "Retained" player
                $query->where(function ($q) {
                    $q->where('player_mode', '!=', 'retained')
                        ->orWhereNull('player_mode');
                });
            } else {
                $query->whereRaw('1 = 0'); // See no players if not assigned to an org
            }
        }

        // 4. Apply all user-selected filters to the query
        $players = $query
            ->when($filters['search'], function ($q) use ($filters) {
                $q->where(fn($q) => $q->where('name', 'like', "%{$filters['search']}%")->orWhere('email', 'like', "%{$filters['search']}%"));
            })
            ->when($filters['team_name'], function ($q) use ($filters) {
                $q->whereHas('team', fn($teamQuery) => $teamQuery->where('name', $filters['team_name']));
            })
            ->when($filters['role'], function ($q) use ($filters) {
                if ($filters['role'] === 'Wicket Keeper') {
                    $q->where('is_wicket_keeper', true);
                } else {
                    $q->whereHas('playerType', fn($typeQuery) => $typeQuery->where('type', $filters['role']));
                }
            })
            ->when($filters['batting_profile'], function ($q) use ($filters) {
                $q->whereHas('battingProfile', fn($profileQuery) => $profileQuery->where('style', $filters['batting_profile']));
            })
            ->when($filters['bowling_profile'], function ($q) use ($filters) {
                $q->whereHas('bowlingProfile', fn($profileQuery) => $profileQuery->where('style', 'like', '%' . $filters['bowling_profile'] . '%'));
            })
            ->when($filters['status'], function ($q) use ($filters) {
                if ($filters['status'] === 'verified') $q->whereNotNull('welcome_email_sent_at');
                elseif ($filters['status'] === 'pending') $q->whereNull('welcome_email_sent_at');
            })
            ->when($filters['updated_sort'], function ($q) use ($filters) {
                if (in_array($filters['updated_sort'], ['asc', 'desc'])) $q->orderBy('updated_at', $filters['updated_sort']);
            }, fn($q) => $q->orderBy('updated_at', 'desc'))
            ->paginate(100) // Pagination set to 100
            ->appends($filters); // Ensures filters are remembered on pagination links

        // 5. Fetch data needed for the filter dropdowns
        if ($user->hasRole('Superadmin')) {
            $teams = Team::orderBy('name')->get();
        } else {
            $teams = Team::where('organization_id', $user->organization_id)->orderBy('name')->get();
        }

        $roles = PlayerType::whereIn('type', ['Bowler', 'Batsman', 'All-Rounder'])->get();
        $battingProfiles = BattingProfile::orderBy('style')->get();
        $bowlingProfiles = BowlingProfile::orderBy('style')->get();

        // 6. Return the view and pass all necessary data
        return view('backend.pages.players.index', [
            'players'         => $players,
            'teams'           => $teams,
            'roles'           => $roles,
            'battingProfiles' => $battingProfiles,
            'bowlingProfiles' => $bowlingProfiles,
            'breadcrumbs'     => ['title' => __('Players')],
        ]);
    }

    public function create(): View
    {
        $this->checkAuthorization(Auth::user(), ['player.create']);

        return view('backend.pages.players.create', [
            'teams' => Team::all(),
            'kitSizes' => KitSize::all(),
            'locations' => PlayerLocation::all(),

            'battingProfiles' => BattingProfile::all(),
            'bowlingProfiles' => BowlingProfile::all(),
            'playerTypes' => PlayerType::all(),
            'breadcrumbs' => [
                'title' => __('New Player'),
                'items' => [['label' => __('Players'), 'url' => route('admin.players.index')]],
            ],
        ]);
    }


    public function export(Request $request)
    {
        // 1. Authorize the action
        $this->authorize('player.view'); // Or a new 'player.export' permission if you want more granular control

        // 2. Validate the incoming request
        $request->validate([
            'player_ids' => 'required|array',
            'player_ids.*' => 'exists:players,id', // Ensure all IDs are valid players
        ]);

        // 3. Fetch the selected players from the database
        $playerIds = $request->input('player_ids');
        $players = Player::with(['team', 'playerType', 'location'])->whereIn('id', $playerIds)->get();

        // 4. Set up the CSV file response
        $fileName = 'players_export_' . date('Y-m-d_H-i-s') . '.csv';
        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        // 5. Create a callback to stream the CSV data
        // This is memory-efficient for large exports.
        $callback = function () use ($players) {
            $file = fopen('php://output', 'w');

            // Add CSV headers
            $columns = [
                'ID',
                'Name',
                'Email',
                'Mobile Number',
                'Team',
                'Player Role',
                'Location',
                'Status'
            ];
            fputcsv($file, $columns);

            // Add data for each player
            foreach ($players as $player) {
                fputcsv($file, [
                    $player->id,
                    $player->name,
                    $player->email,
                    $player->mobile_number_full,
                    $player->team?->name ?? 'N/A',
                    $player->playerType?->type ?? 'N/A',
                    $player->location?->name ?? 'N/A',
                    !is_null($player->welcome_email_sent_at) ? 'Verified' : 'Pending',
                ]);
            }

            fclose($file);
        };

        // 6. Return the streamed response to the browser
        return response()->stream($callback, 200, $headers);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['player.create']);

        // Sanitize and combine phone numbers
        $mobileFull = preg_replace('/\D+/', '', (string) $request->input('mobile_country_code') . (string) $request->input('mobile_national_number'));
        $cricheroesFull = $request->filled(['cricheroes_country_code', 'cricheroes_national_number'])
            ? preg_replace('/\D+/', '', (string) $request->input('cricheroes_country_code') . (string) $request->input('cricheroes_national_number'))
            : null;

        $request->merge([
            'mobile_number_full' => $mobileFull,
            'cricheroes_number_full' => $cricheroesFull,
        ]);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:players,email',
            'mobile_country_code' => 'required|string|max:10',
            'mobile_national_number' => 'required|string|max:20',
            'mobile_number_full' => [
                'required',
                'numeric',
                'digits_between:7,15',
                'unique:players,mobile_number_full',
            ],

            'team_id' => 'nullable|exists:teams,id',
            'jersey_number' => 'nullable',
            'team_name_ref' => 'nullable|string|max:100',
            'jersey_name' => 'required|string|max:50',
            'kit_size_id' => 'required|exists:kit_sizes,id',
            'batting_profile_id' => 'required|exists:batting_profiles,id',
            'bowling_profile_id' => 'required|exists:bowling_profiles,id',
            'player_type_id' => 'required|exists:player_types,id',
            'location_id' => 'required|exists:player_locations,id',
            'total_matches' => 'nullable|integer|min:0',
            'total_runs' => 'nullable|integer|min:0',
            'total_wickets' => 'nullable|integer|min:0',
            'cricheroes_country_code' => 'nullable|string|max:10',
            'cricheroes_national_number' => 'nullable|string|max:20',
            'cricheroes_number_full' => [
                'nullable',
                'numeric',
                'digits_between:7,15',
                Rule::unique('players', 'cricheroes_number_full')->whereNotNull('cricheroes_number_full'),
            ],

            'image_path' => [
                'nullable',
                'image',
                'mimes:png,jpg,jpeg',
                'max:6144',

            ],

            'wicket_keeper' => 'nullable|boolean',
            'need_transportation' => 'nullable|boolean',

            // 🧳 Travel Plan Validation
            'no_travel_plan' => 'nullable|boolean',
            'travel_date_from' => 'nullable|date|after_or_equal:today|required_if:no_travel_plan,false',
            'travel_date_to' => 'nullable|date|after_or_equal:travel_date_from|required_if:no_travel_plan,false',
        ], [
            'mobile_number_full.unique' => 'This mobile number is already registered.',
            'cricheroes_number_full.unique' => 'This CricHeroes number is already registered.',
            'image_path.mimes' => 'The profile image must be a PNG, JPG, or JPEG file.',
            'image_path.max' => 'The profile image size cannot be more than 6MB.',
            'travel_date_from.after_or_equal' => 'The travel start date must be today or later.',
            'travel_date_to.after_or_equal' => 'The travel end date must be after or equal to the start date.',
        ]);

        // 🖼️ Handle image upload
        if ($request->hasFile('image_path')) {
            $validated['image_path'] = $request->file('image_path')->store('player_images', 'public');
        }

        // ✅ Boolean Flags
        $validated['is_wicket_keeper'] = $request->boolean('wicket_keeper');
        $validated['transportation_required'] = $request->boolean('need_transportation');
        $validated['no_travel_plan'] = $request->boolean('no_travel_plan');
        $validated['created_by'] = Auth::id();


        // Create user first
        $username = Str::slug(Str::before($validated['email'], '@'), '_');
        if (User::where('username', $username)->exists()) {
            $username .= '_' . Str::random(5);
        }
        $password = Str::random(12);

        $user = User::create([
            'name' => $validated['name'],
            'organization_id' => Auth::user()->organization_id,
            'username' => $username,
            'email' => $validated['email'],
            'password' => Hash::make($password),
            'email_verified_at' => null,
        ]);

        // Add user_id to validated data before creating player
        $validated['user_id'] = $user->id;

        // Now create player
        $player = Player::create($validated);

        // Assign player role if needed
        if (!$user->hasRole('player')) {
            $playerRole = Role::firstOrCreate(['name' => 'player']);
            $user->assignRole($playerRole);
        }

        // Send welcome email with credentials
        $user->notify(new CustomVerifyEmail($password));



        return redirect()->route('admin.players.index')->with('success', __('Player created successfully.'));
    }



    public function show(Player $player): View
    {
        $this->checkAuthorization(Auth::user(), ['player.view']);

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

        return view('backend.pages.players.show', [
            'player' => $player,
            'teams' => Team::all(),
            'locations' => PlayerLocation::all(),
            'kitSizes' => KitSize::all(),
            'battingProfiles' => BattingProfile::all(),
            'bowlingProfiles' => BowlingProfile::all(),
            'playerTypes' => PlayerType::all(),
            'templates' => ImageTemplate::all(),
            'breadcrumbs' => [
                'title' => __('View Player'),
                'items' => [
                    ['label' => __('Players'), 'url' => route('admin.players.index')],
                ],
            ],
            'verifiedFields' => $verifiedFields,
            'verifiedProfile' => $player->allFieldsVerified()
        ]);
    }

    public function edit(Player $player): View
    {
        $this->checkAuthorization(Auth::user(), ['player.edit']);

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

        return view('backend.pages.players.edit', [
            'player' => $player,
            'teams' => Team::all(),
            'locations' => PlayerLocation::all(),
            'kitSizes' => KitSize::all(),
            'battingProfiles' => BattingProfile::all(),
            'bowlingProfiles' => BowlingProfile::all(),
            'playerTypes' => PlayerType::all(),
            'templates' => ImageTemplate::all(),
            'breadcrumbs' => [
                'title' => __('Edit Player'),
                'items' => [
                    ['label' => __('Players'), 'url' => route('admin.players.index')],
                ],
            ],
            'verifiedFields' => $verifiedFields,
            'verifiedProfile' => $player->allFieldsVerified()
        ]);
    }






    public static function generateWelcomePlayerImageGD(Player $player, ImageTemplate $template)
    {
        $layout = json_decode($template->layout_json, true);
        $bgPath = storage_path('app/public/image_templates/' . $template->background_image);

        if (!file_exists($bgPath)) {
            throw new \Exception("Background image not found: $bgPath");
        }

        [$bgWidth, $bgHeight] = getimagesize($bgPath);
        $image = imagecreatetruecolor($bgWidth, $bgHeight);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);

        $bg = imagecreatefrompng($bgPath);
        imagecopy($image, $bg, 0, 0, 0, 0, $bgWidth, $bgHeight);
        imagedestroy($bg);

        foreach ($layout['objects'] as $object) {
            switch ($object['type']) {

                case 'playerImage':
                    $playerImgPath = storage_path('app/public/' . Str::after($player->image_path, 'storage/'));

                    if (!file_exists($playerImgPath)) {
                        throw new \Exception("Player image not found at: $playerImgPath");
                    }

                    if (filesize($playerImgPath) === 0) {
                        throw new \Exception("Player image is empty (0 bytes).");
                    }

                    $playerImg = @imagecreatefrompng($playerImgPath);
                    if (!$playerImg) {
                        throw new \Exception("imagecreatefrompng() failed. File may be corrupted or not a valid PNG.");
                    }

                    $scaleX = $object['scaleX'] ?? 1;
                    $scaleY = $object['scaleY'] ?? 1;

                    $scaledWidth = imagesx($playerImg) * $scaleX;
                    $scaledHeight = imagesy($playerImg) * $scaleY;

                    // Limit size to prevent memory crash
                    $maxSize = 3000;
                    if ($scaledWidth > $maxSize || $scaledHeight > $maxSize) {
                        $ratio = min($maxSize / $scaledWidth, $maxSize / $scaledHeight);
                        $scaledWidth *= $ratio;
                        $scaledHeight *= $ratio;
                    }

                    $scaledWidth = (int) $scaledWidth;
                    $scaledHeight = (int) $scaledHeight;

                    if ($scaledWidth <= 0 || $scaledHeight <= 0) continue 2;

                    $resized = imagecreatetruecolor($scaledWidth, $scaledHeight);
                    imagealphablending($resized, false);
                    imagesavealpha($resized, true);
                    imagecopyresampled(
                        $resized,
                        $playerImg,
                        0,
                        0,
                        0,
                        0,
                        $scaledWidth,
                        $scaledHeight,
                        imagesx($playerImg),
                        imagesy($playerImg)
                    );

                    $left = (int) $object['left'];
                    $top = (int) $object['top'];
                    imagecopy($image, $resized, $left, $top, 0, 0, $scaledWidth, $scaledHeight);

                    imagedestroy($resized);
                    imagedestroy($playerImg);
                    break;

                case 'staticOverlay':
                    if (!$template->overlay_image_path) continue 2;

                    $overlayPath = storage_path('app/public/image_templates/' . $template->overlay_image_path);
                    if (!file_exists($overlayPath)) continue 2;

                    $overlayImg = imagecreatefrompng($overlayPath);
                    imagealphablending($overlayImg, true);
                    imagesavealpha($overlayImg, true);

                    $scaleX = $object['scaleX'] ?? 1;
                    $scaleY = $object['scaleY'] ?? 1;
                    $overlayW = imagesx($overlayImg) * $scaleX;
                    $overlayH = imagesy($overlayImg) * $scaleY;

                    $resizedOverlay = imagecreatetruecolor($overlayW, $overlayH);
                    imagealphablending($resizedOverlay, false);
                    imagesavealpha($resizedOverlay, true);
                    imagecopyresampled(
                        $resizedOverlay,
                        $overlayImg,
                        0,
                        0,
                        0,
                        0,
                        $overlayW,
                        $overlayH,
                        imagesx($overlayImg),
                        imagesy($overlayImg)
                    );

                    $left = (int) $object['left'];
                    $top = (int) $object['top'];
                    imagecopy($image, $resizedOverlay, $left, $top, 0, 0, $overlayW, $overlayH);

                    imagedestroy($resizedOverlay);
                    imagedestroy($overlayImg);
                    break;

                case 'playerName':
                    $colorHex = ltrim($object['fill'] ?? '#ffffff', '#');
                    $r = hexdec(substr($colorHex, 0, 2));
                    $g = hexdec(substr($colorHex, 2, 2));
                    $b = hexdec(substr($colorHex, 4, 2));

                    $fontPath = public_path('fonts/Montserrat-Medium.ttf');
                    if (!file_exists($fontPath)) {
                        throw new \Exception("Font not found at $fontPath");
                    }

                    $fontSize = $object['fontSize'] ?? 48;
                    $angle = $object['angle'] ?? 0;
                    $text = $player->jersey_name;

                    // Calculate bounding box
                    $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);
                    $textWidth = abs($bbox[4] - $bbox[0]);
                    $textHeight = abs($bbox[5] - $bbox[1]);

                    // Create a transparent image for the text
                    $textImg = imagecreatetruecolor($textWidth + 10, $textHeight + 10);
                    imagealphablending($textImg, false);
                    imagesavealpha($textImg, true);
                    $transparent = imagecolorallocatealpha($textImg, 0, 0, 0, 127);
                    imagefill($textImg, 0, 0, $transparent);

                    $textColor = imagecolorallocate($textImg, $r, $g, $b);
                    imagettftext($textImg, $fontSize, 0, 0, $textHeight, $textColor, $fontPath, $text);

                    // Rotate text
                    $rotated = imagerotate($textImg, -$angle, $transparent);
                    imagedestroy($textImg);

                    // Final position
                    $left = (int) $object['left'];
                    $top = (int) $object['top'];

                    imagecopy($image, $rotated, $left, $top, 0, 0, imagesx($rotated), imagesy($rotated));
                    imagedestroy($rotated);
                    break;
            }
        }

        // Save final image
        $outputPath = 'public/generated_welcome/' . Str::slug($player->name) . '.png';
        Storage::makeDirectory('public/generated_welcome');
        imagepng($image, storage_path('app/' . $outputPath));
        imagedestroy($image);

        return 'storage/' . Str::after($outputPath, 'public/');
    }





    public function editor(Player $player)
    {
        return view('backend.pages.players.image-editor', compact('player'));
    }

    public function saveImage(Request $request, ImageManager $imageManager)
    {
        $request->validate([
            'player_id' => 'required|exists:players,id',
            'layout_data' => 'required|string',
        ]);

        $player = Player::findOrFail($request->player_id);

        $layout = json_decode($request->layout_data, true);

        if (!$layout || !isset($layout['objects'])) {
            return response()->json(['error' => 'Invalid layout JSON'], 422);
        }

        // Load base background image
        $backgroundPath = storage_path('app/public/backgrounds/welcome-template.png');
        if (!file_exists($backgroundPath)) {
            return response()->json(['error' => 'Background template missing'], 500);
        }

        $canvas = $imageManager->read($backgroundPath);

        foreach ($layout['objects'] as $object) {
            // Always replace image src with player image from DB
            if ($object['type'] === 'image') {
                $playerImagePath = storage_path('app/public/' . $player->image_path);

                if (file_exists($playerImagePath)) {
                    $img = $imageManager->read($playerImagePath);

                    // Scale the image
                    if (isset($object['scaleX'], $object['scaleY'])) {
                        $newWidth = $img->width() * $object['scaleX'];
                        $newHeight = $img->height() * $object['scaleY'];
                        $img->resize((int)$newWidth, (int)$newHeight);
                    }

                    // Paste at position
                    $canvas->place($img, (int)($object['left'] ?? 0), (int)($object['top'] ?? 0));
                }
            }

            // Render text
            if (in_array($object['type'], ['text', 'textbox']) && isset($object['text'])) {
                $canvas->text($object['text'], (int)($object['left'] ?? 0), (int)($object['top'] ?? 0), function ($font) use ($object) {
                    if (!empty($object['fontFamily'])) {
                        $fontPath = public_path('fonts/' . $object['fontFamily'] . '.ttf');
                        if (file_exists($fontPath)) {
                            $font->file($fontPath);
                        }
                    }

                    $font->size((int)($object['fontSize'] ?? 24));
                    $font->color($object['fill'] ?? '#000000');
                    $font->align('left');
                    $font->valign('top');
                });
            }
        }

        // Save final image
        $filename = 'welcome_images/' . Str::uuid() . '.png';
        Storage::disk('public')->put($filename, (string)$canvas->toPng());

        // Update player
        $player->welcome_image_path = $filename;
        $player->layout_json = $request->layout_data;
        $player->save();

        return response()->json([
            'success' => true,
            'image_url' => Storage::url($filename),
            'message' => 'Image generated and saved successfully.'
        ]);
    }



    public function update(Request $request, Player $player): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['player.edit']);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:players,email,' . $player->id,
            'mobile_number_full' => 'required|string|max:20',
            'cricheroes_number_full' => 'required|string|max:20',
            'jersey_number' => 'nullable',

            'team_id' => 'nullable|exists:teams,id',
            'location_id' => 'required|exists:player_locations,id',
            'total_matches' => 'nullable|integer|min:0',
            'total_runs' => 'nullable|integer|min:0',
            'total_wickets' => 'nullable|integer|min:0',
            'team_name_ref' => 'nullable|string|max:100',
            'jersey_name' => 'nullable|string|max:50',
            'kit_size_id' => 'nullable|exists:kit_sizes,id',
            'batting_profile_id' => 'nullable|exists:batting_profiles,id',
            'bowling_profile_id' => 'nullable|exists:bowling_profiles,id',
            'player_type_id' => 'nullable|exists:player_types,id',
            'image_path' => 'nullable|image|mimes:jpg,jpeg,png|max:6144',
            'is_wicket_keeper' => 'sometimes|boolean',
            'is_transportation_required' => 'sometimes|boolean',
            'no_travel_plan' => 'nullable|boolean',
            'travel_date_from' => 'nullable|date',
            'travel_date_to' => 'nullable|date|after_or_equal:travel_date_from',
        ]);


        if ($request->hasFile('image_path')) {

            // 1. Delete the old image if it exists
            if ($player->image_path && Storage::disk('public')->exists($player->image_path)) {
                Storage::disk('public')->delete($player->image_path);
            }

            $imageFile = $request->file('image_path');

            // 2. Save the newly uploaded image file
            $originalFilename = 'original-' . Str::random(10) . '.' . $imageFile->getClientOriginalExtension();
            // Use the Storage facade, it's cleaner
            $imageFile->storeAs('public/player_images', $originalFilename);
            $inputPath = storage_path('app/public/player_images/' . $originalFilename);

            // 3. Define paths for the background removal script
            $outputFilename = 'processed-' . Str::random(10) . '.png';
            $outputPath = storage_path('app/public/player_images/' . $outputFilename);

            // Use Laravel helpers for robust path definitions
            $pythonBinary = base_path('rembg-env/bin/python');
            $pythonScript = resource_path('scripts/remove_bg.py');
            if (app()->environment('production')) {
                // === PRODUCTION ENVIRONMENT ===
                $pythonBinary = base_path('rembg-env/bin/python');
                $cachePath = storage_path('app/rembg_cache');
                File::ensureDirectoryExists($cachePath);

                $command = 'U2NET_HOME=' . escapeshellarg($cachePath) . ' ' .
                    escapeshellcmd($pythonBinary) . ' ' .
                    escapeshellarg($pythonScript) . ' ' .
                    escapeshellarg($inputPath) . ' ' .
                    escapeshellarg($outputPath) . ' 2>&1';
            } else {
                // === LOCAL ENVIRONMENT ===
                $pythonBinary = PHP_OS_FAMILY === 'Windows'
                    ? base_path('venv/Scripts/python.exe')
                    : 'python3';

                $command = "\"{$pythonBinary}\" \"{$pythonScript}\" \"{$inputPath}\" \"{$outputPath}\"";
            }

            try {
                // 6. Execute the command
                set_time_limit(300); // Allow up to 5 minutes for execution
                $output = shell_exec($command);

                // 7. Verify the result and update the model
                if (File::exists($outputPath)) {
                    // Delete the original uploaded file
                    File::delete($inputPath);

                    // Load the image (transparency supported)
                    $sourceImage = imagecreatefrompng($outputPath);
                    $origWidth = imagesx($sourceImage);
                    $origHeight = imagesy($sourceImage);

                    // Target width around 425 (average)
                    $targetWidth = 425;
                    $scale = $targetWidth / $origWidth;
                    $newWidth = $targetWidth;
                    $newHeight = (int)($origHeight * $scale);

                    // Create a resized image canvas
                    $resizedImage = imagecreatetruecolor($newWidth, $newHeight);

                    // Preserve transparency
                    imagealphablending($resizedImage, false);
                    imagesavealpha($resizedImage, true);

                    // Copy and resize
                    imagecopyresampled($resizedImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

                    // Save resized image (overwrite original)
                    imagepng($resizedImage, $outputPath);

                    // Cleanup memory
                    imagedestroy($sourceImage);
                    imagedestroy($resizedImage);

                    // Save relative path to DB
                    $player->image_path = 'player_images/' . $outputFilename;
                    $validated['image_path'] = $player->image_path;
                } else {
                    // Throw an exception if the file wasn't created, including the script's output
                    throw new \Exception('Python script failed to create the output file. Output: ' . $output);
                }
            } catch (\Exception $e) {
                Log::error("Background removal failed: " . $e->getMessage());
                // Optionally, return an error response to the user
                // return back()->withErrors(['image_path' => 'Failed to process the image.']);
            }
        }




        // If clear image checkbox was checked
        elseif ($request->input('clear_image')) {
            if ($player->image_path) {
                Storage::delete('public/' . $player->image_path);
            }
            $player->image_path = null;
        }


        // ✅ Assign validated fields
        $player->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'total_matches' => $validated['total_matches'],
            'total_runs' => $validated['total_runs'],
            'total_wickets' => $validated['total_wickets'],
            'location_id' => $validated['location_id'],
            'team_name_ref' => $validated['team_name_ref'],

            'mobile_number_full' => $validated['mobile_number_full'],
            'cricheroes_number_full' => $validated['cricheroes_number_full'],
            'team_id' => $validated['team_id'] ?? null,
            'jersey_name' => $validated['jersey_name'] ?? null,
            'jersey_number' => $validated['jersey_number'] ?? null,
            'kit_size_id' => $validated['kit_size_id'] ?? null,
            'batting_profile_id' => $validated['batting_profile_id'] ?? null,
            'bowling_profile_id' => $validated['bowling_profile_id'] ?? null,
            'player_type_id' => $validated['player_type_id'] ?? null,
            'is_wicket_keeper' => $request->boolean('is_wicket_keeper'),
            'transportation_required' => $request->boolean('transportation_required'),
            'no_travel_plan' => $request->boolean('no_travel_plan'),
            'travel_date_from' => $validated['travel_date_from'] ?? null,
            'travel_date_to' => $validated['travel_date_to'] ?? null,
        ]);

        // ✅ Assign verified flags
        // ✅ Assign verified flags
        $player->verified_name = $request->boolean('verified_name');
        $player->verified_jersey_number = $request->boolean('verified_jersey_number');

        $player->verified_email = $request->boolean('verified_email');
        $player->verified_mobile_number_full = $request->boolean('verified_mobile_number_full');
        $player->verified_image_path = $request->boolean('verified_image_path');
        $player->verified_cricheroes_number_full = $request->boolean('verified_cricheroes_number_full');
        $player->verified_team_id = $request->boolean('verified_team_id');
        $player->verified_jersey_name = $request->boolean('verified_jersey_name');
        $player->verified_kit_size_id = $request->boolean('verified_kit_size_id');
        $player->verified_batting_profile_id = $request->boolean('verified_batting_profile_id');
        $player->verified_bowling_profile_id = $request->boolean('verified_bowling_profile_id');
        $player->verified_player_type_id = $request->boolean('verified_player_type_id');
        $player->verified_is_wicket_keeper = $request->boolean('verified_is_wicket_keeper');
        $player->verified_transportation_required = $request->boolean('verified_transportation_required');
        $player->verified_no_travel_plan = $request->boolean('verified_no_travel_plan'); // ✅ add this line


        $player->save();


        if ($request->isapproved == '1') {


            // Already approved?
            if ($player->isApproved()) {
                return back()->with('error', 'Player is already approved.');
            }

            // Rejected players cannot be approved
            if ($player->isRejected()) {
                return back()->with('error', 'Player has been rejected and cannot be approved.');
            }

            // Ensure the player has a linked user
            $user = $player->user;
            if (!$user) {
                $username = Str::slug(Str::before($validated['email'], '@'), '_');
                if (User::where('username', $username)->exists()) {
                    $username .= '_' . Str::random(5);
                }
                $password = Str::random(12);
                $password = Str::random(12);
                $user = User::create([
                    'name' => $validated['name'],
                    'organization_id' => Auth::user()->organization_id, // from logged-in admin

                    'username' => $username,
                    'email' => $validated['email'],
                    'password' => Hash::make($password),
                    'email_verified_at' => null,
                ]);
                // Link player to user
                $player->user_id = $user->id;
                $player->save();
                $user->notify(new CustomVerifyEmail($password));

                // Optionally assign "player" role
                if (!$user->hasRole('player')) {
                    $playerRole = Role::firstOrCreate(['name' => 'player']);
                    $user->assignRole($playerRole);
                }
            }

            // Assign 'player' role if not already assigned
            if (!$user->hasRole('player')) {
                $playerRole = Role::firstOrCreate(['name' => 'player']);
                $user->assignRole($playerRole);
            }

            // Approve player
            $player->status = 'approved';
            $player->approved_by = auth()->id();
            $player->save();

            return back()->with('success', 'Player approved and activated.');
        }

        // ✅ Optional Intimate after update
        if ($request->boolean('intimate')) {

            if (!$player->email) {
                return back()->with('error', 'Player does not have a valid email address.');
            }

            $verificationFields = [
                'name' => $player->verified_name,
                'email' => $player->verified_email,
                'mobile_number_full' => $player->verified_mobile_number_full,
                'image_path' => $player->verified_image_path,
                'cricheroes_number_full' => $player->verified_cricheroes_number_full,
                'team_id' => $player->verified_team_id,
                'jersey_name' => $player->verified_jersey_name,
                'kit_size_id' => $player->verified_kit_size_id,
                'batting_profile_id' => $player->verified_batting_profile_id,
                'bowling_profile_id' => $player->verified_bowling_profile_id,
                'player_type_id' => $player->verified_player_type_id,
                'is_wicket_keeper' => $player->verified_is_wicket_keeper,
                'transportation_required' => $player->verified_transportation_required,
                'no_travel_plan' => $player->verified_no_travel_plan, // ✅ add this

            ];

            $verifiedFields = [];
            $unverifiedFields = [];

            foreach ($verificationFields as $field => $isVerified) {
                if ($isVerified) {
                    $verifiedFields[] = $field;
                } else {
                    $unverifiedFields[] = $field;
                }
            }

            Mail::to($player->email)->send(
                new PlayerVerificationStatusMail($player, $verifiedFields, $unverifiedFields)
            );


            return redirect()->back()->with('success', 'Player updated and intimated.');

            // return redirect()->route('admin.players.index')->with('success', 'Player updated and intimated.');
        }

        // ✅ Optional: Generate welcome image only if all fields verified
        if ($request->boolean('allverified')) {
            if (!$player->allFieldsVerified()) {
                return redirect()->back()->with('error', 'Cannot generate appreciation image. All fields must be verified.');
            }

            if ($player->welcome_email_sent_at) {
                return redirect()->back()->with('error', 'Welcome image has already been sent.');
            }

            $template = ImageTemplate::where('category_id', 1)->first();
            if (!$template) {
                return redirect()->back()->with('error', 'There is no welcome template associated! Please create and try again!');
            }
            $imagePath = $this->generateWelcomePlayerImageGD($player, $template);

            if (is_array($imagePath)) {
                $imagePath = $imagePath[0] ?? '';
            }

            Mail::to($player->email)->send(new PlayerWelcomeMail($player, $imagePath));

            // Mark email as sent
            $player->update(['welcome_email_sent_at' => now()]);
            return redirect()->back()->with('success', 'Player - Welcome image created and intimated.');
        }




        return redirect()->back()->with('success', 'Player updated successfully.');
    }













    public function destroy(Player $player): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['player.delete']);

        // Delete associated image when deleting player
        if ($player->image_path) {
            Storage::delete('public/' . $player->image_path);
        }

        $player->delete();

        return redirect()->route('admin.players.index')->with('success', __('Player deleted.'));
    }


    public function importCsv(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|mimes:csv,txt|max:6144',
        ]);

        $path = $request->file('csv_file')->getRealPath();
        $file = fopen($path, 'r');
        $header = fgetcsv($file);

        $expectedHeaders = [
            'name',
            'email', // Added email to expected headers
            'mobile_country_code', // Added
            'mobile_national_number', // Added
            'team_id',
            'jersey_name',
            'kit_size',
            'cricheroes_country_code', // Added
            'cricheroes_national_number', // Added
            'batting_profile',
            'bowling_profile',
            'wicket_keeper',
            'transportation_required'
        ];

        if ($header !== $expectedHeaders) {
            return back()->with('error', 'Invalid CSV headers. Required: ' . implode(', ', $expectedHeaders));
        }

        $imported = 0;
        $skipped = [];

        while ($row = fgetcsv($file)) {
            // Ensure the row has enough columns before combining
            if (count($row) !== count($expectedHeaders)) {
                $skipped[] = [
                    'row_data' => implode(',', $row),
                    'reason' => 'Mismatch in column count',
                ];
                continue;
            }

            $data = array_combine($expectedHeaders, $row);

            // Normalize booleans
            $data['is_wicket_keeper'] = filter_var($data['wicket_keeper'], FILTER_VALIDATE_BOOLEAN);
            $data['transportation_required'] = filter_var($data['transportation_required'], FILTER_VALIDATE_BOOLEAN);
            unset($data['wicket_keeper']); // Remove original field as we've renamed it for the model

            $data['created_by'] = Auth::id();

            // Resolve relationships for kit_size, batting_profile, bowling_profile, player_type
            $kitSize = !empty($data['kit_size']) ? KitSize::where('name', $data['kit_size'])->first() : null;
            $data['kit_size_id'] = $kitSize?->id;
            unset($data['kit_size']);

            $battingProfile = !empty($data['batting_profile']) ? BattingProfile::where('name', $data['batting_profile'])->first() : null;
            $data['batting_profile_id'] = $battingProfile?->id;
            unset($data['batting_profile']);

            $bowlingProfile = !empty($data['bowling_profile']) ? BowlingProfile::where('name', $data['bowling_profile'])->first() : null;
            $data['bowling_profile_id'] = $bowlingProfile?->id;
            unset($data['bowling_profile']);

            // Assuming 'player_type' might also be in the CSV for import,
            // though it's not in your original expectedHeaders for CSV.
            // If it is, add it to $expectedHeaders and handle it similarly:
            // $playerType = !empty($data['player_type']) ? PlayerType::where('name', $data['player_type'])->first() : null;
            // $data['player_type_id'] = $playerType?->id;
            // unset($data['player_type']);


            // Validate or nullify team_id
            $team = !empty($data['team_id']) ? Team::find($data['team_id']) : null;
            $data['team_id'] = $team?->id;

            // Check for duplicate player by email or mobile_national_number for robustness
            $exists = Player::where('email', $data['email'])
                ->orWhere('mobile_national_number', $data['mobile_national_number'])
                ->exists();


            if ($exists) {
                $skipped[] = [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'mobile_national_number' => $data['mobile_national_number'],
                    'reason' => 'Duplicate entry (email or mobile number)',
                ];
                continue;
            }

            try {
                Player::create($data);
                $imported++;
            } catch (\Exception $e) {

                $skipped[] = [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'mobile_national_number' => $data['mobile_national_number'],
                    'reason' => 'Database error: ' . $e->getMessage(),
                ];
            }
        }

        fclose($file);

        // Prepare error summary for skipped
        $message = "$imported players imported successfully.";
        if (!empty($skipped)) {
            $message .= ' Some rows were skipped due to duplicates or errors:<br><ul>';
            foreach ($skipped as $entry) {
                $reason = $entry['reason'] ?? 'Unknown reason';
                $message .= "<li><strong>{$entry['name']}</strong> (Email: {$entry['email']}, Mobile: {$entry['mobile_national_number']}) - Reason: {$reason}</li>";
            }
            $message .= '</ul>';
            return redirect()->route('admin.players.index')->with('error', $message); // Change to error for skipped items
        }

        return redirect()->route('admin.players.index')->with('success', $message);
    }


    public function downloadSampleCsv()
    {
        $filename = "players_sample.csv";

        $headers = [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0",
        ];

        $columns = [
            'name',
            'email',
            'mobile_country_code',
            'mobile_national_number',
            'team_id',
            'jersey_name',
            'kit_size', // Assuming this is the name, not ID
            'cricheroes_country_code',
            'cricheroes_national_number',
            'batting_profile', // Assuming this is the name, not ID
            'bowling_profile', // Assuming this is the name, not ID
            'wicket_keeper',
            'transportation_required',
        ];

        $sampleData = [
            ['John Doe', 'john.doe@example.com', '+91', '9876543210', 1, 'JD', 'XL', '+91', '1234567890', 'RHB', 'Right-arm offbreak', 'true', 'false'],
            ['Mike Smith', 'mike.smith@example.com', '+91', '9123456789', 2, 'MS', 'M', '+91', '2345678901', 'LHB', 'Left-arm fast', 'false', 'true'],
            ['Sarah Khan', 'sarah.khan@example.com', '+91', '9345678901', 1, 'SK', 'L', '+91', '3456789012', 'RHB', 'Right-arm legbreak', 'true', 'true'],
        ];

        $callback = function () use ($columns, $sampleData) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($sampleData as $row) {
                fputcsv($file, $row);
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }
}

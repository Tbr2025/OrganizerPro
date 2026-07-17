<?php

namespace App\Http\Controllers\Backend;

use App\Helpers\PlayerFormConfig;
use App\Http\Controllers\Controller;
use App\Mail\PlayerVerificationStatusMail;
use App\Mail\PlayerWelcomeMail;
use App\Models\ActualTeam;
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
use App\Models\TournamentRegistration;
use App\Models\TournamentTemplate;
use App\Models\User;
use App\Services\Notification\TournamentNotificationService;
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
use App\Models\ActionLog;
use App\Traits\HasActionLogTrait;

class PlayerController extends Controller
{
    use HasActionLogTrait;

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
            'actual_team_id'   => request('actual_team_id'),
            'role'             => request('role'),
            'batting_profile'  => request('batting_profile'),
            'bowling_profile'  => request('bowling_profile'),
            'status'           => request('status', 'approved'),
            'updated_sort'     => request('updated_sort'),
            'player_mode'     => request('player_mode'),
            'tournament'       => request('tournament'),
            'sort'             => request('sort'),
        ];

        // 2. Start the base query with all necessary relationships for performance
        $user = Auth::user();
        $query = Player::with([
            'user.organization',
            'user.actualTeams', // <-- CRITICAL: Load the actual team relationship
            'user.roles',
            'team',
            'actualTeam',
            'playerType',
            'location',
            'battingProfile',
            'bowlingProfile',
            'registeredTournaments', // tournament tags in the listing
        ]);

        // Filter out orphaned player records (no associated user)
        $query->whereNotNull('user_id');

        // Only show users who have the 'player' role (they may also have other roles)
        $query->whereHas('user.roles', fn($q) => $q->where('name', 'player'));

        // 3. Apply role-based data scoping
        if ($user->hasRole('Superadmin')) {
            // Superadmins see all players. No initial scope is applied.
        } elseif ($user->hasRole('Team Manager') && !$user->hasRole('Admin')) {
            // Team Managers only see players from their own team(s)
            $teamIds = $user->actualTeams->pluck('id')->toArray();
            if (!empty($teamIds)) {
                $query->whereIn('actual_team_id', $teamIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($user->organization_id) {
            // Admins/Organizers see players from their organization's tournaments
            $orgTeamIds = \App\Models\ActualTeam::whereHas('tournament', function ($q) use ($user) {
                $q->where('organization_id', $user->organization_id);
            })->pluck('id')->toArray();
            $query->whereIn('actual_team_id', $orgTeamIds);
        } else {
            $query->whereRaw('1 = 0');
        }

        // 4. Apply all user-selected filters to the query
        $players = $query
            ->when($filters['search'], function ($q) use ($filters) {
                $q->where(fn($q) => $q->where('name', 'like', "%{$filters['search']}%")->orWhere('email', 'like', "%{$filters['search']}%"));
            })
            ->when($filters['actual_team_id'], function ($q) use ($filters) {
                $q->where('actual_team_id', $filters['actual_team_id']);
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
            })->when($filters['player_mode'] ?? null, function ($q, $filters) {
                if ($filters === 'retained') {
                    // This is correct. It filters a simple column on the main table.
                    $q->where('player_mode', 'retained');
                } elseif ($filters === 'normal') {
                    $q->where(function ($subQ) {
                        $subQ->where('player_mode', 'normal')
                            ->orWhereNull('player_mode');
                    });
                }
            })
            ->when($filters['bowling_profile'], function ($q) use ($filters) {
                $q->whereHas('bowlingProfile', fn($profileQuery) => $profileQuery->where('style', 'like', '%' . $filters['bowling_profile'] . '%'));
            })
            ->when($filters['status'] && $filters['status'] !== 'all', function ($q) use ($filters) {
                if ($filters['status'] === 'approved') $q->where('status', 'approved');
                elseif ($filters['status'] === 'pending') $q->where(fn($q) => $q->where('status', 'pending')->orWhereNull('status'));
                elseif ($filters['status'] === 'queued') $q->where('status', 'queued');
                elseif ($filters['status'] === 'rejected') $q->where('status', 'rejected');
            })
            // Filter by the tournament the player is assigned to (via actual team).
            ->when($filters['tournament'], function ($q) use ($filters) {
                $tournamentTeamIds = ActualTeam::forTournament($filters['tournament'])->pluck('id');
                $q->whereIn('actual_team_id', $tournamentTeamIds);
            })
            // Sort: explicit "sort" dropdown wins; else legacy updated_sort; else newest-updated.
            ->when(true, function ($q) use ($filters) {
                switch ($filters['sort']) {
                    case 'name_asc':   $q->orderBy('name'); break;
                    case 'name_desc':  $q->orderByDesc('name'); break;
                    case 'newest':     $q->orderByDesc('created_at'); break;
                    case 'oldest':     $q->orderBy('created_at'); break;
                    case 'recently_updated': $q->orderByDesc('updated_at'); break;
                    default:
                        if (in_array($filters['updated_sort'], ['asc', 'desc'], true)) {
                            $q->orderBy('updated_at', $filters['updated_sort']);
                        } else {
                            $q->orderByDesc('updated_at');
                        }
                }
            })
            ->paginate(100) // Pagination set to 100
            ->appends($filters); // Ensures filters are remembered on pagination links

        // 5. Fetch data needed for the filter dropdowns
        $selectedTournamentId = $filters['tournament'];

        // Tournaments — available for all roles
        $tournaments = Tournament::forUser($user)->orderBy('name')->get(['id', 'name']);

        // Actual teams for filter — scoped to tournament if selected
        $filterTeams = $selectedTournamentId
            ? ActualTeam::forTournament($selectedTournamentId)->orderBy('name')->get()
            : ($user->hasRole('Superadmin')
                ? ActualTeam::orderBy('name')->get()
                : ActualTeam::where('organization_id', $user->organization_id)->orderBy('name')->get());

        // Only show dropdown values present among players (scoped by tournament if selected)
        $baseQuery = Player::whereNotNull('user_id')
            ->whereHas('user.roles', fn($q) => $q->where('name', 'player'))
            ->when($selectedTournamentId, function ($q) use ($selectedTournamentId) {
                $tournamentTeamIds = ActualTeam::forTournament($selectedTournamentId)->pluck('id');
                $q->whereIn('actual_team_id', $tournamentTeamIds);
            })
            ->when(!$selectedTournamentId && !$user->hasRole('Superadmin') && $user->organization_id, function ($q) use ($user) {
                $orgTeamIds = ActualTeam::whereHas('tournament', fn($tq) => $tq->where('organization_id', $user->organization_id))->pluck('id');
                $q->whereIn('actual_team_id', $orgTeamIds);
            });

        $roles = PlayerType::whereIn('id', (clone $baseQuery)->whereNotNull('player_type_id')->pluck('player_type_id')->unique())->orderBy('type')->get();
        $battingProfiles = BattingProfile::whereIn('id', (clone $baseQuery)->whereNotNull('batting_profile_id')->pluck('batting_profile_id')->unique())->orderBy('style')->get();
        $bowlingProfiles = BowlingProfile::whereIn('id', (clone $baseQuery)->whereNotNull('bowling_profile_id')->pluck('bowling_profile_id')->unique())->orderBy('style')->get();

        // Actual teams for retain modal (with tournament info, only active/registration tournaments)
        $actualTeams = $user->hasRole('Superadmin')
            ? ActualTeam::with(['tournaments' => fn($q) => $q->whereIn('tournaments.status', ['active', 'registration'])->select('tournaments.id', 'tournaments.name')->orderByDesc('tournaments.created_at')])->orderBy('name')->get(['id', 'name', 'tournament_id'])
            : ActualTeam::with(['tournaments' => fn($q) => $q->whereIn('tournaments.status', ['active', 'registration'])->select('tournaments.id', 'tournaments.name')->orderByDesc('tournaments.created_at')])->where('organization_id', $user->organization_id)->orderBy('name')->get(['id', 'name', 'tournament_id']);

        // 6. Return the view and pass all necessary data
        return view('backend.pages.players.index', [
            'players'         => $players,
            'filterTeams'     => $filterTeams,
            'actualTeams'     => $actualTeams,
            'roles'           => $roles,
            'battingProfiles' => $battingProfiles,
            'bowlingProfiles' => $bowlingProfiles,
            'tournaments'     => $tournaments,
            'breadcrumbs'     => ['title' => __('Players')],
        ]);
    }

    public function create(Request $request): View
    {
        $this->checkAuthorization(Auth::user(), ['player.create']);

        $defaultCountry = config('settings.default_country', '');
        $defaultDialCode = $defaultCountry ? config('countries.dial_codes.' . $defaultCountry, '+971') : '+971';

        $tournaments = Tournament::forUser(auth()->user())->orderBy('name')->get();

        // If tournament is selected, use its field config; otherwise use defaults
        $selectedTournament = null;
        $fieldConfig = PlayerFormConfig::defaultFormFields();
        if ($request->filled('tournament_id')) {
            $selectedTournament = Tournament::with('settings')->find($request->tournament_id);
            if ($selectedTournament?->settings) {
                $fieldConfig = PlayerFormConfig::getFieldConfig($selectedTournament->settings);
            }
        }

        return view('backend.pages.players.create', [
            'teams' => Team::all(),
            'actualTeams' => ActualTeam::all(),
            'kitSizes' => KitSize::all(),
            'locations' => PlayerLocation::all(),
            'battingProfiles' => BattingProfile::all(),
            'bowlingProfiles' => BowlingProfile::all(),
            'playerTypes' => PlayerType::all(),
            'defaultCountry' => $defaultCountry,
            'defaultDialCode' => $defaultDialCode,
            'fieldConfig' => $fieldConfig,
            'tournaments' => $tournaments,
            'selectedTournament' => $selectedTournament,
            'selectedTournamentId' => $selectedTournament?->id,
            'breadcrumbs' => [
                'title' => __('New Player'),
                'items' => [['label' => __('Players'), 'url' => route('admin.players.index')]],
            ],
        ]);
    }


    public function export(Request $request)
    {
        // 1. Authorize the action
        $this->authorize('player.view');

        // 2. Validate the incoming request
        $request->validate([
            'player_ids' => 'required|array',
            'player_ids.*' => 'exists:players,id',
        ]);

        // 3. **FIXED**: Fetch players with ALL their relationships
        $playerIds = $request->input('player_ids');
        $players = Player::with([
            'team',
            'playerType',
            'location',
            'battingProfile',
            'bowlingProfile',
            'kitSize',
            'user.organization' // Also get user and organization info
        ])->whereIn('id', $playerIds)->get();

        // 4. Set up the CSV file response
        $fileName = 'full_players_export_' . date('Y-m-d_H-i-s') . '.csv';
        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        // 5. Create the callback to stream the detailed CSV data
        $callback = function () use ($players) {
            $file = fopen('php://output', 'w');

            // **FIXED**: Add all the new columns to the header
            $columns = [
                'Player ID',
                'Organization',
                'Name',
                'Email',
                'Mobile Number',
                'Cricheroes Number',
                'Status',
                'Player Status', // e.g., Retained
                'Current Team',
                'Team Name (if Others)',
                'Player Role',
                'Batting Style',
                'Bowling Style',
                'Is Wicket Keeper',
                'Jersey Name',
                'Jersey Size',
                'Location',
                'Requires Transportation',
                'No Travel Plan',
                'Travel From',
                'Travel To',
                'Total Matches',
                'Total Runs',
                'Total Wickets',
                'Registered At',
            ];
            fputcsv($file, $columns);

            // Add the comprehensive data row for each player
            foreach ($players as $player) {
                fputcsv($file, [
                    $player->id,
                    $player->user?->organization?->name ?? 'N/A',
                    $player->name,
                    $player->email,
                    $player->mobile_number_full,
                    $player->cricheroes_number_full ?? 'N/A',
                    !is_null($player->welcome_email_sent_at) ? 'Verified' : 'Pending',
                    $player->player_status ?? 'Normal',
                    $player->team?->name ?? 'N/A',
                    $player->team_name_ref ?? '',
                    $player->playerType?->type ?? 'N/A',
                    $player->battingProfile?->style ?? 'N/A',
                    $player->bowlingProfile?->style ?? 'N/A',
                    $player->is_wicket_keeper ? 'Yes' : 'No',
                    $player->jersey_name ?? 'N/A',
                    $player->kitSize?->size ?? 'N/A',
                    $player->location?->name ?? 'N/A',
                    $player->transportation_required ? 'Yes' : 'No',
                    $player->no_travel_plan ? 'Yes' : 'No',
                    $player->travel_date_from ? \Carbon\Carbon::parse($player->travel_date_from)->format('Y-m-d') : '',
                    $player->travel_date_to ? \Carbon\Carbon::parse($player->travel_date_to)->format('Y-m-d') : '',
                    $player->total_matches ?? 0,
                    $player->total_runs ?? 0,
                    $player->total_wickets ?? 0,
                    $player->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        // 6. Return the streamed response
        return response()->stream($callback, 200, $headers);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['player.create']);

        // Get field config from selected tournament
        $fieldConfig = PlayerFormConfig::defaultFormFields();
        if ($request->filled('tournament_id')) {
            $tournament = Tournament::with('settings')->find($request->tournament_id);
            if ($tournament?->settings) {
                $fieldConfig = PlayerFormConfig::getFieldConfig($tournament->settings);
            }
        }

        // Helper: check if a field is visible AND required
        $req = fn($key) => ($fieldConfig[$key]['visible'] ?? true) && ($fieldConfig[$key]['required'] ?? false);

        // Sanitize and combine phone numbers (null if national number is empty)
        $mobileFull = $request->filled('mobile_national_number')
            ? preg_replace('/\D+/', '', (string) $request->input('mobile_country_code') . (string) $request->input('mobile_national_number'))
            : null;
        $cricheroesFull = $request->filled('cricheroes_national_number')
            ? preg_replace('/\D+/', '', (string) $request->input('cricheroes_country_code') . (string) $request->input('cricheroes_national_number'))
            : null;

        $request->merge([
            'mobile_number_full' => $mobileFull,
            'cricheroes_number_full' => $cricheroesFull,
            // Compose the internal full name from first + last.
            'name' => trim($request->input('first_name', '') . ' ' . $request->input('last_name', '')),
        ]);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'date_of_birth' => 'nullable|date|before:today',
            'country' => $req('country') ? 'required|string|max:2' : 'nullable|string|max:2',
            'state' => 'nullable|string|max:100',
            'visa_status' => 'nullable|in:work_visa,visit_visa',
            'visa_expiry' => 'nullable|date|required_if:visa_status,visit_visa',
            'employer_name' => 'nullable|string|max:255|required_if:visa_status,work_visa',
            'employer_address' => 'nullable|string|max:500|required_if:visa_status,work_visa',
            'employer_position' => 'nullable|string|max:255|required_if:visa_status,work_visa',
            'available_saturday' => 'nullable|boolean',
            'available_sunday' => 'nullable|boolean',
            'played_ys_ipl_s1' => 'nullable|boolean',
            'email' => 'required|email|unique:players,email',
            'mobile_country_code' => $req('mobile_number') ? 'required|string|max:10' : 'nullable|string|max:10',
            'mobile_national_number' => $req('mobile_number') ? 'required|string|max:20' : 'nullable|string|max:20',
            'mobile_number_full' => [
                $req('mobile_number') ? 'required' : 'nullable',
                'numeric',
                'digits_between:7,15',
                'unique:players,mobile_number_full',
            ],

            'team_id' => $req('registration_team') ? 'required|exists:teams,id' : 'nullable|exists:teams,id',
            'actual_team_id' => $req('playing_team') ? 'required|string' : 'nullable|string',
            'playing_team_name_ref' => 'nullable|string|max:100',
            'jersey_number' => 'nullable',
            'team_name_ref' => 'nullable|string|max:100',
            'jersey_name' => $req('jersey_name') ? 'required|string|max:50' : 'nullable|string|max:50',
            'kit_size_id' => $req('kit_size') ? 'required|exists:kit_sizes,id' : 'nullable|exists:kit_sizes,id',
            'batting_profile_id' => $req('batting_profile') ? 'required|exists:batting_profiles,id' : 'nullable|exists:batting_profiles,id',
            'bowling_profile_id' => $req('bowling_profile') ? 'required|exists:bowling_profiles,id' : 'nullable|exists:bowling_profiles,id',
            'player_type_id' => $req('player_type') ? 'required|exists:player_types,id' : 'nullable|exists:player_types,id',
            'location_id' => $req('location') ? 'required|exists:player_locations,id' : 'nullable|exists:player_locations,id',
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
            'cricheroes_profile_url' => $req('cricheroes_profile_url') ? 'required|url|max:500' : 'nullable|url|max:500',

            'image_path' => [
                $req('image') ? 'required' : 'nullable',
                'string',
                'max:500',
            ],

            'wicket_keeper' => 'nullable|boolean',
            'need_transportation' => 'nullable|boolean',

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

        // Image path comes pre-processed from AJAX upload (string path)
        // Validate the path exists in storage if provided
        if (!empty($validated['image_path']) && is_string($validated['image_path'])) {
            if (!Storage::disk('public')->exists($validated['image_path'])) {
                unset($validated['image_path']);
            }
        }

        // ✅ Boolean Flags
        $validated['is_wicket_keeper'] = $request->boolean('wicket_keeper');
        $validated['transportation_required'] = $request->boolean('need_transportation');
        $validated['no_travel_plan'] = $request->boolean('no_travel_plan');
        $validated['available_saturday'] = $request->boolean('available_saturday');
        $validated['available_sunday'] = $request->boolean('available_sunday');
        $validated['available_weekends'] = $request->boolean('available_saturday') || $request->boolean('available_sunday');
        $validated['played_ys_ipl_s1'] = $request->boolean('played_ys_ipl_s1');
        $validated['created_by'] = Auth::id();


        // Create or reuse existing user
        $existingUser = User::where('email', $validated['email'])->first();
        $password = null;

        if ($existingUser) {
            $user = $existingUser;
        } else {
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
        }

        // Add user_id to validated data before creating player
        $validated['user_id'] = $user->id;

        // Handle "Other" playing team selection
        if (($validated['actual_team_id'] ?? null) === 'other') {
            $validated['actual_team_id'] = null;
        } else {
            $validated['playing_team_name_ref'] = null;
        }

        // Now create player
        $player = Player::create($validated);

        // Assign player role if needed
        if (!$user->hasRole('player')) {
            $playerRole = Role::firstOrCreate(['name' => 'player']);
            $user->assignRole($playerRole);
        }

        // Send welcome email with credentials (only for new users)
        if ($password) {
            $user->notify(new CustomVerifyEmail($password));
        }



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

        // Load tournament assignments with tournament details
        $tournamentAssignments = \DB::table('player_actual_team_tournament')
            ->join('tournaments', 'tournaments.id', '=', 'player_actual_team_tournament.tournament_id')
            ->join('actual_teams', 'actual_teams.id', '=', 'player_actual_team_tournament.actual_team_id')
            ->where('player_actual_team_tournament.player_id', $player->id)
            ->select(
                'player_actual_team_tournament.*',
                'tournaments.name as tournament_name',
                'actual_teams.name as team_name',
                'actual_teams.team_logo'
            )
            ->get();

        // Load per-tournament statistics
        $tournamentStats = \App\Models\PlayerStatistic::where('player_id', $player->id)
            ->with('tournament')
            ->get()
            ->keyBy('tournament_id');

        // Actual teams for retain modal
        $user = Auth::user();
        $actualTeams = $user->hasRole('Superadmin')
            ? ActualTeam::orderBy('name')->get(['id', 'name', 'tournament_id'])
            : ActualTeam::where('organization_id', $user->organization_id)->orderBy('name')->get(['id', 'name', 'tournament_id']);

        return view('backend.pages.players.show', [
            'player' => $player,
            'teams' => Team::all(),
            'actualTeams' => $actualTeams,
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
            'verifiedProfile' => $player->allFieldsVerified(),
            'tournamentAssignments' => $tournamentAssignments,
            'tournamentStats' => $tournamentStats,
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

        // Load tournament registrations with settings for tournament-aware form
        $tournamentRegistrations = TournamentRegistration::where('player_id', $player->id)
            ->where('type', 'player')
            ->with('tournament.settings')
            ->orderBy('created_at', 'desc')
            ->get();

        // Determine selected tournament from query param or default to latest registration
        $selectedTournamentId = request('tournament');
        $selectedRegistration = $selectedTournamentId
            ? $tournamentRegistrations->firstWhere('tournament_id', $selectedTournamentId)
            : $tournamentRegistrations->first();

        $selectedTournament = $selectedRegistration?->tournament;
        $settings = $selectedTournament?->settings;

        // Use tournament-specific field config when available, else defaults (all visible)
        $fieldConfig = $settings
            ? PlayerFormConfig::getFieldConfig($settings)
            : PlayerFormConfig::defaultFormFields();

        $layout = PlayerFormConfig::getFormLayout($settings, true);

        // Welcome card uses the player's tournament's welcome_card editor template.
        $welcomeRegistration = TournamentRegistration::where('player_id', $player->id)
            ->whereNotNull('tournament_id')
            ->latest()
            ->with('tournament.settings')
            ->first();
        $hasWelcomeTemplate = (bool) $welcomeRegistration?->tournament?->getTemplate(TournamentTemplate::TYPE_WELCOME_CARD);

        return view('backend.pages.players.edit', [
            'player' => $player,
            'layout' => $layout,
            'selectedTournament' => $selectedTournament,
            'teams' => Team::all(),
            'actualTeams' => ActualTeam::with('tournament')->orderBy('name')->get(),
            'locations' => PlayerLocation::all(),
            'kitSizes' => KitSize::all(),
            'battingProfiles' => BattingProfile::all(),
            'bowlingProfiles' => BowlingProfile::all(),
            'playerTypes' => PlayerType::all(),
            'countries' => config('countries.list', []),
            'visaList' => config('registration.visa_statuses', []),
            'tshirtOptions' => PlayerFormConfig::sizeOptions('tshirt_sizes', PlayerFormConfig::defaultTshirtSizes()),
            'pantOptions' => PlayerFormConfig::sizeOptions('pant_sizes', PlayerFormConfig::defaultPantSizes()),
            'battingModes' => ['Aggressive Batsman', 'Defensive Batsman', 'Finisher', 'Anchor', 'Power Hitter'],
            'battingPositions' => ['Opener', '3', '4', '5', '6', '7', '8', "I'm Flexible"],
            'templates' => ImageTemplate::all(),
            'welcomeRegistration' => $welcomeRegistration,
            'hasWelcomeTemplate' => $hasWelcomeTemplate,
            'defaultCountry' => config('settings.default_country', ''),
            'fieldConfig' => $fieldConfig,
            'breadcrumbs' => [
                'title' => __('Edit Player'),
                'items' => [
                    ['label' => __('Players'), 'url' => route('admin.players.index')],
                ],
            ],
            'verifiedFields' => $verifiedFields,
            'verifiedProfile' => $player->allFieldsVerified(),
            'tournamentRegistrations' => $tournamentRegistrations,
            'actionLogs' => ActionLog::where('data', 'like', '%"player_id":' . $player->id . '%')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(),
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
        $outputPath = 'public/generated_welcome/' . \App\Services\Poster\TemplateRenderService::posterFilename('welcome-' . Str::slug($player->name));
        Storage::makeDirectory('public/generated_welcome');
        imagepng($image, storage_path('app/' . $outputPath));
        imagedestroy($image);

        return 'storage/' . Str::after($outputPath, 'public/');
    }





    public function editor(Player $player)
    {
        $breadcrumbs = ['title' => __('Player Image Editor')];

        return view('backend.pages.players.image-editor', compact('player', 'breadcrumbs'));
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

        \Log::info('PLAYER_UPDATE', [
            'player_id' => $player->id,
            'actual_team_id' => $request->input('actual_team_id'),
            'playing_team_name_ref' => $request->input('playing_team_name_ref'),
            'tournament_context' => $request->input('tournament_context'),
        ]);

        // Use tournament-specific field config when a tournament context is provided,
        // otherwise fall back to defaults (all visible).
        $tournamentId = $request->input('tournament_context');
        $settings = $tournamentId
            ? \App\Models\TournamentSetting::where('tournament_id', $tournamentId)->first()
            : null;
        $fieldConfig = $settings
            ? PlayerFormConfig::getFieldConfig($settings)
            : PlayerFormConfig::defaultFormFields();

        // Helper: check if a field is visible AND required per config
        $req = fn($key) => ($fieldConfig[$key]['visible'] ?? true) && ($fieldConfig[$key]['required'] ?? false);

        // Compose the internal full name from first + last.
        $request->merge(['name' => trim($request->input('first_name', '') . ' ' . $request->input('last_name', ''))]);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'date_of_birth' => 'nullable|date|before:today',
            'country' => 'nullable|string|max:2',
            'state' => 'nullable|string|max:100',
            'visa_status' => 'nullable|in:work_visa,visit_visa',
            'visa_expiry' => 'nullable|date|required_if:visa_status,visit_visa',
            'employer_name' => 'nullable|string|max:255|required_if:visa_status,work_visa',
            'employer_address' => 'nullable|string|max:500|required_if:visa_status,work_visa',
            'employer_position' => 'nullable|string|max:255|required_if:visa_status,work_visa',
            'available_saturday' => 'nullable|boolean',
            'available_sunday' => 'nullable|boolean',
            'played_ys_ipl_s1' => 'nullable|boolean',
            'email' => 'required|email|unique:players,email,' . $player->id,
            'mobile_number_full' => $req('mobile_number') ? 'required|string|max:20' : 'nullable|string|max:20',
            'cricheroes_number_full' => 'nullable|string|max:20',
            'cricheroes_profile_url' => $req('cricheroes_profile_url') ? 'required|url|max:500' : 'nullable|url|max:500',
            'jersey_number' => 'nullable',
            'tshirt_size' => 'nullable|string|max:50',
            'tshirt_size_custom' => 'nullable|string|max:50',
            'pant_size' => 'nullable|string|max:50',
            'pant_size_custom' => 'nullable|string|max:50',

            'team_id' => 'nullable|exists:teams,id',
            'actual_team_id' => $req('playing_team') ? 'required|string' : 'nullable|string',
            'playing_team_name_ref' => 'nullable|string|max:100',
            'location_id' => $req('location') ? 'required|exists:player_locations,id' : 'nullable|exists:player_locations,id',
            'total_matches' => 'nullable|integer|min:0',
            'total_runs' => 'nullable|integer|min:0',
            'total_wickets' => 'nullable|integer|min:0',
            'team_name_ref' => 'nullable|string|max:100',
            'jersey_name' => $req('jersey_name') ? 'required|string|max:50' : 'nullable|string|max:50',
            'kit_size_id' => $req('kit_size') ? 'required|exists:kit_sizes,id' : 'nullable|exists:kit_sizes,id',
            'batting_profile_id' => $req('batting_profile') ? 'required|exists:batting_profiles,id' : 'nullable|exists:batting_profiles,id',
            'bowling_profile_id' => $req('bowling_profile') ? 'required|exists:bowling_profiles,id' : 'nullable|exists:bowling_profiles,id',
            'player_type_id' => $req('player_type') ? 'required|exists:player_types,id' : 'nullable|exists:player_types,id',
            'image_path' => 'nullable|string|max:500',
            'is_wicket_keeper' => 'sometimes|boolean',
            'transportation_mode' => 'nullable|in:self,required',
            'has_travel_plan' => 'nullable|in:no,yes',
            'travel_date_from' => 'nullable|date',
            'travel_date_to' => 'nullable|date|after_or_equal:travel_date_from',
            'player_mode' => 'nullable|in:normal,retained',
            'retained_value' => 'nullable|numeric|min:0',
            'batting_mode' => 'nullable|in:Aggressive Batsman,Defensive Batsman,Finisher,Anchor,Power Hitter',
            'preferred_batting_positions' => 'nullable|array|max:3',
            'preferred_batting_positions.*' => "in:Opener,3,4,5,6,7,8,I'm Flexible",
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

        // If clear image checkbox was checked
        if ($request->input('clear_image') && empty($validated['image_path'])) {
            if ($player->image_path) {
                Storage::delete('public/' . $player->image_path);
            }
            $player->image_path = null;
        }


        // Resolve "Other" size selections to the custom value
        if (($validated['tshirt_size'] ?? null) === 'Other' && !empty($validated['tshirt_size_custom'])) {
            $validated['tshirt_size'] = $validated['tshirt_size_custom'];
        }
        if (($validated['pant_size'] ?? null) === 'Other' && !empty($validated['pant_size_custom'])) {
            $validated['pant_size'] = $validated['pant_size_custom'];
        }
        unset($validated['tshirt_size_custom'], $validated['pant_size_custom']);

        // ✅ Assign validated fields
        $player->fill([
            'name' => $validated['name'],
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'country' => $validated['country'] ?? null,
            'state' => $validated['state'] ?? null,
            'visa_status' => $validated['visa_status'] ?? null,
            'visa_expiry' => $validated['visa_expiry'] ?? null,
            'employer_name' => $validated['employer_name'] ?? null,
            'employer_address' => $validated['employer_address'] ?? null,
            'employer_position' => $validated['employer_position'] ?? null,
            'available_saturday' => $request->boolean('available_saturday'),
            'available_sunday' => $request->boolean('available_sunday'),
            'available_weekends' => $request->boolean('available_saturday') || $request->boolean('available_sunday'),
            'played_ys_ipl_s1' => $request->boolean('played_ys_ipl_s1'),
            'email' => $validated['email'],
            'total_matches' => $validated['total_matches'] ?? null,
            'total_runs' => $validated['total_runs'] ?? null,
            'total_wickets' => $validated['total_wickets'] ?? null,
            'location_id' => $validated['location_id'] ?? null,
            'team_name_ref' => $validated['team_name_ref'] ?? null,

            'mobile_number_full' => $validated['mobile_number_full'] ?? null,
            'cricheroes_number_full' => $validated['cricheroes_number_full'] ?? null,
            'cricheroes_profile_url' => $validated['cricheroes_profile_url'] ?? null,
            'team_id' => $validated['team_id'] ?? null,
            'actual_team_id' => ($validated['actual_team_id'] ?? null) === 'other' ? null : ($validated['actual_team_id'] ?? null),
            'playing_team_name_ref' => ($validated['actual_team_id'] ?? null) === 'other'
                ? ($validated['playing_team_name_ref'] ?? null)
                : null,
            'jersey_name' => $validated['jersey_name'] ?? null,
            'jersey_number' => $validated['jersey_number'] ?? null,
            'tshirt_size' => $validated['tshirt_size'] ?? null,
            'pant_size' => $validated['pant_size'] ?? null,
            'kit_size_id' => $validated['kit_size_id'] ?? null,
            'batting_profile_id' => $validated['batting_profile_id'] ?? null,
            'bowling_profile_id' => $validated['bowling_profile_id'] ?? null,
            'player_type_id' => $validated['player_type_id'] ?? null,
            'is_wicket_keeper' => $request->boolean('is_wicket_keeper'),
            'transportation_required' => $request->input('transportation_mode') === 'required',
            'no_travel_plan' => $request->input('has_travel_plan') !== 'yes',
            'travel_date_from' => $request->input('has_travel_plan') === 'yes' ? ($validated['travel_date_from'] ?? null) : null,
            'travel_date_to' => $request->input('has_travel_plan') === 'yes' ? ($validated['travel_date_to'] ?? null) : null,
            'player_mode' => $validated['player_mode'] ?? $player->player_mode,
            'retained_value' => ($validated['player_mode'] ?? null) === 'retained' ? $validated['retained_value'] : null,
            'batting_mode' => $validated['batting_mode'] ?? null,
            'preferred_batting_positions' => $validated['preferred_batting_positions'] ?? null,
        ]);

        // ✅ Assign verified flags
        // ✅ Assign verified flags
        $player->verified_name = $request->boolean('verified_name');
        $player->verified_jersey_number = $request->boolean('verified_jersey_number');

        $player->verified_email = $request->boolean('verified_email');
        $player->verified_mobile_number_full = $request->boolean('verified_mobile_number_full');
        $player->verified_image_path = $request->boolean('verified_image_path');
        $player->verified_cricheroes_number_full = $request->boolean('verified_cricheroes_number_full');
        $player->verified_cricheroes_profile_url = $request->boolean('verified_cricheroes_profile_url');
        $player->verified_team_id = $request->boolean('verified_team_id');
        $player->verified_jersey_name = $request->boolean('verified_jersey_name');
        $player->verified_kit_size_id = $request->boolean('verified_kit_size_id');
        $player->verified_batting_profile_id = $request->boolean('verified_batting_profile_id');
        $player->verified_bowling_profile_id = $request->boolean('verified_bowling_profile_id');
        $player->verified_player_type_id = $request->boolean('verified_player_type_id');
        $player->verified_is_wicket_keeper = $request->boolean('verified_is_wicket_keeper');
        $player->verified_transportation_required = $request->boolean('verified_transportation_required');
        $player->verified_no_travel_plan = $request->boolean('verified_no_travel_plan');
        $player->verified_country = $request->boolean('verified_country');

        // Track changed fields before saving
        $changedFields = $player->getDirty();
        // Exclude verified_* flags from the change log display
        $fieldChanges = array_filter($changedFields, fn($k) => !str_starts_with($k, 'verified_'), ARRAY_FILTER_USE_KEY);

        $player->save();

        // Log the update with changed fields
        if (!empty($fieldChanges)) {
            $this->logAction("Player Updated: {$player->name}", $player, [
                'player_id' => $player->id,
                'changed_fields' => array_keys($fieldChanges),
            ]);
        }


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

            // Use the player's tournament's welcome_card editor template.
            $registration = TournamentRegistration::where('player_id', $player->id)
                ->whereNotNull('tournament_id')
                ->latest()
                ->first();

            if (! $registration) {
                return redirect()->back()->with('error', 'This player is not linked to a tournament, so no welcome card can be generated.');
            }
            if (! $registration->tournament?->getTemplate(TournamentTemplate::TYPE_WELCOME_CARD)) {
                return redirect()->back()->with('error', 'No welcome_card template for this tournament — create one first.');
            }

            $sent = app(TournamentNotificationService::class)->sendWelcomeCard($registration, true);

            if (! $sent) {
                return redirect()->back()->with('error', 'Could not generate the welcome card. Check the welcome_card template and try again.');
            }

            $player->update(['welcome_email_sent_at' => now()]);
            return redirect()->back()->with('success', 'Player - Welcome card created and intimated.');
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

    public function retain(Request $request, Player $player): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['player.edit']);

        if ($player->status !== 'approved') {
            return redirect()->back()->with('error', 'Only approved players can be retained.');
        }

        $request->validate([
            'actual_team_id' => 'required|exists:actual_teams,id',
            'retained_value' => 'required|numeric|min:0',
        ]);

        $player->update([
            'player_mode' => 'retained',
            'actual_team_id' => $request->actual_team_id,
            'retained_value' => $request->retained_value,
        ]);

        // Add to squad count via pivot table
        $team = ActualTeam::find($request->actual_team_id);
        if ($team) {
            $tournamentId = $team->tournament_id ?? $team->tournaments()->first()?->id;
            if ($tournamentId) {
                \DB::table('player_actual_team_tournament')->updateOrInsert(
                    ['player_id' => $player->id, 'tournament_id' => $tournamentId],
                    ['actual_team_id' => $team->id, 'updated_at' => now(), 'created_at' => now()]
                );
            }

            // Sync to actual_team_users so retained players appear on public teams page
            if ($player->user_id) {
                \DB::table('actual_team_users')->updateOrInsert(
                    ['actual_team_id' => $team->id, 'user_id' => $player->user_id],
                    ['role' => 'Player', 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }

        return redirect()->back()->with('success', $player->name . ' has been retained successfully.');
    }

    public function unretain(Player $player): RedirectResponse
    {
        $this->checkAuthorization(Auth::user(), ['player.edit']);

        $teamId = $player->actual_team_id;
        $team = $teamId ? ActualTeam::find($teamId) : null;

        $player->update([
            'player_mode' => 'normal',
            'actual_team_id' => null,
            'retained_value' => null,
        ]);

        // Remove from squad count pivot
        $unretainTournamentId = $team ? ($team->tournament_id ?? $team->tournaments()->first()?->id) : null;
        if ($team && $unretainTournamentId) {
            \DB::table('player_actual_team_tournament')
                ->where('player_id', $player->id)
                ->where('tournament_id', $unretainTournamentId)
                ->delete();
        }

        // Remove from actual_team_users so player no longer appears on public teams page
        if ($team && $player->user_id) {
            \DB::table('actual_team_users')
                ->where('actual_team_id', $team->id)
                ->where('user_id', $player->user_id)
                ->delete();
        }

        return redirect()->back()->with('success', 'Retention removed for ' . $player->name . '.');
    }
}

<?php

namespace App\Http\Controllers\Backend;

use App\Helpers\PlayerFormConfig;
use App\Http\Controllers\Controller;
use App\Models\ActualTeam;
use App\Models\Auction;
use App\Models\Player;
use App\Models\TournamentRegistration;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\NewPlayerAddedMail;
use App\Mail\PlayerApprovedMail;
use App\Models\BattingProfile;
use App\Models\BowlingProfile;
use App\Models\KitSize;
use App\Models\PlayerLocation;
use App\Models\PlayerType;
use App\Models\TournamentRegistration as TournamentRegistrationModel;
use App\Models\TournamentTemplate;
use App\Models\Wishlist;
use App\Services\Poster\TemplateRenderService;
use Illuminate\Support\Facades\Storage;
use App\Notifications\GeneralNotification;

class TeamManagerController extends Controller
{
    /**
     * Resolve the selected team from session, falling back to the first team.
     */
    private function selectedTeam(User $user): ?ActualTeam
    {
        $teams = $user->actualTeams()->get();
        if ($teams->isEmpty()) {
            return null;
        }
        if ($selectedId = session('selected_team_id')) {
            return $teams->firstWhere('id', $selectedId) ?? $teams->first();
        }
        return $teams->first();
    }

    /**
     * Team Manager Dashboard - Shows their team, players, and upcoming auctions
     */
    public function dashboard(Request $request)
    {
        $user = Auth::user();

        // Get the team(s) this manager is assigned to
        $teams = $user->actualTeams()
            ->with(['tournament.organization', 'players', 'auctionPlayers'])
            ->get();

        if ($teams->isEmpty()) {
            $breadcrumbs = ['title' => __('No Team Assigned')];
            return view('backend.pages.team-manager.no-team', compact('breadcrumbs'));
        }

        // Get the selected team from query param, or session, or default to first
        if ($request->has('team')) {
            $team = $teams->firstWhere('id', $request->query('team')) ?? $teams->first();
        } elseif (session('selected_team_id')) {
            $team = $teams->firstWhere('id', session('selected_team_id')) ?? $teams->first();
        } else {
            $team = $teams->first();
        }

        // Store selected team in session for other pages (matches, players, etc.)
        session(['selected_team_id' => $team->id]);

        // Only count players who are on the roster (player_actual_team_tournament pivot)
        // Use withoutOrganizationScope() so retained players with NULL org_id are included
        $teamPlayers = Player::withoutOrganizationScope()->where('actual_team_id', $team->id)
            ->whereExists(function ($q) use ($team) {
                $q->select(\DB::raw(1))
                  ->from('player_actual_team_tournament')
                  ->whereColumn('player_actual_team_tournament.player_id', 'players.id')
                  ->where('player_actual_team_tournament.actual_team_id', $team->id);
            })
            ->with(['playerType', 'battingProfile', 'bowlingProfile', 'kitSize', 'location'])
            ->orderBy('name')
            ->get();

        // Get upcoming auctions for all tournaments this team belongs to
        $teamTournamentIds = $team->tournaments()->pluck('tournaments.id');
        if ($team->tournament_id) {
            $teamTournamentIds = $teamTournamentIds->push($team->tournament_id)->unique();
        }
        $upcomingAuctions = collect();
        if ($teamTournamentIds->isNotEmpty()) {
            $upcomingAuctions = Auction::whereIn('tournament_id', $teamTournamentIds)
                ->whereIn('status', ['scheduled', 'running', 'paused'])
                ->with('tournament')
                ->get();
        }

        // Get available players that can be added to the team
        // Players not assigned to any team and not retained
        $availablePlayers = Player::whereNull('actual_team_id')
            ->where(function ($query) {
                $query->whereNull('player_mode')
                      ->orWhere('player_mode', '!=', 'retained');
            })
            ->orderBy('name')
            ->get();

        // Calculate budget info for active auctions
        $auctionBudgets = [];
        foreach ($upcomingAuctions as $auction) {
            $soldSpent = $team->auctionPlayers()
                ->where('auction_id', $auction->id)
                ->where('status', 'sold')
                ->sum('final_price');

            $retainedSpent = Player::where('actual_team_id', $team->id)
                ->where('player_mode', 'retained')
                ->whereNotNull('retained_value')
                ->sum('retained_value');

            $spent = $soldSpent + $retainedSpent;

            $auctionBudgets[$auction->id] = [
                'max' => $auction->max_budget_per_team ?? 0,
                'spent' => $spent,
                'remaining' => ($auction->max_budget_per_team ?? 0) - $spent,
            ];
        }

        // Team members from pivot table (for captain/owner management)
        $teamMembers = $team->users()->get();
        $currentUserPivot = $teamMembers->firstWhere('id', $user->id);
        $isCaptain = $currentUserPivot && strtolower($currentUserPivot->pivot->role) === 'captain';
        $isOwner = $currentUserPivot && $currentUserPivot->pivot->role === 'Owner';
        $isManager = $currentUserPivot && $currentUserPivot->pivot->role === 'Manager';

        // Auction teams don't manually build a roster — their squad comes from the
        // auction — so the "select/add player" and roster-management UI is hidden.
        $isAuctionTeam = optional($team->tournament)->isAuction()
            || $team->tournaments->contains(fn ($t) => $t->isAuction());

        $managerIsPlayer = (bool) $user->player;

        // Fetch tournament registration statuses for the manager's player record
        $managerRegistrations = collect();
        if ($managerIsPlayer && $user->player) {
            $managerRegistrations = TournamentRegistration::where('player_id', $user->player->id)
                ->where('type', 'player')
                ->with('tournament')
                ->latest()
                ->get();
        }

        return view('backend.pages.team-manager.dashboard', compact(
            'teams',
            'team',
            'teamPlayers',
            'upcomingAuctions',
            'availablePlayers',
            'auctionBudgets',
            'teamMembers',
            'isCaptain',
            'isOwner',
            'isManager',
            'isAuctionTeam',
            'managerIsPlayer',
            'managerRegistrations'
        ));
    }

    /**
     * Show form to create a new player for the team
     */
    public function createPlayer()
    {
        $user = Auth::user();
        $teams = $user->actualTeams()->with(['tournament.organization', 'tournaments.settings'])->get();

        if ($teams->isEmpty()) {
            return redirect()->route('team-manager.dashboard')
                ->with('error', 'You are not assigned to any team.');
        }

        // Default to selected team from session, or first team
        $team = $teams->firstWhere('id', session('selected_team_id')) ?? $teams->first();

        // Collect all effective tournaments across all manager's teams
        $effectiveTournaments = $teams->flatMap(function ($t) {
            return $t->effectiveTournaments;
        })->unique('id')->values();

        $locations = PlayerLocation::orderBy('name')->get();
        $kitSizes = KitSize::all();
        $battingProfiles = BattingProfile::all();
        $bowlingProfiles = BowlingProfile::all();
        $playerTypes = PlayerType::all();

        $defaultCountry = config('settings.default_country', '');

        // Get field config — use effective tournament settings (fallback for multi-tournament teams)
        $tournamentSettings = $team->tournaments->first()?->settings
            ?? $team->tournament?->settings;
        $fieldConfig = PlayerFormConfig::getFieldConfig($tournamentSettings);

        $breadcrumbs = ['title' => __('Create Player')];

        return view('backend.pages.team-manager.create-player', compact(
            'teams', 'team', 'effectiveTournaments', 'locations', 'kitSizes', 'battingProfiles', 'bowlingProfiles', 'playerTypes', 'defaultCountry', 'fieldConfig', 'breadcrumbs'
        ));
    }

    /**
     * Store a new player created by team manager
     */
    public function storePlayer(Request $request)
    {
        $user = Auth::user();
        $teams = $user->actualTeams()->with(['tournament.organization', 'tournaments.settings'])->get();

        if ($teams->isEmpty()) {
            return redirect()->route('team-manager.dashboard')
                ->with('error', 'You are not assigned to any team.');
        }

        // Use selected team from form, or session, or first
        $team = $teams->firstWhere('id', $request->input('team_id'))
            ?? $teams->firstWhere('id', session('selected_team_id'))
            ?? $teams->first();

        // Get field config — use effective tournament settings (fallback for multi-tournament teams)
        $tournamentSettings = $team->tournaments->first()?->settings
            ?? $team->tournament?->settings;
        $fieldConfig = PlayerFormConfig::getFieldConfig($tournamentSettings);
        $rules = PlayerFormConfig::buildValidationRules($fieldConfig, 'team_manager', $tournamentSettings);
        // Override email to allow nullable + unique for team manager context
        $rules['email'] = 'nullable|email|unique:players,email';
        // Image field name is image_path in team manager form (pre-processed string path)
        unset($rules['image']);
        $rules['image_path'] = 'nullable|string|max:500';

        // Additional validation for team/tournament selection
        $request->validate([
            'team_id' => 'nullable|exists:actual_teams,id',
            'tournament_ids' => 'nullable|array',
            'tournament_ids.*' => 'exists:tournaments,id',
        ]);

        $validated = $request->validate($rules);

        $data = [
            'name' => $validated['name'],
            'country' => $validated['country'] ?? null,
            'email' => $validated['email'] ?? null,
            'mobile_number_full' => $validated['mobile_number_full'] ?? null,
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
            'created_by' => $user->id,
        ];

        // Handle image — pre-processed path from AJAX upload
        if (!empty($validated['image_path']) && is_string($validated['image_path'])
            && Storage::disk('public')->exists($validated['image_path'])) {
            $data['image_path'] = $validated['image_path'];
        }

        $player = Player::create($data);

        // Create player_actual_team_tournament pivot entries for selected tournaments
        $tournamentIds = $request->input('tournament_ids', []);
        if (!empty($tournamentIds)) {
            foreach ($tournamentIds as $tournamentId) {
                DB::table('player_actual_team_tournament')->insert([
                    'player_id' => $player->id,
                    'actual_team_id' => $team->id,
                    'tournament_id' => $tournamentId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Send welcome email to player if they have an email address
        if ($player->email) {
            try {
                $addedByPhone = TournamentRegistration::where('actual_team_id', $team->id)->first()?->captain_phone;
                Mail::to($player->email)->send(new NewPlayerAddedMail($player, $team, $user, $addedByPhone));
            } catch (\Exception $e) {
                // Log the error but don't fail the player creation
                \Log::error('Failed to send new player email: ' . $e->getMessage());
            }
        }

        // In-app notification to Owner/Manager (if different from current user)
        $dashboardUrl = route('team-manager.dashboard');
        $teamMembers = $team->users()->get();
        foreach ($teamMembers as $member) {
            $role = $member->pivot->role;
            if (in_array($role, ['Owner', 'Manager']) && $member->id !== $user->id) {
                $member->notify(new GeneralNotification(
                    "{$player->name} was added to {$team->name} by {$user->name}",
                    $dashboardUrl,
                    'player-added'
                ));
            }
        }

        return redirect()->route('team-manager.dashboard')
            ->with('success', 'Player created and added to your team successfully.' . ($player->email ? ' Welcome email sent.' : ''));
    }

    /**
     * Add an existing player to the team roster
     */
    public function addPlayerToRoster(Request $request)
    {
        $user = Auth::user();
        $team = $this->selectedTeam($user);

        if (!$team) {
            return redirect()->route('team-manager.dashboard')
                ->with('error', 'You are not assigned to any team.');
        }

        $validated = $request->validate([
            'player_id' => 'required|exists:players,id',
        ]);

        $player = Player::findOrFail($validated['player_id']);

        // Verify player is not already assigned to a team
        if ($player->actual_team_id !== null) {
            return redirect()->route('team-manager.dashboard')
                ->with('error', 'This player is already assigned to a team.');
        }

        // Add player to team
        $player->update([
            'actual_team_id' => $team->id,
            'player_mode' => 'normal',
        ]);

        return redirect()->route('team-manager.dashboard')
            ->with('success', 'Player added to your team successfully.');
    }

    /**
     * Remove a player from the team roster
     */
    public function removePlayerFromRoster(Request $request, Player $player)
    {
        $user = Auth::user();
        $team = $this->selectedTeam($user);

        if (!$team) {
            return redirect()->route('team-manager.dashboard')
                ->with('error', 'You are not assigned to any team.');
        }

        // Verify player belongs to this team
        if ($player->actual_team_id !== $team->id) {
            return redirect()->route('team-manager.dashboard')
                ->with('error', 'This player is not on your team.');
        }

        // Check if player was sold in an auction (don't allow removal)
        $soldInAuction = DB::table('auction_players')
            ->where('player_id', $player->id)
            ->where('team_id', $team->id)
            ->where('status', 'sold')
            ->exists();

        if ($soldInAuction) {
            return redirect()->route('team-manager.dashboard')
                ->with('error', 'Cannot remove a player that was acquired through auction.');
        }

        // Remove player from team
        $player->update([
            'actual_team_id' => null,
            'player_mode' => 'normal',
        ]);

        return redirect()->route('team-manager.dashboard')
            ->with('success', 'Player removed from your team.');
    }

    /**
     * View team details
     */
    public function viewTeam()
    {
        $user = Auth::user();
        $team = $user->actualTeams()
            ->with(['tournament.organization', 'players', 'users'])
            ->first();

        if (!$team) {
            return redirect()->route('team-manager.dashboard')
                ->with('error', 'You are not assigned to any team.');
        }

        return view('backend.pages.team-manager.team-details', compact('team'));
    }

    /**
     * List auctions the team can participate in
     */
    public function auctions()
    {
        $user = Auth::user();
        $team = $user->actualTeams()->with('tournament')->first();

        if (!$team) {
            return redirect()->route('team-manager.dashboard')
                ->with('error', 'You are not assigned to any team.');
        }

        $teamTournamentIds = $team->tournaments()->pluck('tournaments.id');
        if ($team->tournament_id) {
            $teamTournamentIds = $teamTournamentIds->push($team->tournament_id)->unique();
        }
        $auctions = Auction::whereIn('tournament_id', $teamTournamentIds)
            ->with(['tournament', 'auctionPlayers' => function ($query) use ($team) {
                $query->where('team_id', $team->id)->where('status', 'sold');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        // Add budget info to each auction
        foreach ($auctions as $auction) {
            $soldSpent = $team->auctionPlayers()
                ->where('auction_id', $auction->id)
                ->where('status', 'sold')
                ->sum('final_price');

            $retainedSpent = Player::where('actual_team_id', $team->id)
                ->where('player_mode', 'retained')
                ->whereNotNull('retained_value')
                ->sum('retained_value');

            $spent = $soldSpent + $retainedSpent;

            $auction->budget_info = [
                'max' => $auction->max_budget_per_team ?? 0,
                'spent' => $spent,
                'remaining' => ($auction->max_budget_per_team ?? 0) - $spent,
            ];
        }

        $breadcrumbs = ['title' => __('My Auctions')];

        return view('backend.pages.team-manager.auctions', compact('team', 'auctions', 'breadcrumbs'));
    }

    /**
     * Verify a player with password confirmation
     */
    public function verifyPlayer(Request $request, Player $player)
    {
        $user = Auth::user();
        $team = $this->selectedTeam($user);

        if (!$team) {
            return redirect()->route('team-manager.dashboard')
                ->with('error', 'You are not assigned to any team.');
        }

        // Verify player belongs to this team
        if ($player->actual_team_id !== $team->id) {
            return redirect()->route('team-manager.dashboard')
                ->with('error', 'This player is not on your team.');
        }

        // Validate password
        $request->validate([
            'password' => 'required|string',
        ]);

        // Check if password matches the team manager's password
        if (!Hash::check($request->password, $user->password)) {
            return redirect()->route('team-manager.dashboard')
                ->with('error', 'Invalid password. Player verification failed.');
        }

        // Update player status to approved
        $player->update([
            'status' => 'approved',
            'approved_by' => $user->id,
        ]);

        // Generate welcome card using tournament template
        $welcomeCardPath = null;
        try {
            $team->load('tournament.settings');
            $tournament = $team->tournament;

            if ($tournament) {
                $template = $tournament->getTemplate(TournamentTemplate::TYPE_WELCOME_CARD);

                if ($template && $template->background_image) {
                    $renderService = new TemplateRenderService();

                    $data = [
                        'player_name' => $player->name,
                        'jersey_name' => $player->jersey_name ?? '',
                        'jersey_number' => $player->jersey_number ?? '',
                        'player_image' => $player->image_path ?? '',
                        'player_type' => $player->playerType->type ?? '',
                        'batting_style' => $player->battingProfile->style ?? '',
                        'bowling_style' => $player->bowlingProfile->style ?? '',
                        'team_name' => $team->name,
                        'team_logo' => $team->team_logo ?? '',
                        'tournament_name' => $tournament->name,
                        'tournament_logo' => $tournament->settings->logo ?? '',
                    ];

                    $outputPath = $renderService->renderAndSave(
                        $template,
                        $data,
                        \App\Services\Poster\TemplateRenderService::posterFilename('welcome-' . \Illuminate\Support\Str::slug($player->name))
                    );

                    if ($outputPath && Storage::disk('public')->exists($outputPath)) {
                        $welcomeCardPath = Storage::disk('public')->path($outputPath);
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error('Failed to generate welcome card: ' . $e->getMessage());
        }

        // Send approval email to player (with welcome card if available)
        if ($player->email) {
            try {
                $team->load('tournament.settings');
                Mail::to($player->email)->send(new PlayerApprovedMail($player, $team, $welcomeCardPath));

                // Mark welcome email as sent if card was attached
                if ($welcomeCardPath) {
                    $player->update(['welcome_email_sent_at' => now()]);
                }
            } catch (\Exception $e) {
                \Log::error('Failed to send player approved email: ' . $e->getMessage());
            }
        }

        return redirect()->route('team-manager.dashboard')
            ->with('success', 'Player "' . $player->name . '" has been verified successfully.' . ($player->email ? ' Approval email sent.' : ''));
    }

    /**
     * Reject a pending player
     */
    public function rejectPlayer(Player $player)
    {
        $user = Auth::user();
        $team = $this->selectedTeam($user);

        if (!$team) {
            return redirect()->route('team-manager.dashboard')
                ->with('error', 'You are not assigned to any team.');
        }

        if ($player->actual_team_id !== $team->id) {
            return redirect()->route('team-manager.dashboard')
                ->with('error', 'This player is not on your team.');
        }

        $player->update(['status' => 'rejected']);

        return redirect()->route('team-manager.dashboard')
            ->with('success', 'Player "' . $player->name . '" has been rejected.');
    }

    /**
     * Resend welcome/approval email to an approved player
     */
    public function resendWelcomeEmail(Player $player)
    {
        $user = Auth::user();
        $team = $this->selectedTeam($user);

        if (!$team) {
            return redirect()->route('team-manager.dashboard')
                ->with('error', 'You are not assigned to any team.');
        }

        if ($player->actual_team_id !== $team->id) {
            return redirect()->route('team-manager.dashboard')
                ->with('error', 'This player is not on your team.');
        }

        if (!$player->email) {
            return redirect()->route('team-manager.dashboard')
                ->with('error', 'Player does not have an email address.');
        }

        // Generate welcome card using tournament template
        $welcomeCardPath = null;
        try {
            $team->load('tournament.settings');
            $tournament = $team->tournament;

            if ($tournament) {
                $template = $tournament->getTemplate(TournamentTemplate::TYPE_WELCOME_CARD);

                if ($template && $template->background_image) {
                    $renderService = new TemplateRenderService();

                    $data = [
                        'player_name' => $player->name,
                        'jersey_name' => $player->jersey_name ?? '',
                        'jersey_number' => $player->jersey_number ?? '',
                        'player_image' => $player->image_path ?? '',
                        'player_type' => $player->playerType->type ?? '',
                        'batting_style' => $player->battingProfile->style ?? '',
                        'bowling_style' => $player->bowlingProfile->style ?? '',
                        'team_name' => $team->name,
                        'team_logo' => $team->team_logo ?? '',
                        'tournament_name' => $tournament->name,
                        'tournament_logo' => $tournament->settings->logo ?? '',
                    ];

                    $outputPath = $renderService->renderAndSave(
                        $template,
                        $data,
                        \App\Services\Poster\TemplateRenderService::posterFilename('welcome-' . \Illuminate\Support\Str::slug($player->name))
                    );

                    if ($outputPath && Storage::disk('public')->exists($outputPath)) {
                        $welcomeCardPath = Storage::disk('public')->path($outputPath);
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error('Failed to generate welcome card for resend: ' . $e->getMessage());
        }

        // Send the approval email
        try {
            $team->load('tournament.settings');
            Mail::to($player->email)->send(new PlayerApprovedMail($player, $team, $welcomeCardPath));

            $player->update(['welcome_email_sent_at' => now()]);

            return redirect()->route('team-manager.dashboard')
                ->with('success', 'Welcome email resent to ' . $player->name . '.');
        } catch (\Exception $e) {
            \Log::error('Failed to resend welcome email: ' . $e->getMessage());
            return redirect()->route('team-manager.dashboard')
                ->with('error', 'Failed to send email. Please try again later.');
        }
    }

    /**
     * Show tournament selector so the manager can register as a player
     * via the public registration form (with their data prefilled).
     */
    public function registerAsPlayer()
    {
        $user = Auth::user();

        if ($user->player) {
            return redirect()->route('team-manager.dashboard')
                ->with('info', 'You are already registered as a player.');
        }

        $teams = $user->actualTeams()->with(['tournament', 'tournaments'])->get();

        if ($teams->isEmpty()) {
            return redirect()->route('team-manager.dashboard')
                ->with('error', 'You are not assigned to any team.');
        }

        $tournaments = $teams->flatMap(fn($t) => $t->effectiveTournaments)->unique('id')->values();

        $breadcrumbs = ['title' => __('Register as Player')];

        return view('backend.pages.team-manager.register-as-player', compact('user', 'tournaments', 'breadcrumbs'));
    }

    /**
     * Assign captain role to a team member.
     * Both Owner and Captain can perform this action.
     * Owner keeps their 'Owner' role; old captain (if different) becomes 'Player'.
     * If Captain transfers: old captain becomes 'Player', new captain gets 'captain' role.
     */
    public function assignCaptain(Request $request)
    {
        $user = Auth::user();
        $team = $this->selectedTeam($user);

        if (!$team) {
            return redirect()->route('team-manager.dashboard')
                ->with('error', 'You are not assigned to any team.');
        }

        // Verify current user is owner, manager, or captain
        $currentUserPivot = $team->users()->where('user_id', $user->id)->first();
        $currentRole = $currentUserPivot?->pivot->role;
        $isOwner = $currentRole === 'Owner';
        $isManager = $currentRole === 'Manager';
        $isCaptain = $currentRole && strtolower($currentRole) === 'captain';

        if (!$isOwner && !$isManager && !$isCaptain) {
            return redirect()->route('team-manager.dashboard')
                ->with('error', 'Only the owner, manager, or captain can assign captaincy.');
        }

        $validated = $request->validate([
            'new_captain_user_id' => 'required|integer',
        ]);

        // Verify the new captain is a member of this team
        $newCaptain = $team->users()->where('user_id', $validated['new_captain_user_id'])->first();
        if (!$newCaptain) {
            return redirect()->route('team-manager.dashboard')
                ->with('error', 'Selected user is not a member of this team.');
        }

        // Ensure new captain has Team Manager role for dashboard access
        $newCaptainUser = User::find($validated['new_captain_user_id']);
        if ($newCaptainUser && !$newCaptainUser->hasRole('Team Manager')) {
            $newCaptainUser->assignRole('Team Manager');
        }

        // Demote current captain (if exists and is a different person from owner and new captain)
        $currentCaptainPivot = $team->users()->wherePivot('role', 'captain')->first();
        if ($currentCaptainPivot && $currentCaptainPivot->id !== $validated['new_captain_user_id']) {
            $team->users()->updateExistingPivot($currentCaptainPivot->id, ['role' => 'Player']);
        }

        // If current user is captain (not owner/manager) transferring, demote self
        if ($isCaptain && !$isOwner && !$isManager && $user->id !== $validated['new_captain_user_id']) {
            $team->users()->updateExistingPivot($user->id, ['role' => 'Player']);
        }

        // Assign new captain
        $team->users()->updateExistingPivot($validated['new_captain_user_id'], ['role' => 'captain']);

        // In-app notifications for captain assignment
        $dashboardUrl = route('team-manager.dashboard');

        // Notify the new captain
        $newCaptainUser = User::find($validated['new_captain_user_id']);
        if ($newCaptainUser && $newCaptainUser->id !== $user->id) {
            $newCaptainUser->notify(new GeneralNotification(
                "You have been assigned as Captain of {$team->name}",
                $dashboardUrl,
                'captain'
            ));
        }

        // Notify Owner and Manager (if different from current user and new captain)
        $teamMembers = $team->users()->get();
        foreach ($teamMembers as $member) {
            $role = $member->pivot->role;
            if (in_array($role, ['Owner', 'Manager']) && $member->id !== $user->id && $member->id !== $validated['new_captain_user_id']) {
                $member->notify(new GeneralNotification(
                    "{$newCaptain->name} has been assigned as Captain of {$team->name}",
                    $dashboardUrl,
                    'captain'
                ));
            }
        }

        return redirect()->route('team-manager.dashboard')
            ->with('success', 'Captain has been assigned to ' . $newCaptain->name . '.');
    }

    /**
     * All approved players in the tournament (player pool)
     */
    public function players(Request $request)
    {
        $user = Auth::user();
        $team = $this->selectedTeam($user);

        if (!$team) {
            return redirect()->route('team-manager.dashboard')
                ->with('error', 'You are not assigned to any team.');
        }

        $tournamentId = $team->tournament_id;

        // Get all players with approved registration for this tournament
        // Use withoutOrganizationScope() so players with NULL org_id are included
        // Exclude all retained players (they're already locked to their teams)
        $query = Player::withoutOrganizationScope()->whereHas('registrations', function ($q) use ($tournamentId) {
                $q->where('tournament_id', $tournamentId)
                  ->where('status', 'approved');
            })
            ->where(function ($q) {
                $q->where('player_mode', '!=', 'retained')
                  ->orWhereNull('player_mode');
            })
            ->with(['playerType', 'battingProfile', 'bowlingProfile', 'actualTeam', 'location', 'kitSize']);

        // Search by name or jersey name
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('jersey_name', 'like', "%{$search}%");
            });
        }

        // Filter by player type (role)
        if ($playerTypeId = $request->get('player_type')) {
            $query->where('player_type_id', $playerTypeId);
        }

        // Filter by batting profile
        if ($battingId = $request->get('batting')) {
            $query->where('batting_profile_id', $battingId);
        }

        // Filter by bowling profile
        if ($bowlingId = $request->get('bowling')) {
            $query->where('bowling_profile_id', $bowlingId);
        }

        // Filter by team
        if ($teamFilter = $request->get('team')) {
            $query->where('actual_team_id', $teamFilter);
        }

        $players = $query->orderBy('name')->paginate(20)->appends($request->query());

        // Get wishlisted player IDs for current user + tournament
        $wishlistedIds = Wishlist::where('user_id', $user->id)
            ->where('tournament_id', $tournamentId)
            ->pluck('player_id')
            ->toArray();

        // Filter options — only show values present among approved players
        $baseIds = Player::whereHas('registrations', fn($q) => $q->where('tournament_id', $tournamentId)->where('status', 'approved'));
        $playerTypes = PlayerType::whereIn('id', (clone $baseIds)->whereNotNull('player_type_id')->pluck('player_type_id')->unique())->orderBy('type')->get();
        $battingProfiles = BattingProfile::whereIn('id', (clone $baseIds)->whereNotNull('batting_profile_id')->pluck('batting_profile_id')->unique())->orderBy('style')->get();
        $bowlingProfiles = BowlingProfile::whereIn('id', (clone $baseIds)->whereNotNull('bowling_profile_id')->pluck('bowling_profile_id')->unique())->orderBy('style')->get();
        $teams = ActualTeam::where('tournament_id', $tournamentId)->orderBy('name')->get();

        $breadcrumbs = ['title' => __('Players')];

        return view('backend.pages.team-manager.players', compact(
            'team', 'players', 'wishlistedIds', 'breadcrumbs',
            'playerTypes', 'battingProfiles', 'bowlingProfiles', 'teams'
        ));
    }

    /**
     * Show full player profile (read-only, TM view)
     */
    public function showPlayer(Player $player)
    {
        $user = Auth::user();
        $team = $this->selectedTeam($user);

        if (!$team) {
            return redirect()->route('team-manager.dashboard')
                ->with('error', 'You are not assigned to any team.');
        }

        $tournamentId = $team->tournament_id;

        // Verify player belongs to this tournament (has approved registration)
        $belongsToTournament = $player->registrations()
            ->where('tournament_id', $tournamentId)
            ->where('status', 'approved')
            ->exists();

        if (!$belongsToTournament) {
            return redirect()->route('team-manager.players')
                ->with('error', 'Player not found in your tournament.');
        }

        // Load registrations with tournament for the context selector
        $registrations = $player->registrations()->with('tournament')->latest()->get();
        $selectedRegistration = $registrations->firstWhere('tournament_id', $tournamentId) ?? $registrations->first();

        // Tournament-context-aware form layout
        $settings = $selectedRegistration?->tournament?->settings;
        $layout = PlayerFormConfig::getFormLayout($settings, false);
        $fieldConfig = PlayerFormConfig::getFieldConfig($settings);

        // Verified fields from selected registration
        $verifiedFields = (array) ($selectedRegistration?->verified_fields ?? []);

        // Custom fields for selected tournament
        $customFields = $selectedRegistration?->tournament?->customFields?->where('form', 'player')->where('visible', true) ?? collect();
        $customValues = (array) ($selectedRegistration?->custom_field_values ?? []);

        $tournamentAssignments = DB::table('player_actual_team_tournament')
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

        $tournamentStats = \App\Models\PlayerStatistic::where('player_id', $player->id)
            ->with('tournament')
            ->get()
            ->keyBy('tournament_id');

        return view('backend.pages.players.show', [
            'player' => $player,
            'teams' => collect(),
            'locations' => PlayerLocation::all(),
            'kitSizes' => KitSize::all(),
            'battingProfiles' => BattingProfile::all(),
            'bowlingProfiles' => BowlingProfile::all(),
            'playerTypes' => PlayerType::all(),
            'templates' => collect(),
            'breadcrumbs' => [
                'title' => __('View Player'),
                'items' => [
                    ['label' => __('Players'), 'url' => route('team-manager.players')],
                ],
            ],
            'verifiedFields' => $verifiedFields,
            'verifiedProfile' => $player->allFieldsVerified(),
            'tournamentAssignments' => $tournamentAssignments,
            'tournamentStats' => $tournamentStats,
            'actualTeams' => collect(),
            'registrations' => $registrations,
            'selectedRegistration' => $selectedRegistration,
            'layout' => $layout,
            'fieldConfig' => $fieldConfig,
            'customFields' => $customFields,
            'customValues' => $customValues,
        ]);
    }

    /**
     * Team's own players (My Squad)
     */
    public function squad()
    {
        $user = Auth::user();
        $team = $this->selectedTeam($user);

        if (!$team) {
            return redirect()->route('team-manager.dashboard')
                ->with('error', 'You are not assigned to any team.');
        }

        // Only show players who have been added to the Player Roster
        // (i.e. they exist in the player_actual_team_tournament pivot table).
        // Use withoutOrganizationScope() so retained players with NULL org_id are included
        $query = Player::withoutOrganizationScope()->where('actual_team_id', $team->id)
            ->whereExists(function ($q) use ($team) {
                $q->select(\DB::raw(1))
                  ->from('player_actual_team_tournament')
                  ->whereColumn('player_actual_team_tournament.player_id', 'players.id')
                  ->where('player_actual_team_tournament.actual_team_id', $team->id);
            })
            ->with(['playerType', 'battingProfile', 'bowlingProfile', 'kitSize', 'location', 'user.roles']);

        // For auction tournaments, only show retained and sold (auctioned) players
        if ($team->tournament && $team->tournament->isAuction()) {
            $query->whereIn('player_mode', ['retained', 'sold']);
        }

        $teamPlayers = $query->orderBy('name')->get();

        $breadcrumbs = ['title' => __('My Squad')];

        return view('backend.pages.team-manager.squad', compact('team', 'teamPlayers', 'breadcrumbs'));
    }

    /**
     * List other teams in the same tournament
     */
    public function otherTeams()
    {
        $user = Auth::user();
        $team = $this->selectedTeam($user);

        if (!$team) {
            return redirect()->route('team-manager.dashboard')
                ->with('error', 'You are not assigned to any team.');
        }

        $tournamentId = $team->tournament_id;

        $otherTeams = ActualTeam::forTournament($tournamentId)
            ->where('id', '!=', $team->id)
            ->withCount(['playersPerTournament as approved_players_count' => function ($q) use ($tournamentId) {
                $q->where('player_actual_team_tournament.tournament_id', $tournamentId);
            }])
            ->orderBy('name')
            ->get();

        $breadcrumbs = ['title' => __('Other Teams')];

        return view('backend.pages.team-manager.other-teams', compact('team', 'otherTeams', 'breadcrumbs'));
    }

    /**
     * View a specific other team's players (read-only)
     */
    public function otherTeamPlayers(ActualTeam $otherTeam)
    {
        $user = Auth::user();
        $team = $this->selectedTeam($user);

        if (!$team) {
            return redirect()->route('team-manager.dashboard')
                ->with('error', 'You are not assigned to any team.');
        }

        // Verify same tournament
        if ($otherTeam->tournament_id !== $team->tournament_id) {
            return redirect()->route('team-manager.other-teams')
                ->with('error', 'That team is not in your tournament.');
        }

        $players = $otherTeam->playersPerTournament()
            ->wherePivot('tournament_id', $team->tournament_id)
            ->whereIn('player_mode', ['retained', 'sold'])
            ->with(['playerType', 'battingProfile', 'bowlingProfile'])
            ->orderBy('name')
            ->get();

        $tournamentId = $team->tournament_id;
        $wishlistedIds = Wishlist::where('user_id', $user->id)
            ->where('tournament_id', $tournamentId)
            ->pluck('player_id')
            ->toArray();

        $breadcrumbs = ['title' => $otherTeam->name . ' - Players'];

        return view('backend.pages.team-manager.other-team-players', compact('team', 'otherTeam', 'players', 'wishlistedIds', 'breadcrumbs'));
    }

    /**
     * Toggle wishlist (AJAX)
     */
    public function toggleWishlist(Request $request)
    {
        $user = Auth::user();
        $team = $this->selectedTeam($user);

        if (!$team) {
            return response()->json(['error' => 'No team assigned'], 403);
        }

        $request->validate(['player_id' => 'required|exists:players,id']);

        $tournamentId = $team->tournament_id;
        $playerId = $request->input('player_id');

        $existing = Wishlist::where('user_id', $user->id)
            ->where('player_id', $playerId)
            ->where('tournament_id', $tournamentId)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['wishlisted' => false]);
        }

        Wishlist::create([
            'user_id' => $user->id,
            'player_id' => $playerId,
            'tournament_id' => $tournamentId,
        ]);

        return response()->json(['wishlisted' => true]);
    }

    /**
     * Wishlisted players page
     */
    public function wishlist()
    {
        $user = Auth::user();
        $team = $this->selectedTeam($user);

        if (!$team) {
            return redirect()->route('team-manager.dashboard')
                ->with('error', 'You are not assigned to any team.');
        }

        $tournamentId = $team->tournament_id;

        $wishlistedPlayerIds = Wishlist::where('user_id', $user->id)
            ->where('tournament_id', $tournamentId)
            ->pluck('player_id');

        $players = Player::whereIn('id', $wishlistedPlayerIds)
            ->with(['playerType', 'battingProfile', 'bowlingProfile', 'actualTeam'])
            ->orderBy('name')
            ->get();

        $breadcrumbs = ['title' => __('Wishlist')];

        return view('backend.pages.team-manager.wishlist', compact('team', 'players', 'breadcrumbs'));
    }
}

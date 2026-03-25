<?php

namespace App\Http\Controllers\Backend;

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
use App\Models\TournamentTemplate;
use App\Services\Poster\TemplateRenderService;
use Illuminate\Support\Facades\Storage;
use App\Notifications\GeneralNotification;

class TeamManagerController extends Controller
{
    /**
     * Team Manager Dashboard - Shows their team, players, and upcoming auctions
     */
    public function dashboard()
    {
        $user = Auth::user();

        // Get the team(s) this manager is assigned to
        $teams = $user->actualTeams()
            ->with(['tournament.organization', 'players', 'auctionPlayers'])
            ->get();

        if ($teams->isEmpty()) {
            return view('backend.pages.team-manager.no-team');
        }

        // Get the primary team (first one, or could be selected)
        $team = $teams->first();

        // Get players on this team
        $teamPlayers = Player::where('actual_team_id', $team->id)
            ->orderBy('name')
            ->get();

        // Get upcoming auctions for the tournament
        $upcomingAuctions = collect();
        if ($team->tournament_id) {
            $upcomingAuctions = Auction::where('tournament_id', $team->tournament_id)
                ->whereIn('status', ['scheduled', 'active', 'paused'])
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
            $spent = $team->auctionPlayers()
                ->where('auction_id', $auction->id)
                ->where('status', 'sold')
                ->sum('final_price');

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
            'isManager'
        ));
    }

    /**
     * Show form to create a new player for the team
     */
    public function createPlayer()
    {
        $user = Auth::user();
        $team = $user->actualTeams()->with('tournament.organization')->first();

        if (!$team) {
            return redirect()->route('team-manager.dashboard')
                ->with('error', 'You are not assigned to any team.');
        }

        $locations = PlayerLocation::orderBy('name')->get();
        $kitSizes = KitSize::all();
        $battingProfiles = BattingProfile::all();
        $bowlingProfiles = BowlingProfile::all();
        $playerTypes = PlayerType::all();

        return view('backend.pages.team-manager.create-player', compact(
            'team', 'locations', 'kitSizes', 'battingProfiles', 'bowlingProfiles', 'playerTypes'
        ));
    }

    /**
     * Store a new player created by team manager
     */
    public function storePlayer(Request $request)
    {
        $user = Auth::user();
        $team = $user->actualTeams()->with('tournament.organization')->first();

        if (!$team) {
            return redirect()->route('team-manager.dashboard')
                ->with('error', 'You are not assigned to any team.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:players,email',
            'mobile_number_full' => 'nullable|string|max:20',
            'cricheroes_number_full' => 'nullable|string|max:20',
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
            'image_path' => 'nullable|image|mimes:jpeg,png,jpg|max:6144',
        ]);

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'mobile_number_full' => $validated['mobile_number_full'] ?? null,
            'cricheroes_number_full' => $validated['cricheroes_number_full'] ?? null,
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

        // Handle image upload
        if ($request->hasFile('image_path')) {
            $data['image_path'] = $request->file('image_path')->store('player_images', 'public');
        }

        $player = Player::create($data);

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
        $team = $user->actualTeams()->first();

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
        $team = $user->actualTeams()->first();

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

        $auctions = Auction::where('tournament_id', $team->tournament_id)
            ->with(['tournament', 'auctionPlayers' => function ($query) use ($team) {
                $query->where('team_id', $team->id)->where('status', 'sold');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        // Add budget info to each auction
        foreach ($auctions as $auction) {
            $spent = $team->auctionPlayers()
                ->where('auction_id', $auction->id)
                ->where('status', 'sold')
                ->sum('final_price');

            $auction->budget_info = [
                'max' => $auction->max_budget_per_team ?? 0,
                'spent' => $spent,
                'remaining' => ($auction->max_budget_per_team ?? 0) - $spent,
            ];
        }

        return view('backend.pages.team-manager.auctions', compact('team', 'auctions'));
    }

    /**
     * Verify a player with password confirmation
     */
    public function verifyPlayer(Request $request, Player $player)
    {
        $user = Auth::user();
        $team = $user->actualTeams()->first();

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
                        'welcome-card-' . $player->id . '-' . time() . '.png'
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
        $team = $user->actualTeams()->first();

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
     * Assign captain role to a team member.
     * Both Owner and Captain can perform this action.
     * Owner keeps their 'Owner' role; old captain (if different) becomes 'Player'.
     * If Captain transfers: old captain becomes 'Player', new captain gets 'captain' role.
     */
    public function assignCaptain(Request $request)
    {
        $user = Auth::user();
        $team = $user->actualTeams()->first();

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
}

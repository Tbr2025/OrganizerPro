<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ActualTeam;
use App\Models\Auction;
use App\Models\Player;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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

        return view('backend.pages.team-manager.dashboard', compact(
            'teams',
            'team',
            'teamPlayers',
            'upcomingAuctions',
            'availablePlayers',
            'auctionBudgets'
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

        return view('backend.pages.team-manager.create-player', compact('team'));
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
            'phone' => 'nullable|string|max:20',
            'dob' => 'nullable|date',
            'batting_style' => 'nullable|string|max:50',
            'bowling_style' => 'nullable|string|max:50',
            'playing_role' => 'nullable|string|max:50',
            'jersey_number' => 'nullable|integer|min:0|max:99',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'mobile_number_full' => $validated['phone'] ?? null,
            'jersey_number' => $validated['jersey_number'] ?? null,
            'actual_team_id' => $team->id,
            'player_mode' => 'normal',
            'status' => 'pending',
            'created_by' => $user->id,
        ];

        // Handle photo upload
        if ($request->hasFile('photo')) {
            $data['image_path'] = $request->file('photo')->store('player_images', 'public');
        }

        Player::create($data);

        return redirect()->route('team-manager.dashboard')
            ->with('success', 'Player created and added to your team successfully.');
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

        return redirect()->route('team-manager.dashboard')
            ->with('success', 'Player "' . $player->name . '" has been verified successfully.');
    }
}

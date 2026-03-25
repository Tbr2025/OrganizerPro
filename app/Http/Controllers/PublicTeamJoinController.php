<?php

namespace App\Http\Controllers;

use App\Models\ActualTeam;
use App\Models\BattingProfile;
use App\Models\BowlingProfile;
use App\Models\KitSize;
use App\Models\Player;
use App\Models\PlayerLocation;
use App\Models\PlayerType;
use App\Notifications\GeneralNotification;
use Illuminate\Http\Request;

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
            $data['image_path'] = $request->file('image')->store('player_images', 'public');
        }

        $player = Player::create($data);

        // Notify team Owner/Manager
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

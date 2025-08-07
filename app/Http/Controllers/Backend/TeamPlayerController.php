<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeamPlayerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Team $team)
    {
        // Step 1: Validate the request
        $validated = $request->validate([
            'player_id' => 'required|exists:players,id',
            'tournament_id' => 'nullable|exists:tournaments,id',
            'role' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
        ]);

        $playerId = $validated['player_id'];

        // Step 2: Prevent duplicate player-team assignment
        if ($team->players()->where('player_id', $playerId)->exists()) {
            return back()->withErrors(['player_id' => 'This player is already in the team.']);
        }

        // Step 3: Handle image upload
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('player_images', 'public');
        }

        // Step 4: Prepare pivot data
        $pivotData = [
            'tournament_id' => $request->input('tournament_id'),
            'role' => $validated['role'],
            'image_path' => $imagePath,
            'team_id' => $team->id, // optional if your pivot includes this
        ];

        // Step 5: Attach player to team via pivot table
        $team->players()->attach($playerId, $pivotData);

        // Step 6: Update team_id in players table (if you use one-to-many reference too)
        Player::where('id', $playerId)->update(['team_id' => $team->id]);

        // Step 7: Redirect back with success
        return redirect()->back()->with('success', 'Player added to team successfully.');
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Team $team, Player $player)
    {
        // Find pivot row in `player_team_tournament`
        $pivot = DB::table('player_team_tournament')
            ->where('team_id', $team->id)
            ->where('player_id', $player->id)
            ->first();

        if ($pivot) {
            // Delete the pivot row
            DB::table('player_team_tournament')->where('id', $pivot->id)->delete();

            // Set team_id to null in players table (if you’re using team_id directly)
            $player->team_id = null;
            $player->save();

            return back()->with('success', 'Player removed from team and updated successfully.');
        }

        return back()->withErrors('Player not found in this team.');
    }
}

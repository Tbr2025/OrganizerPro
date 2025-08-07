<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Ball;
use App\Models\Matches;
use App\Models\Player;
use Illuminate\Http\Request;

class BallController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    public function create(Matches $match)
    {
        $players = Player::whereIn('team_id', [$match->team_a_id, $match->team_b_id])->get();

        $overs = Ball::where('match_id', $match->id)
            ->orderBy('over')
            ->orderBy('ball_in_over')
            ->get()
            ->groupBy('over');

        return view('backend.pages.balls.create', compact('match', 'players', 'overs'));
    }

    public function store(Request $request, Matches $match)
    {
        $data = $request->validate([
            'batsman_id' => 'required|exists:players,id',
            'bowler_id' => 'required|exists:players,id',
            'over' => 'required|integer|min:0',
            'ball_in_over' => 'required|integer|min:1|max:6',
            'runs' => 'required|integer|min:0|max:6',
            'extra_type' => 'nullable|string|in:wide,no_ball,bye,leg_bye',
            'extra_runs' => 'nullable|integer|min:0|max:6',
            'is_wicket' => 'nullable|boolean'
        ]);

        $data['match_id'] = $match->id;
        $data['is_wicket'] = $request->has('is_wicket');

        Ball::create($data);

        return redirect()->back()->with('success', 'Ball recorded successfully.');
    }



    public function ajaxStore(Request $request)
    {
        $validated = $request->validate([
            'match_id' => 'required|exists:matches,id',
            'batsman_id' => 'required|exists:players,id',
            'bowler_id' => 'required|exists:players,id|different:batsman_id', // Ensure not same
            'over' => 'required|integer|min:0',
            'ball_in_over' => 'required|integer|min:0',
            'runs' => 'required|integer|min:0',
            'extra_type' => 'nullable|string',
            'extra_runs' => 'nullable|integer',
            'is_wicket' => 'nullable|boolean',
        ]);

        $match = Matches::findOrFail($validated['match_id']);

        // Make sure players are from correct teams
        $batsman = Player::findOrFail($validated['batsman_id']);
        $bowler = Player::findOrFail($validated['bowler_id']);

        // Prevent same player for both
        if ($batsman->id === $bowler->id) {
            return response()->json(['error' => 'Batsman and Bowler cannot be the same player.'], 422);
        }

        // Optional: determine innings and enforce team roles
        if (!in_array($batsman->team_id, [$match->team_a_id, $match->team_b_id])) {
            return response()->json(['error' => 'Batsman is not part of this match.'], 422);
        }

        if (!in_array($bowler->team_id, [$match->team_a_id, $match->team_b_id])) {
            return response()->json(['error' => 'Bowler is not part of this match.'], 422);
        }

        if ($batsman->team_id === $bowler->team_id) {
            return response()->json(['error' => 'Batsman and Bowler must be from different teams.'], 422);
        }

        // Set default values
        $validated['extra_runs'] = $validated['extra_runs'] ?? 0;
        $validated['is_wicket'] = $validated['is_wicket'] ?? false;

        Ball::create($validated);

        return response()->json(['success' => true]);
    }


    public function summary(Matches $match)
    {
        $overs = Ball::where('match_id', $match->id)
            ->orderBy('over')
            ->orderBy('ball_in_over')
            ->get()
            ->groupBy('over');

        return view('backend.pages.matches.partials.over-summary', compact('overs'))->render();
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
    public function destroy(string $id)
    {
        //
    }
}

<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ActualTeamUser;
use App\Models\Ball;
use App\Models\Matches;
use App\Models\Player;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BallController extends Controller
{
    public function index()
    {
        //
    }
    public function create(Matches $match)
    {
        // Fetch batting & bowling actual teams
        $battingTeam = $match->actualTeamA;
        $bowlingTeam = $match->actualTeamB;

        // Safely get players or empty collection
        $battingPlayers = $battingTeam ? $battingTeam->users : collect();
        $bowlingPlayers = $bowlingTeam ? $bowlingTeam->users : collect();

        // Get IDs of batsmen who are already out
        $outBatsmanIds = Ball::where('match_id', $match->id)
            ->where('is_wicket', 1)
            ->pluck('batsman_id')
            ->toArray();

        // Exclude out batsmen from batting players
        $battingPlayers = $battingPlayers->whereNotIn('id', $outBatsmanIds);

        // Get overs data
        $overs = Ball::where('match_id', $match->id)
            ->orderBy('over')
            ->orderBy('ball_in_over')
            ->get()
            ->groupBy('over');

        return view('backend.pages.balls.create', compact('match', 'battingPlayers', 'bowlingPlayers', 'overs'));
    }




    public function store(Request $request, Matches $match)
    {
        $data = $request->validate([
            'batsman_id' => 'required|exists:players,id',
            'bowler_id' => 'required|exists:players,id|different:batsman_id',
            'runs' => 'required|integer|min:0|max:6',
            'extra_type' => 'nullable|string|in:wide,no_ball,bye,leg_bye',
            'extra_runs' => 'nullable|integer|min:0|max:6',
            'is_wicket' => 'nullable|boolean'
        ]);

        $data['match_id'] = $match->id;
        $data['extra_runs'] = $data['extra_runs'] ?? 0;
        $data['is_wicket'] = $request->has('is_wicket');

        // find next over + ball
        [$over, $ballInOver] = $this->getNextBall($match->id, $data['extra_type']);
        $data['over'] = $over;
        $data['ball_in_over'] = $ballInOver;

        Ball::create($data);

        return redirect()->back()->with('success', 'Ball recorded successfully.');
    }

    public function ajaxStore(Request $request)
    {
        // --- ADJUSTMENT 1: Validation ---
        // We need to validate that the batsman_id and bowler_id being sent
        // are valid user_ids that exist in the actual_team_users table.
        $validator = Validator::make($request->all(), [
            'match_id' => 'required|exists:matches,id',
            // Validate that the provided ID (which we expect to be a user_id) exists in the user_id column of actual_team_users
            'batsman_id' => 'required|exists:actual_team_users,user_id|numeric',
            'bowler_id'  => 'required|exists:actual_team_users,user_id|numeric|different:batsman_id',
            'runs'       => 'required|integer|min:0|max:6',
            'extra_type' => 'nullable|string|in:wide,no_ball,bye,leg_bye',
            'extra_runs' => 'nullable|integer|min:0|max:6',
            'is_wicket'  => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        try {
            $match = Matches::findOrFail($validated['match_id']);

            // --- ADJUSTMENT 2: Retrieval ---
            // Find the ActualTeamUser records using the validated user_ids.
            // This part is correct from the previous step.
            $batsman = ActualTeamUser::where('user_id', $validated['batsman_id'])->firstOrFail();
            $bowler  = ActualTeamUser::where('user_id', $validated['bowler_id'])->firstOrFail();

            // Ensure batsman and bowler are from different actual teams
            if ($batsman->actual_team_id === $bowler->actual_team_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => ['team_conflict' => 'Batsman and Bowler must be from different teams.'],
                ], 422);
            }

            // Prepare the data array for Ball creation
            $ballData = [
                'match_id' => $match->id,
                // --- ADJUSTMENT 3: Insert the USER_ID, not the ActualTeamUser's primary key ID ---
                // We are inserting the user_id because the foreign key constraint (if it were correct)
                // would be on users(id), and that's what you're validating against and sending from the view.
                'batsman_id' => $validated['batsman_id'], // Use the user_id sent from the form
                'bowler_id' => $validated['bowler_id'],   // Use the user_id sent from the form
                'runs' => $validated['runs'],
                'extra_type' => $validated['extra_type'],
                'extra_runs' => $validated['extra_runs'] ?? 0,
                'is_wicket' => $request->has('is_wicket') ? 1 : 0,
            ];

            // If it's a wicket, zero out runs
            if ($ballData['is_wicket']) {
                $ballData['runs'] = 0;
                $ballData['extra_runs'] = 0;
            }

            // Determine next ball automatically
            [$over, $ballInOver] = $this->getNextBall($match->id, $ballData['extra_type']);
            $ballData['over'] = $over;
            $ballData['ball_in_over'] = $ballInOver;

            // Create the ball
            Ball::create($ballData);

            return response()->json(['success' => true, 'message' => 'Ball saved successfully.']);
        } catch (ModelNotFoundException $e) {
            Log::error("Model not found: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Resource not found.',
                'errors' => ['general' => 'Selected player or match not found.'],
            ], 404);
        } catch (\Exception $e) {
            // This will catch the foreign key violation if the schema is not fixed.
            Log::error("Ball creation error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred.',
                'errors' => ['general' => $e->getMessage()], // Return the actual SQL error
            ], 500);
        }
    }

    // Assuming getNextBall method exists and is functional
    private function getNextBall(int $matchId, ?string $extraType): array
    {
        // Get the last legal ball in this match
        $lastBall = Ball::where('match_id', $matchId)
            ->orderByDesc('over')
            ->orderByDesc('ball_in_over')
            ->first();

        if (!$lastBall) {
            // First ball of the match → start with Over 1, Ball 1
            return [1, 1];
        }

        $over       = $lastBall->over;
        $ballInOver = $lastBall->ball_in_over;

        // For wides & no-balls → do NOT increase ball count
        if (in_array($extraType, ['wide', 'no_ball'])) {
            return [$over, $ballInOver];
        }

        // Increase ball in over
        $ballInOver++;

        // If 6 legal balls completed, move to next over
        if ($ballInOver > 6) {
            $over++;
            $ballInOver = 1;
        }

        return [$over, $ballInOver];
    }

    public function summary(Matches $match)
    {
        $overs = Ball::where('match_id', $match->id)
            ->orderBy('over')
            ->orderBy('ball_in_over')
            ->get()
            ->groupBy('over');

        return view('backend.pages.matches.partials.over-summary', [
            'overs' => $overs,
            'match' => $match, // ✅ now available in Blade
        ])->render();
    }


    public function destroy(Matches $match, Ball $ball)
    {
        // safety: make sure ball belongs to match
        if ($ball->match_id !== $match->id) {
            return response()->json(['error' => 'Ball does not belong to this match.'], 422);
        }

        $ball->delete();

        return response()->json(['success' => true]);
    }
}

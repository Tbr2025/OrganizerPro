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
            'dismissal_type' => 'nullable',
            'fielder_id' => 'nullable|exists:actual_team_users,user_id|numeric',
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

            // --- ADJUSTMENT 2: Ensure players exist in the match context ---
            // It's better to check if the batsman and bowler are part of the teams in the match.
            // You might need to adjust this logic based on your schema for team membership.
            // For example, assuming a `match_teams` or similar pivot table, or by checking `teamA` and `teamB` relations.

            // Find the ActualTeamUser records using the validated user_ids.
            // This is crucial for team checks.
            $batsmanTeamUser = ActualTeamUser::where('user_id', $validated['batsman_id'])->first();
            $bowlerTeamUser  = ActualTeamUser::where('user_id', $validated['bowler_id'])->first();
            $currentStrikerUserId = $request->input('current_striker_user_id');
            $currentNonStrikerUserId = $request->input('current_non_striker_user_id');
            if (!$batsmanTeamUser || !$bowlerTeamUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => ['player_team' => 'Batsman or Bowler not found in team data.'],
                ], 422);
            }

            // Ensure batsman and bowler are from different actual teams for this match context.
            // You might need to check against the correct teams for the current innings.
            if ($batsmanTeamUser->actual_team_id === $bowlerTeamUser->actual_team_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => ['team_conflict' => 'Batsman and Bowler must be from different teams.'],
                ], 422);
            }

            // Prepare the data array for Ball creation
            $ballData = [
                'match_id' => $match->id,
                'batsman_id' => $validated['batsman_id'],
                'bowler_id' => $validated['bowler_id'],
                'runs' => $validated['runs'],
                'extra_type' => $validated['extra_type'],
                'extra_runs' => $validated['extra_runs'] ?? 0,
                'is_wicket' => $request->has('is_wicket') ? 1 : 0,
                'dismissal_type' => $validated['dismissal_type'] ?? null,
                'fielder_id' => $request->input('fielder_id') ? $request->input('fielder_id') : null,
                'over' => $validated['over'] ?? 1, // Default over to 1 if not provided
                'ball_in_over' => $validated['ball_in_over'] ?? 1, // Default ball to 1 if not provided
            ];

            // --- ADJUSTMENT FOR WICKET: Nullify extras ---
            if ($ballData['is_wicket']) {
                session()->forget('current_striker_id_' . $match->id);
                session()->forget('current_non_striker_id_' . $match->id);
                $ballData['runs'] = 0; // Runs are not added on a wicket ball (unless it's a run out on a wicket)
                $ballData['extra_type'] = null; // Extra type should be null if it's a wicket
                $ballData['extra_runs'] = 0;   // Extra runs should be zero

            } else {
                // --- NON-WICKET HANDLING: Update session for next striker ---
                $totalRunsOnBall = $ballData['runs'] + $ballData['extra_runs'];

                if ($totalRunsOnBall > 0 && $totalRunsOnBall % 2 !== 0) {
                    // Odd runs scored, swap striker and non-striker
                    $nextStrikerUserId = $currentNonStrikerUserId;
                    $nextNonStrikerUserId = $currentStrikerUserId;
                } else {
                    // Even runs or no runs, striker stays the same (unless they got out, which is handled by the wicket logic)
                    $nextStrikerUserId = $currentStrikerUserId;
                    $nextNonStrikerUserId = $currentNonStrikerUserId;
                }

                // Update session
                session(['current_striker_id_' . $match->id => $nextStrikerUserId]);
                session(['current_non_striker_id_' . $match->id => $nextNonStrikerUserId]);
            }

            // Determine next ball automatically (assuming you have a getNextBall helper method)
            // Ensure this method correctly increments over and ball_in_over
            // and handles end of over logic.
            [$over, $ballInOver] = $this->getNextBall($match->id, $ballData['extra_type']);
            $ballData['over'] = $over;
            $ballData['ball_in_over'] = $ballInOver;

            // Create the ball
            $ball = Ball::create($ballData);
            Log::info('New striker ID set: ' . session('current_striker_id_' . $ball->match_id));

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
                'errors' => ['general' => $e->getMessage()], // Return the actual SQL error for debugging
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

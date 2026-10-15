<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Ball;
use App\Models\Matches;
use App\Services\Scoring\BallRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BallController extends Controller
{
    /**
     * Who may look at a match, and who may change what it says happened.
     *
     * This controller had no authorization of any kind, and `admin/matches*` is on
     * RedirectTeamManager's allowlist, so team managers could reach every method by URL.
     * Ball-by-ball scoring is the Scorer's job, so it needs `match.edit` — which Scorers hold and
     * team managers do not. Reads stay on `match.view` so a manager can still follow the match.
     */
    public function __construct(private readonly BallRecorder $recorder)
    {
        $this->middleware('permission:match.view')->only(['index', 'summary', 'lastBall']);
        $this->middleware('permission:match.edit')->only(['create', 'store', 'ajaxStore', 'destroy']);
        $this->middleware('credits.unlock:' . \App\Models\Credits\CreditFeature::SCORING)->only(['ajaxStore', 'destroy']);
    }

    public function index()
    {
        //
    }

    /**
     * The old standalone ball-entry page. It called relations that do not
     * exist ($match->actualTeamA) and never rendered; scoring happens on the
     * match page.
     */
    public function create(Matches $match): RedirectResponse
    {
        return redirect()->route('admin.matches.show', $match);
    }

    public function store(Matches $match): RedirectResponse
    {
        return redirect()->route('admin.matches.show', $match);
    }

    /**
     * Record one delivery from the scoring page. See BallRecorder for the rules.
     */
    public function ajaxStore(Request $request, Matches $match): JsonResponse
    {
        $this->authorizeMatch($match);

        $validator = Validator::make($request->all(), [
            'batsman_id' => 'required|integer',
            // The page has always sent the pair as current_*_user_id.
            'non_striker_id' => 'required_without:current_non_striker_user_id|nullable|integer',
            'current_non_striker_user_id' => 'nullable|integer',
            'bowler_id' => 'required|integer|different:batsman_id',
            'runs' => 'required|integer|min:0|max:7',
            'extra_type' => 'nullable|string|in:wide,no_ball,bye,leg_bye',
            'extra_runs' => 'nullable|integer|min:0|max:7',
            'is_wicket' => 'nullable|boolean',
            'dismissal_type' => 'nullable|required_if:is_wicket,1,true|in:bowled,caught,lbw,run_out,stumped,hit_wicket',
            'out_batsman' => 'nullable|in:striker,non_striker',
            'fielder_id' => 'nullable|integer',
            'event_uuid' => 'nullable|uuid',
            'expected_version' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $input = $validator->validated();
        $input['non_striker_id'] = (int) ($input['non_striker_id'] ?? $input['current_non_striker_user_id']);
        $input['is_wicket'] = $request->boolean('is_wicket');

        try {
            $result = $this->recorder->record($match, $input);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], $e->status);
        }

        return response()->json([
            'success' => true,
            'message' => $result['duplicate'] ? 'Ball already recorded.' : 'Ball saved successfully.',
            'ball_id' => $result['ball']->id,
            'score_version' => (int) $match->fresh()->score_version,
        ]);
    }

    public function summary(Matches $match)
    {
        $overs = Ball::where('match_id', $match->id)
            ->orderBy('over')
            ->orderBy('ball_in_over')
            ->orderBy('id')
            ->get()
            ->groupBy('over');

        return view('backend.pages.matches.partials.over-summary', [
            'overs' => $overs,
            'match' => $match, // ✅ now available in Blade
        ])->render();
    }

    /** Undo: only the most recent delivery of the match can be removed. */
    public function destroy(Request $request, Matches $match, Ball $ball): JsonResponse
    {
        $this->authorizeMatch($match);

        if ((int) $ball->match_id !== (int) $match->id) {
            return response()->json(['success' => false, 'error' => 'Ball does not belong to this match.'], 422);
        }

        try {
            $this->recorder->undo($match, $ball, $request->filled('expected_version') ? (int) $request->input('expected_version') : null);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'error' => collect($e->errors())->flatten()->first()], $e->status);
        }

        return response()->json(['success' => true, 'score_version' => (int) $match->fresh()->score_version]);
    }

    /**
     * The delivery an undo would remove: the most recent one of the match.
     */
    public function lastBall(Matches $match)
    {
        $lastBall = Ball::where('match_id', $match->id)->orderByDesc('id')->first();

        if (! $lastBall) {
            return response()->json([
                'success' => false,
                'message' => 'No balls recorded in this match yet.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'ball' => [
                'id' => $lastBall->id,
                'innings' => $lastBall->innings,
                'over' => $lastBall->over,
                'ball_in_over' => $lastBall->ball_in_over,
                'runs' => $lastBall->runs,
                'is_wicket' => $lastBall->is_wicket,
            ],
        ]);
    }

    /** organizer.access only narrows Organizers; everyone else is held to their organization here. */
    private function authorizeMatch(Matches $match): void
    {
        abort_unless(Matches::forUser(auth()->user())->whereKey($match->id)->exists(), 403);
    }
}

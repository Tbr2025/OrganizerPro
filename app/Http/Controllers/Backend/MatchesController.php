<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ActualTeam;
use App\Models\Ball;
use App\Models\MatchAppreciation;
use App\Models\Matches;
use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View; // ✅ Correct import


class MatchesController extends Controller
{
    public function index(): View
    {
        $matches = Matches::with(['tournament', 'teamA', 'teamB', 'winner'])->latest()->paginate(20);

        return view('backend.pages.matches.index', compact('matches'));
    }

    public function create(): View
    {
        $tournaments = Tournament::all();
        $teams = ActualTeam::all();

        return view('backend.pages.matches.create', compact('tournaments', 'teams'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'tournament_id' => 'required|exists:tournaments,id',
            'team_a_id' => 'required|different:team_b_id|exists:actual_teams,id',
            'team_b_id' => 'required|exists:actual_teams,id',
            'match_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'overs' => 'nullable|integer|min:1|max:50',
            'venue' => 'nullable|string|max:255',
        ]);

        Matches::create($request->only([
            'name',
            'tournament_id',
            'team_a_id',
            'team_b_id',
            'match_date',
            'start_time',
            'end_time',
            'overs',
            'venue'
        ]));
        return redirect()->route('admin.matches.index')->with('success', 'Match created successfully.');
    }

    public function show(Matches $match): View
    {
        $match->load([
            'tournament',
            'teamA.players.player',
            'teamB.players.player',
            'winner',
            'appreciations.player'
        ]);

        // Get current innings from session or default to 1
        $currentInnings = session('match_innings_' . $match->id, 1);

        // Get team player IDs
        $teamAPlayerIds = $match->teamA?->players?->pluck('id')->toArray() ?? [];
        $teamBPlayerIds = $match->teamB?->players?->pluck('id')->toArray() ?? [];

        // Get all balls
        $allBalls = Ball::where('match_id', $match->id)
            ->orderBy('over')
            ->orderBy('ball_in_over')
            ->get();

        // Separate balls by innings (based on batsman team)
        $innings1Balls = $allBalls->filter(fn($b) => in_array($b->batsman_id, $teamAPlayerIds));
        $innings2Balls = $allBalls->filter(fn($b) => in_array($b->batsman_id, $teamBPlayerIds));

        // Calculate innings stats
        $innings1Stats = $this->calculateInningsStats($innings1Balls);
        $innings2Stats = $this->calculateInningsStats($innings2Balls);

        // Determine if first innings is complete
        $matchOversLimit = $match->overs ?? 20;
        $firstInningsComplete = $innings1Stats['completedOvers'] >= $matchOversLimit ||
                                $innings1Stats['wickets'] >= 10 ||
                                ($innings1Balls->isNotEmpty() && $innings2Balls->isNotEmpty());

        // Auto-switch to 2nd innings if 1st is complete and session not set
        if ($firstInningsComplete && !session()->has('match_innings_' . $match->id)) {
            $currentInnings = 2;
            session(['match_innings_' . $match->id => 2]);
        }

        // Get current innings balls for display
        $balls = $currentInnings === 1 ? $innings1Balls : $innings2Balls;

        // Determine batting and bowling teams for current innings
        if ($currentInnings === 1) {
            $battingTeam = $match->teamA;
            $bowlingTeam = $match->teamB;
            $battingPlayers = $match->teamA->players ?? collect();
            $bowlingPlayers = $match->teamB->players ?? collect();
        } else {
            $battingTeam = $match->teamB;
            $bowlingTeam = $match->teamA;
            $battingPlayers = $match->teamB->players ?? collect();
            $bowlingPlayers = $match->teamA->players ?? collect();
        }

        // Group by over for breakdown
        $overs = $balls->groupBy('over');

        $summary = [];
        foreach ($overs as $overNum => $ballsInOver) {
            $overRuns = $ballsInOver->sum('runs') + $ballsInOver->sum('extra_runs');
            $wickets = $ballsInOver->where('is_wicket', 1)->count();

            $ballSummary = $ballsInOver->map(function ($ball) {
                if ($ball->is_wicket) return 'W';
                if ($ball->extra_type === 'wide') return ($ball->runs + $ball->extra_runs) . 'wd';
                if ($ball->extra_type === 'no_ball') return ($ball->runs + $ball->extra_runs) . 'nb';
                if ($ball->extra_type === 'bye') return ($ball->extra_runs) . 'b';
                if ($ball->extra_type === 'leg_bye') return ($ball->extra_runs) . 'lb';
                return (string) $ball->runs;
            })->values();

            $summary[] = [
                'over'    => $overNum,
                'balls'   => $ballSummary,
                'runs'    => $overRuns,
                'wickets' => $wickets,
            ];
        }

        // Current innings totals
        $totalRuns = $balls->sum('runs') + $balls->sum('extra_runs');
        $totalWickets = $balls->where('is_wicket', 1)->count();
        $totalOvers = $overs->count();

        // Get IDs of batsmen who are already out in current innings
        $outBatsmenIds = $balls->where('is_wicket', 1)->pluck('batsman_id')->toArray();

        // Auto-select current striker, non-striker, and bowler
        $currentStriker = null;
        $currentNonStriker = null;
        $currentBowler = null;
        $needsNewBatsman = false;

        $lastBall = $balls->last();
        if ($lastBall) {
            $lastBatsman = $lastBall->batsman_id;
            $lastBowler = $lastBall->bowler_id;

            $activeBatsmen = $balls->pluck('batsman_id')->unique()
                ->diff($outBatsmenIds)->values();

            $lastBallRuns = $lastBall->runs + ($lastBall->extra_runs ?? 0);
            $isEndOfOver = $lastBall->ball_in_over >= 6 && !in_array($lastBall->extra_type, ['wide', 'no_ball']);

            if ($lastBall->is_wicket) {
                $needsNewBatsman = true;
                $currentStriker = null;
                $currentNonStriker = $activeBatsmen->first();
            } else {
                $shouldSwap = ($lastBallRuns % 2 === 1) xor $isEndOfOver;
                $otherBatsman = $activeBatsmen->filter(fn($id) => $id !== $lastBatsman)->first();

                if ($shouldSwap) {
                    $currentNonStriker = $lastBatsman;
                    // Only set striker to other batsman if they exist and are different
                    $currentStriker = ($otherBatsman && $otherBatsman !== $lastBatsman) ? $otherBatsman : null;
                } else {
                    $currentStriker = $lastBatsman;
                    // Only set non-striker if they exist and are different
                    $currentNonStriker = ($otherBatsman && $otherBatsman !== $lastBatsman) ? $otherBatsman : null;
                }
            }

            if ($isEndOfOver) {
                $previousOver = $balls->where('over', $lastBall->over - 1)->first();
                $currentBowler = $previousOver ? $previousOver->bowler_id : null;
            } else {
                $currentBowler = $lastBowler;
            }
        }

        return view('backend.pages.matches.show', compact(
            'match',
            'summary',
            'battingPlayers',
            'bowlingPlayers',
            'battingTeam',
            'bowlingTeam',
            'totalRuns',
            'totalWickets',
            'totalOvers',
            'outBatsmenIds',
            'currentStriker',
            'currentNonStriker',
            'currentBowler',
            'needsNewBatsman',
            'currentInnings',
            'firstInningsComplete',
            'innings1Stats',
            'innings2Stats'
        ));
    }

    /**
     * Calculate innings statistics
     */
    private function calculateInningsStats($balls): array
    {
        if ($balls->isEmpty()) {
            return ['runs' => 0, 'wickets' => 0, 'overs' => '0.0', 'completedOvers' => 0];
        }

        $totalRuns = $balls->sum('runs') + $balls->sum('extra_runs');
        $totalWickets = $balls->where('is_wicket', 1)->count();

        $legalBalls = $balls->filter(fn($b) => !in_array($b->extra_type, ['wide', 'no_ball']))->count();
        $completedOvers = floor($legalBalls / 6);
        $ballsInOver = $legalBalls % 6;

        return [
            'runs' => $totalRuns,
            'wickets' => $totalWickets,
            'overs' => $completedOvers . '.' . $ballsInOver,
            'completedOvers' => $completedOvers,
        ];
    }

    /**
     * Switch innings
     */
    public function switchInnings(Matches $match)
    {
        $currentInnings = session('match_innings_' . $match->id, 1);
        $newInnings = $currentInnings === 1 ? 2 : 1;
        session(['match_innings_' . $match->id => $newInnings]);

        return redirect()->route('admin.matches.show', $match)
            ->with('success', 'Switched to ' . ($newInnings === 1 ? '1st' : '2nd') . ' innings.');
    }

    /**
     * Get match state as JSON for AJAX updates
     */
    public function getState(Matches $match)
    {
        $match->load(['teamA.players', 'teamB.players']);

        // Get current innings from session
        $currentInnings = session('match_innings_' . $match->id, 1);

        // Get team player IDs
        $teamAPlayerIds = $match->teamA?->players?->pluck('id')->toArray() ?? [];
        $teamBPlayerIds = $match->teamB?->players?->pluck('id')->toArray() ?? [];

        // Get all balls
        $allBalls = Ball::where('match_id', $match->id)
            ->orderBy('over')
            ->orderBy('ball_in_over')
            ->get();

        // Separate balls by innings
        $innings1Balls = $allBalls->filter(fn($b) => in_array($b->batsman_id, $teamAPlayerIds));
        $innings2Balls = $allBalls->filter(fn($b) => in_array($b->batsman_id, $teamBPlayerIds));

        // Calculate both innings stats for header display
        $innings1Stats = $this->calculateInningsStats($innings1Balls);
        $innings2Stats = $this->calculateInningsStats($innings2Balls);

        // Filter balls by current innings (based on batsman team)
        if ($currentInnings === 1) {
            $balls = $innings1Balls;
        } else {
            $balls = $innings2Balls;
        }

        // Group by over
        $overs = $balls->groupBy('over');

        $summary = [];
        foreach ($overs as $overNum => $ballsInOver) {
            $overRuns = $ballsInOver->sum('runs') + $ballsInOver->sum('extra_runs');
            $wickets = $ballsInOver->where('is_wicket', 1)->count();

            $ballSummary = $ballsInOver->map(function ($ball) {
                if ($ball->is_wicket) return 'W';
                if ($ball->extra_type === 'wide') return ($ball->runs + $ball->extra_runs) . 'wd';
                if ($ball->extra_type === 'no_ball') return ($ball->runs + $ball->extra_runs) . 'nb';
                if ($ball->extra_type === 'bye') return ($ball->extra_runs) . 'b';
                if ($ball->extra_type === 'leg_bye') return ($ball->extra_runs) . 'lb';
                return (string) $ball->runs;
            })->values();

            $summary[] = [
                'over' => $overNum,
                'balls' => $ballSummary,
                'runs' => $overRuns,
                'wickets' => $wickets,
            ];
        }

        $totalRuns = $balls->sum('runs') + $balls->sum('extra_runs');
        $totalWickets = $balls->where('is_wicket', 1)->count();
        $totalOvers = $overs->count();

        // Get out batsmen for CURRENT innings only
        $outBatsmenIds = $balls->where('is_wicket', 1)->pluck('batsman_id')->toArray();

        // Calculate current players
        $currentStriker = null;
        $currentNonStriker = null;
        $currentBowler = null;
        $needsNewBatsman = false;

        $lastBall = $balls->last();
        if ($lastBall) {
            $lastBatsman = $lastBall->batsman_id;
            $lastBowler = $lastBall->bowler_id;
            $activeBatsmen = $balls->pluck('batsman_id')->unique()
                ->diff($outBatsmenIds)->values();
            $lastBallRuns = $lastBall->runs + ($lastBall->extra_runs ?? 0);
            $isEndOfOver = $lastBall->ball_in_over >= 6 && !in_array($lastBall->extra_type, ['wide', 'no_ball']);

            if ($lastBall->is_wicket) {
                $needsNewBatsman = true;
                $currentStriker = null;
                $currentNonStriker = $activeBatsmen->first();
            } else {
                $shouldSwap = ($lastBallRuns % 2 === 1) xor $isEndOfOver;
                $otherBatsman = $activeBatsmen->filter(fn($id) => $id !== $lastBatsman)->first();

                if ($shouldSwap) {
                    $currentNonStriker = $lastBatsman;
                    // Only set striker to other batsman if they exist and are different
                    $currentStriker = ($otherBatsman && $otherBatsman !== $lastBatsman) ? $otherBatsman : null;
                } else {
                    $currentStriker = $lastBatsman;
                    // Only set non-striker if they exist and are different
                    $currentNonStriker = ($otherBatsman && $otherBatsman !== $lastBatsman) ? $otherBatsman : null;
                }
            }

            if ($isEndOfOver) {
                $previousOver = $balls->where('over', $lastBall->over - 1)->first();
                $currentBowler = $previousOver ? $previousOver->bowler_id : null;
            } else {
                $currentBowler = $lastBowler;
            }
        }

        // Current over balls for display
        $lastOver = collect($summary)->last();
        $currentOverBalls = $lastOver['balls'] ?? [];

        // Check if innings is complete (all out = 10 wickets)
        $isAllOut = $totalWickets >= 10;
        $matchOversLimit = $match->overs ?? 20;

        return response()->json([
            'totalRuns' => $totalRuns,
            'totalWickets' => $totalWickets,
            'totalOvers' => $totalOvers,
            'runRate' => $totalOvers > 0 ? round($totalRuns / $totalOvers, 2) : 0,
            'currentStriker' => $currentStriker,
            'currentNonStriker' => $currentNonStriker,
            'currentBowler' => $currentBowler,
            'needsNewBatsman' => $needsNewBatsman,
            'outBatsmenIds' => $outBatsmenIds,
            'summary' => $summary,
            'currentOverBalls' => $currentOverBalls,
            'currentInnings' => $currentInnings,
            'isAllOut' => $isAllOut,
            'matchOversLimit' => $matchOversLimit,
            // Both innings stats for header display
            'innings1Stats' => $innings1Stats,
            'innings2Stats' => $innings2Stats,
        ]);
    }

    public function edit(Matches $match): View
    {
        $tournaments = Tournament::all();
        $teams = ActualTeam::all();

        return view('backend.pages.matches.edit', compact('match', 'tournaments', 'teams'));
    }

    public function update(Request $request, Matches $match): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'tournament_id' => 'required|exists:tournaments,id',
            'team_a_id' => 'required|different:team_b_id|exists:actual_teams,id',
            'team_b_id' => 'required|exists:actual_teams,id',
            'match_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'overs' => 'nullable|integer|min:1|max:50',
            'venue' => 'nullable|string|max:255',
            'status' => 'nullable|in:upcoming,live,completed',
            'winner_team_id' => 'nullable|exists:actual_teams,id',
        ]);

        $match->update($request->only([
            'name',
            'tournament_id',
            'team_a_id',
            'team_b_id',
            'match_date',
            'start_time',
            'end_time',
            'overs',
            'venue',
            'status',
            'winner_team_id'
        ]));

        return redirect()->route('admin.matches.index')->with('success', 'Match updated successfully.');
    }

    public function addAppreciation(Request $request, Matches $match): RedirectResponse
    {
        $request->validate([
            'player_id' => 'required|exists:players,id',
            'title' => 'required|string|max:255',
        ]);

        MatchAppreciation::create([
            'match_id' => $match->id,
            'player_id' => $request->player_id,
            'title' => $request->title,
        ]);

        return back()->with('success', 'Appreciation assigned successfully.');
    }
    public function editOvers(Matches $match)
    {
        return view('backend.matches.overs', compact('match'));
    }

    public function updateOvers(Request $request, Matches $match)
    {
        $request->validate([
            'overs' => 'required|integer|min:1|max:50',
        ]);

        $match->overs = $request->overs;
        $match->save();

        return redirect()->route('admin.matches.scorecard', $match)->with('success', 'Overs updated successfully!');
    }

    public function destroy(Matches $match): RedirectResponse
    {
        $match->delete();

        return redirect()
            ->route('admin.matches.index')
            ->with('success', 'Match deleted successfully.');
    }

    /**
     * Live Match Ticker Display for Broadcasting (1920x1080)
     * Redirects to public ticker for consistency
     */
    public function liveTicker(Matches $match)
    {
        return redirect()->route('public.live-ticker', $match);
    }

    /**
     * Get list of live/ongoing matches for ticker selection
     */
    public function liveTickerIndex(): View
    {
        $matches = Matches::with(['tournament', 'teamA', 'teamB'])
            ->whereIn('status', ['live', 'scheduled', 'in_progress'])
            ->orWhere('match_date', '>=', now()->subDay())
            ->latest('match_date')
            ->paginate(20);

        return view('backend.pages.matches.live-ticker-index', compact('matches'));
    }
}

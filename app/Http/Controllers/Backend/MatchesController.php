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
            'tossWinner',
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
        $match->load(['teamA.players.player', 'teamB.players.player']);

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

        // Get detailed batsman stats
        $batsmanStats = [];
        $batsmanIds = $balls->pluck('batsman_id')->unique();
        foreach ($batsmanIds as $batsmanId) {
            $playerBalls = $balls->where('batsman_id', $batsmanId);
            $runs = $playerBalls->sum('runs');
            $ballsFaced = $playerBalls->filter(fn($b) => !in_array($b->extra_type, ['wide']))->count();
            $fours = $playerBalls->where('runs', 4)->count();
            $sixes = $playerBalls->where('runs', 6)->count();
            $strikeRate = $ballsFaced > 0 ? round(($runs / $ballsFaced) * 100, 2) : 0;
            $isOut = in_array($batsmanId, $outBatsmenIds);

            // Get player name
            $player = $match->teamA?->players->where('id', $batsmanId)->first()
                ?? $match->teamB?->players->where('id', $batsmanId)->first();

            $batsmanStats[$batsmanId] = [
                'id' => $batsmanId,
                'name' => $player?->player?->name ?? 'Unknown',
                'runs' => $runs,
                'balls' => $ballsFaced,
                'fours' => $fours,
                'sixes' => $sixes,
                'strikeRate' => $strikeRate,
                'isOut' => $isOut,
            ];
        }

        // Get detailed bowler stats
        $bowlerStats = [];
        $bowlerIds = $balls->pluck('bowler_id')->unique();
        foreach ($bowlerIds as $bowlerId) {
            $bowlerBalls = $balls->where('bowler_id', $bowlerId);
            $runsConceded = $bowlerBalls->sum('runs') + $bowlerBalls->sum('extra_runs');
            $wickets = $bowlerBalls->where('is_wicket', 1)->count();
            $legalBalls = $bowlerBalls->filter(fn($b) => !in_array($b->extra_type, ['wide', 'no_ball']))->count();
            $oversDecimal = floor($legalBalls / 6) + (($legalBalls % 6) / 10);
            $overs = floor($legalBalls / 6) . '.' . ($legalBalls % 6);
            $economy = $oversDecimal > 0 ? round($runsConceded / $oversDecimal, 2) : 0;

            // Count maidens (overs with 0 runs - only count complete overs)
            $maidens = 0;
            $bowlerOvers = $bowlerBalls->groupBy('over');
            foreach ($bowlerOvers as $overBalls) {
                $legalInOver = $overBalls->filter(fn($b) => !in_array($b->extra_type, ['wide', 'no_ball']))->count();
                $runsInOver = $overBalls->sum('runs') + $overBalls->sum('extra_runs');
                if ($legalInOver >= 6 && $runsInOver === 0) {
                    $maidens++;
                }
            }

            // Get player name
            $player = $match->teamA?->players->where('id', $bowlerId)->first()
                ?? $match->teamB?->players->where('id', $bowlerId)->first();

            $bowlerStats[$bowlerId] = [
                'id' => $bowlerId,
                'name' => $player?->player?->name ?? 'Unknown',
                'overs' => $overs,
                'maidens' => $maidens,
                'runs' => $runsConceded,
                'wickets' => $wickets,
                'economy' => $economy,
            ];
        }

        // Get striker and non-striker details
        $strikerDetails = $currentStriker ? ($batsmanStats[$currentStriker] ?? null) : null;
        $nonStrikerDetails = $currentNonStriker ? ($batsmanStats[$currentNonStriker] ?? null) : null;
        $bowlerDetails = $currentBowler ? ($bowlerStats[$currentBowler] ?? null) : null;

        // Calculate partnership
        $partnership = ['runs' => 0, 'balls' => 0];
        if ($currentStriker || $currentNonStriker) {
            // Get last wicket index
            $lastWicketIndex = $balls->search(fn($b) => $b->is_wicket);
            $partnershipBalls = $lastWicketIndex !== false
                ? $balls->slice($lastWicketIndex + 1)
                : $balls;

            $partnership['runs'] = $partnershipBalls->sum('runs');
            $partnership['balls'] = $partnershipBalls->filter(fn($b) => !in_array($b->extra_type, ['wide']))->count();
        }

        // Last wicket info
        $lastWicket = null;
        $lastWicketBall = $balls->where('is_wicket', 1)->last();
        if ($lastWicketBall) {
            $outBatsman = $batsmanStats[$lastWicketBall->batsman_id] ?? null;
            $lastWicket = [
                'name' => $outBatsman['name'] ?? 'Unknown',
                'runs' => $outBatsman['runs'] ?? 0,
                'balls' => $outBatsman['balls'] ?? 0,
                'score' => $totalRuns,
            ];
        }

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
            // Detailed player stats
            'strikerDetails' => $strikerDetails,
            'nonStrikerDetails' => $nonStrikerDetails,
            'bowlerDetails' => $bowlerDetails,
            'partnership' => $partnership,
            'lastWicket' => $lastWicket,
            'batsmanStats' => array_values($batsmanStats),
            'bowlerStats' => array_values($bowlerStats),
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
            'toss_winner_team_id' => 'nullable|exists:actual_teams,id',
            'toss_decision' => 'nullable|in:bat,bowl',
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
            'winner_team_id',
            'toss_winner_team_id',
            'toss_decision'
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
     * Get list of matches for ticker selection (recent + upcoming)
     */
    public function liveTickerIndex(): View
    {
        $matches = Matches::with(['tournament', 'teamA', 'teamB'])
            ->where('is_cancelled', false)
            ->where(function ($query) {
                // Show live/upcoming matches, or any match from last 30 days
                $query->whereIn('status', ['live', 'upcoming'])
                      ->orWhere('match_date', '>=', now()->subDays(30));
            })
            ->orderByRaw("CASE WHEN status = 'live' THEN 0 WHEN status = 'upcoming' THEN 1 ELSE 2 END")
            ->orderBy('match_date', 'desc')
            ->paginate(20);

        return view('backend.pages.matches.live-ticker-index', compact('matches'));
    }

    /**
     * Save toss details via AJAX
     */
    public function saveToss(Request $request, Matches $match)
    {
        $validated = $request->validate([
            'toss_winner_team_id' => 'required|exists:actual_teams,id',
            'toss_decision' => 'required|in:bat,bowl',
        ]);

        // Verify the team is part of this match
        if (!in_array($validated['toss_winner_team_id'], [$match->team_a_id, $match->team_b_id])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid team selected'
            ], 422);
        }

        $match->update([
            'toss_winner_team_id' => $validated['toss_winner_team_id'],
            'toss_decision' => $validated['toss_decision'],
            'status' => 'live', // Match goes live when toss is done
        ]);

        $match->load('tossWinner');

        return response()->json([
            'success' => true,
            'message' => 'Toss saved successfully',
            'data' => [
                'toss_winner' => $match->tossWinner?->name,
                'toss_decision' => $match->toss_decision,
                'status' => 'live',
            ]
        ]);
    }

    /**
     * Set match status to live
     */
    public function goLive(Matches $match): RedirectResponse
    {
        $match->update([
            'status' => 'live',
            'is_cancelled' => false,
        ]);

        return redirect()->route('admin.matches.index')->with('success', 'Match is now LIVE!');
    }

    /**
     * Cancel a match with reason
     */
    public function cancelMatch(Request $request, Matches $match): RedirectResponse
    {
        $request->validate([
            'cancellation_reason' => 'nullable|string|max:500',
        ]);

        $match->cancel($request->cancellation_reason);

        return redirect()->route('admin.matches.index')->with('success', 'Match has been cancelled.');
    }

    /**
     * Download all available posters for a match as ZIP
     * Uses tournament's custom templates if available
     */
    public function downloadAllPosters(Matches $match)
    {
        $posters = [];
        $teamAName = \Str::slug($match->teamA?->short_name ?? $match->teamA?->name ?? 'team-a');
        $teamBName = \Str::slug($match->teamB?->short_name ?? $match->teamB?->name ?? 'team-b');
        $matchName = $teamAName . '-vs-' . $teamBName;

        $tournament = $match->tournament;
        $renderService = new \App\Services\Poster\TemplateRenderService();

        // Prepare match data for templates
        $matchData = $this->prepareMatchDataForTemplate($match);

        // Get match poster template (default or active)
        $matchPosterTemplate = $tournament->templates()
            ->where('type', 'match_poster')
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->first();

        // Generate match poster from template
        if ($matchPosterTemplate && $matchPosterTemplate->background_image) {
            try {
                $posterPath = $renderService->renderAndSave($matchPosterTemplate, $matchData, 'match-poster-' . $match->id . '-' . time() . '.png');
                if ($posterPath && \Storage::disk('public')->exists($posterPath)) {
                    $posters[] = [
                        'path' => \Storage::disk('public')->path($posterPath),
                        'name' => $matchName . '-match-poster.png',
                        'temp' => true,
                        'storage_path' => $posterPath,
                    ];
                }
            } catch (\Exception $e) {
                \Log::error('Failed to generate match poster from template: ' . $e->getMessage());
            }
        }

        // Get match summary template (only if match is completed)
        if ($match->status === 'completed') {
            $summaryTemplate = $tournament->templates()
                ->where('type', 'match_summary')
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->first();

            if ($summaryTemplate && $summaryTemplate->background_image) {
                try {
                    $summaryPath = $renderService->renderAndSave($summaryTemplate, $matchData, 'match-summary-' . $match->id . '-' . time() . '.png');
                    if ($summaryPath && \Storage::disk('public')->exists($summaryPath)) {
                        $posters[] = [
                            'path' => \Storage::disk('public')->path($summaryPath),
                            'name' => $matchName . '-match-summary.png',
                            'temp' => true,
                            'storage_path' => $summaryPath,
                        ];
                    }
                } catch (\Exception $e) {
                    \Log::error('Failed to generate summary poster from template: ' . $e->getMessage());
                }
            }
        }

        // Fallback to legacy poster services if no templates found
        if (empty($posters)) {
            // Try enhanced match poster (Kerala League style)
            if (!$match->poster_image || !\Storage::disk('public')->exists($match->poster_image)) {
                try {
                    $posterService = new \App\Services\Poster\EnhancedMatchPosterService();
                    $posterService->generate($match);
                    $match->refresh();
                } catch (\Exception $e) {
                    \Log::error('Failed to generate enhanced fixture poster: ' . $e->getMessage());
                    // Fallback to legacy poster service
                    try {
                        $legacyService = new \App\Services\Poster\MatchPosterService();
                        $legacyService->generate($match);
                        $match->refresh();
                    } catch (\Exception $e2) {
                        \Log::error('Failed to generate legacy fixture poster: ' . $e2->getMessage());
                    }
                }
            }

            if ($match->poster_image && \Storage::disk('public')->exists($match->poster_image)) {
                $posters[] = [
                    'path' => \Storage::disk('public')->path($match->poster_image),
                    'name' => $matchName . '-fixture-poster.png',
                    'temp' => false,
                ];
            }

            // Try legacy summary poster
            if ($match->status === 'completed') {
                $summary = $match->summary;
                if (!$summary) {
                    $summary = $match->summary()->create(['highlights' => [], 'commentary' => null]);
                }
                if (!$summary->summary_poster || !\Storage::disk('public')->exists($summary->summary_poster)) {
                    try {
                        $summaryPosterService = new \App\Services\Poster\MatchSummaryPosterService();
                        $posterPath = $summaryPosterService->generate($match);
                        $summary->update(['summary_poster' => $posterPath]);
                    } catch (\Exception $e) {
                        \Log::error('Failed to generate legacy summary poster: ' . $e->getMessage());
                    }
                }
                $summary->refresh();
                if ($summary->summary_poster && \Storage::disk('public')->exists($summary->summary_poster)) {
                    $posters[] = [
                        'path' => \Storage::disk('public')->path($summary->summary_poster),
                        'name' => $matchName . '-summary-poster.png',
                        'temp' => false,
                    ];
                }
            }
        }

        if (empty($posters)) {
            return back()->with('error', 'No templates found for this tournament. Please create match_poster and match_summary templates first.');
        }

        // If only one poster, download directly
        if (count($posters) === 1) {
            $poster = $posters[0];
            $response = response()->download($poster['path'], $poster['name']);

            // Clean up temp file after download
            if ($poster['temp'] ?? false) {
                $response->deleteFileAfterSend(true);
            }

            return $response;
        }

        // Create ZIP file with all posters
        $zipFileName = $matchName . '-posters-' . now()->format('Ymd-His') . '.zip';
        $zipPath = storage_path('app/temp/' . $zipFileName);

        // Ensure temp directory exists
        if (!is_dir(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', 'Failed to create ZIP file.');
        }

        foreach ($posters as $poster) {
            $zip->addFile($poster['path'], $poster['name']);
        }

        $zip->close();

        // Clean up temp poster files
        foreach ($posters as $poster) {
            if (($poster['temp'] ?? false) && isset($poster['storage_path'])) {
                \Storage::disk('public')->delete($poster['storage_path']);
            }
        }

        return response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);
    }

    /**
     * Generate a single poster for a match using selected template
     */
    public function generatePoster(Request $request, Matches $match)
    {
        $templateId = $request->query('template');
        $tournament = $match->tournament;

        if (!$tournament) {
            return response()->json(['message' => 'Match has no tournament assigned'], 400);
        }

        $renderService = new \App\Services\Poster\TemplateRenderService();
        $matchData = $this->prepareMatchDataForTemplate($match);

        $teamAName = \Str::slug($match->teamA?->short_name ?? $match->teamA?->name ?? 'team-a');
        $teamBName = \Str::slug($match->teamB?->short_name ?? $match->teamB?->name ?? 'team-b');
        $matchName = $teamAName . '-vs-' . $teamBName;

        // Handle built-in enhanced poster
        if ($templateId === 'enhanced') {
            try {
                $posterService = new \App\Services\Poster\EnhancedMatchPosterService();
                $posterPath = $posterService->generate($match);
                $match->refresh();

                if ($match->poster_image && \Storage::disk('public')->exists($match->poster_image)) {
                    return response()->download(
                        \Storage::disk('public')->path($match->poster_image),
                        $matchName . '-match-poster.png'
                    );
                }
            } catch (\Exception $e) {
                \Log::error('Failed to generate enhanced poster: ' . $e->getMessage());
                return response()->json(['message' => 'Failed to generate enhanced poster: ' . $e->getMessage()], 500);
            }
        }

        // Get template from database
        $template = $tournament->templates()->find($templateId);

        if (!$template) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        if (!$template->background_image) {
            return response()->json(['message' => 'Template has no background image'], 400);
        }

        try {
            $posterPath = $renderService->renderAndSave($template, $matchData, 'match-poster-' . $match->id . '-' . time() . '.png');

            if ($posterPath && \Storage::disk('public')->exists($posterPath)) {
                $response = response()->download(
                    \Storage::disk('public')->path($posterPath),
                    $matchName . '-match-poster.png'
                );

                // Clean up temp file after download
                $response->deleteFileAfterSend(true);

                return $response;
            }

            return response()->json(['message' => 'Failed to generate poster'], 500);
        } catch (\Exception $e) {
            \Log::error('Failed to generate poster from template: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to generate poster: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Prepare match data for template rendering
     */
    protected function prepareMatchDataForTemplate(Matches $match): array
    {
        $tournament = $match->tournament;
        $settings = $tournament?->settings;

        // Get captain info for teams
        $teamACaptain = $this->getTeamCaptain($match->teamA);
        $teamBCaptain = $this->getTeamCaptain($match->teamB);

        $data = [
            // Tournament info
            'tournament_name' => $tournament?->name ?? 'Tournament',
            'tournament_logo' => $settings?->logo ?? null,

            // Team A info
            'team_a_name' => $match->teamA?->name ?? 'Team A',
            'team_a_short_name' => $match->teamA?->short_name ?? strtoupper(substr($match->teamA?->name ?? 'TMA', 0, 3)),
            'team_a_logo' => $match->teamA?->team_logo ?? null,
            'team_a_location' => $match->teamA?->location ?? '',
            'team_a_captain_name' => $teamACaptain['name'] ?? '',
            'team_a_captain_image' => $match->teamA?->captain_image ?? $teamACaptain['image'] ?? null,
            'team_a_sponsor_logo' => $match->teamA?->sponsor_logo ?? null,

            // Team B info
            'team_b_name' => $match->teamB?->name ?? 'Team B',
            'team_b_short_name' => $match->teamB?->short_name ?? strtoupper(substr($match->teamB?->name ?? 'TMB', 0, 3)),
            'team_b_logo' => $match->teamB?->team_logo ?? null,
            'team_b_location' => $match->teamB?->location ?? '',
            'team_b_captain_name' => $teamBCaptain['name'] ?? '',
            'team_b_captain_image' => $match->teamB?->captain_image ?? $teamBCaptain['image'] ?? null,
            'team_b_sponsor_logo' => $match->teamB?->sponsor_logo ?? null,

            // Match info
            'match_date' => $match->match_date ? $match->match_date->format('M d, Y') : 'TBA',
            'match_date_day' => $match->match_date ? $match->match_date->format('d') : '',
            'match_date_month' => $match->match_date ? strtoupper($match->match_date->format('M')) : '',
            'match_date_weekday' => $match->match_date ? strtoupper($match->match_date->format('D')) : '',
            'match_time' => $match->start_time ?? 'TBA',
            'match_day' => $match->match_date ? $match->match_date->format('l') : '',
            'venue' => $match->venue ?? $match->location ?? 'TBA',
            'ground_name' => $match->ground?->name ?? $match->venue ?? $match->location ?? 'TBA',
            'match_stage' => $match->stage ?? $match->round ?? 'Group Stage',
            'match_number' => $match->match_number ?? '',
        ];

        // Add result data if match is completed
        if ($match->status === 'completed') {
            $result = $match->result;

            $data['team_a_score'] = $result?->team_a_score ?? ($match->team_a_runs . '/' . $match->team_a_wickets);
            $data['team_b_score'] = $result?->team_b_score ?? ($match->team_b_runs . '/' . $match->team_b_wickets);
            $data['result_summary'] = $result?->result_text ?? $match->result_text ?? '';
            $data['winner_name'] = $match->winner?->name ?? '';

            // Man of the match
            $summary = $match->summary;
            if ($summary && $summary->highlights) {
                $mom = collect($summary->highlights)->firstWhere('type', 'man_of_the_match');
                if ($mom) {
                    $data['man_of_the_match_name'] = $mom['player_name'] ?? '';
                    $data['man_of_the_match_image'] = $mom['player_image'] ?? null;
                }
            }
        }

        // Debug: Log the data being passed
        \Log::info('Template data for match ' . $match->id, $data);

        return $data;
    }

    /**
     * Get captain info from a team
     */
    protected function getTeamCaptain($team): array
    {
        if (!$team) {
            return ['name' => '', 'image' => null];
        }

        // Try to find captain from team members (pivot role = captain or team_manager)
        $captain = $team->members()
            ->wherePivotIn('role', ['captain', 'team_manager', 'Captain', 'Team Manager'])
            ->first();

        if ($captain) {
            // Check if user has a player profile with image
            $player = \App\Models\Player::where('user_id', $captain->id)->first();
            return [
                'name' => $captain->name ?? '',
                'image' => $player?->profile_image ?? $captain->profile_photo_path ?? null,
            ];
        }

        // Fallback: try to get first player from team
        $firstPlayer = $team->players()->with('player')->first();
        if ($firstPlayer && $firstPlayer->player) {
            return [
                'name' => $firstPlayer->player->name ?? '',
                'image' => $firstPlayer->player->profile_image ?? null,
            ];
        }

        return ['name' => '', 'image' => null];
    }
}

<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ActualTeamUser;
use App\Models\Ball;
use App\Models\Matches;
use App\Models\Player;
use App\Models\PlayerAppreciation;
use Illuminate\Http\Request;

class ScorecardController extends Controller
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
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
public function show(Matches $match)
{
    // Eager-load relationships
    $match->load([
        'tournament',
        'teamA.players.player',   // ActualTeam -> ActualTeamUser -> Player (user table)
        'teamB.players.player',
        'winner',
        'appreciations.player'
    ]);

    // Split players team-wise
    $teamAPlayers = $match->teamA->players;
    $teamBPlayers = $match->teamB->players;

    // Appreciations grouped by type
    $appreciations = PlayerAppreciation::with('player.team')
        ->where('match_id', $match->id)
        ->get()
        ->groupBy('type');

    // Get all balls for this match
    $balls = Ball::where('match_id', $match->id)->get();

    // Group by batsman_id and bowler_id for quick lookups
    $battingStats = $balls->groupBy('batsman_id'); // keys = actual_team_user.id
    $bowlingStats = $balls->groupBy('bowler_id');

    // Build over-wise summary (ball-by-ball)
    $oversGrouped = $balls->sortBy(function ($b) {
        return ($b->over * 100) + $b->ball_in_over;
    })->groupBy('over');

    $summary = [];
    foreach ($oversGrouped as $overNum => $ballsInOver) {
        $overRuns = $ballsInOver->sum('runs') + $ballsInOver->sum('extra_run');
        $wickets  = $ballsInOver->where('is_wicket', 1)->count();

        $ballSummary = $ballsInOver->map(function ($ball) {
            if ($ball->is_wicket == 1) return 'W';
            if ($ball->extra_type === 'wide') return ($ball->runs + $ball->extra_run) . 'wd';
            if ($ball->extra_type === 'no_ball') return ($ball->runs + $ball->extra_run) . 'nb';
            return $ball->runs;
        })->values();

        $summary[] = [
            'over'    => $overNum,
            'balls'   => $ballSummary,
            'runs'    => $overRuns,
            'wickets' => $wickets,
        ];
    }

    // === Match Totals ===
    $totalRuns    = $balls->sum('runs') + $balls->sum('extra_run');
    $totalWickets = $balls->where('is_wicket', 1)->count();
    $legalBalls   = $balls->whereNotIn('extra_type', ['wide', 'no_ball'])->count(); // only legal deliveries
    $totalOvers   = floor($legalBalls / 6) . '.' . ($legalBalls % 6);

    return view('backend.pages.matches.scorecard', compact(
        'match',
        'teamAPlayers',
        'teamBPlayers',
        'appreciations',
        'battingStats',
        'bowlingStats',
        'summary',
        'totalRuns',
        'totalWickets',
        'totalOvers'
    ));
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

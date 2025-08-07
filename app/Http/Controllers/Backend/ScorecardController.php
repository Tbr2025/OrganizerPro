<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
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
        $teamAPlayers = Player::where('team_id', $match->team_a_id)->get();
        $teamBPlayers = Player::where('team_id', $match->team_b_id)->get();

        $appreciations = PlayerAppreciation::where('match_id', $match->id)->get();

        $overs = Ball::where('match_id', $match->id)
            ->orderBy('over')
            ->orderBy('ball_in_over')
            ->get()
            ->groupBy('over');

        $summary = [];

        foreach ($overs as $overNum => $balls) {
            $overRuns = $balls->sum('runs') + $balls->sum('extra_runs');
            $wickets = $balls->where('is_wicket', true)->count();

            $ballSummary = $balls->map(function ($ball) {
                if ($ball->is_wicket) return 'W';
                if ($ball->extra_type === 'wide') return ($ball->runs + $ball->extra_runs) . 'wd';
                if ($ball->extra_type === 'no_ball') return ($ball->runs + $ball->extra_runs) . 'nb';
                return $ball->runs;
            });

            $summary[] = [
                'over' => $overNum,
                'balls' => $ballSummary,
                'runs' => $overRuns,
                'wickets' => $wickets,
            ];
        }

        return view('backend.pages.matches.scorecard', compact('match', 'teamAPlayers', 'teamBPlayers', 'appreciations', 'summary'));
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

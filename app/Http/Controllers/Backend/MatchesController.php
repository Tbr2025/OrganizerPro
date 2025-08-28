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
            'venue' => 'nullable|string',
        ]);

        Matches::create($request->only([
            'name',
            'tournament_id',
            'team_a_id',
            'team_b_id',
            'match_date',
            'start_time',
            'end_time',
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

    // Get all balls
    $balls = Ball::where('match_id', $match->id)
        ->orderBy('over')
        ->orderBy('ball_in_over')
        ->get();

    // Group by over for breakdown
    $overs = $balls->groupBy('over');

    $summary = [];
    foreach ($overs as $overNum => $ballsInOver) {
        // Runs + extras in this over
        $overRuns = $ballsInOver->sum('runs') + $ballsInOver->sum('extra_run');

        // Wickets in this over
        $wickets = $ballsInOver->where('is_wicket', 1)->count();

        // Ball-by-ball display
        $ballSummary = $ballsInOver->map(function ($ball) {
            if ($ball->is_wicket) return 'W';
            if ($ball->extra_type === 'wide') return ($ball->runs + $ball->extra_run) . 'wd';
            if ($ball->extra_type === 'no_ball') return ($ball->runs + $ball->extra_run) . 'nb';
            return (string) $ball->runs;
        })->values();

        $summary[] = [
            'over'    => $overNum,
            'balls'   => $ballSummary,
            'runs'    => $overRuns,
            'wickets' => $wickets,
        ];
    }

    // ✅ Match totals
    $totalRuns    = $balls->sum('runs') + $balls->sum('extra_run');
    $totalWickets = $balls->where('is_wicket', 1)->count();
    $totalOvers   = $overs->count(); // or floor($balls->count() / 6)

    // Players split team-wise
    $teamAPlayers = $match->teamA->players;
    $teamBPlayers = $match->teamB->players;

    return view('backend.pages.matches.show', compact(
        'match',
        'summary',
        'teamAPlayers',
        'teamBPlayers',
        'totalRuns',
        'totalWickets',
        'totalOvers'
    ));
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
            'tournament_id' => 'required|exists:tournaments,id',
            'team_a_id' => 'required|different:team_b_id|exists:actual_teams,id',
            'team_b_id' => 'required|exists:actual_teams,id',
            'match_date' => 'required|date',
            'venue' => 'nullable|string',
            'status' => 'in:upcoming,live,completed',
            'winner_team_id' => 'nullable|exists:actual_teams,id',
        ]);

        $match->update($request->only(['tournament_id', 'team_a_id', 'team_b_id', 'match_date', 'venue', 'status', 'winner_team_id']));

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
}

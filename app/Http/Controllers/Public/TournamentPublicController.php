<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Services\Tournament\PointTableService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TournamentPublicController extends Controller
{
    public function __construct(
        private readonly PointTableService $pointTableService
    ) {}

    /**
     * Show tournament landing page
     */
    public function show(Tournament $tournament): View
    {
        $tournament->load(['settings', 'groups.teams', 'champion', 'runnerUp']);

        $settings = $tournament->settings;

        // Get upcoming matches
        $upcomingMatches = $tournament->matches()
            ->with(['teamA', 'teamB', 'ground'])
            ->where('status', 'upcoming')
            ->where('is_cancelled', false)
            ->orderBy('match_date')
            ->limit(5)
            ->get();

        // Get recent results
        $recentResults = $tournament->matches()
            ->with(['teamA', 'teamB', 'result', 'winner'])
            ->where('status', 'completed')
            ->orderByDesc('match_date')
            ->limit(5)
            ->get();

        return view('public.tournament.show', [
            'tournament' => $tournament,
            'settings' => $settings,
            'upcomingMatches' => $upcomingMatches,
            'recentResults' => $recentResults,
        ]);
    }

    /**
     * Show all fixtures
     */
    public function fixtures(Tournament $tournament, Request $request): View
    {
        $tournament->load(['settings', 'groups']);

        $stage = $request->get('stage');
        $groupId = $request->get('group_id');

        $query = $tournament->matches()
            ->with(['teamA', 'teamB', 'ground', 'group', 'result'])
            ->where('is_cancelled', false)
            ->orderBy('match_date')
            ->orderBy('match_number');

        if ($stage) {
            $query->where('stage', $stage);
        }

        if ($groupId) {
            $query->where('tournament_group_id', $groupId);
        }

        $matches = $query->get();

        // Group by date for display
        $matchesByDate = $matches->groupBy(function ($match) {
            return $match->match_date->format('Y-m-d');
        });

        return view('public.tournament.fixtures', [
            'tournament' => $tournament,
            'matches' => $matches,
            'matchesByDate' => $matchesByDate,
            'groups' => $tournament->groups,
            'selectedStage' => $stage,
            'selectedGroupId' => $groupId,
        ]);
    }

    /**
     * Show point table
     */
    public function pointTable(Tournament $tournament): View
    {
        $tournament->load(['settings', 'groups']);

        $pointTableByGroups = $this->pointTableService->getPointTableByGroups($tournament);

        return view('public.tournament.point-table', [
            'tournament' => $tournament,
            'pointTableByGroups' => $pointTableByGroups,
        ]);
    }

    /**
     * Show statistics
     */
    public function statistics(Tournament $tournament, Request $request): View
    {
        $tournament->load('settings');

        $tab = $request->get('tab', 'batting');

        $topBatsmen = $tournament->playerStatistics()
            ->with(['player', 'team'])
            ->where('runs', '>', 0)
            ->orderByDesc('runs')
            ->limit(20)
            ->get();

        $topBowlers = $tournament->playerStatistics()
            ->with(['player', 'team'])
            ->where('wickets', '>', 0)
            ->orderByDesc('wickets')
            ->limit(20)
            ->get();

        $topSixHitters = $tournament->playerStatistics()
            ->with(['player', 'team'])
            ->where('sixes', '>', 0)
            ->orderByDesc('sixes')
            ->limit(10)
            ->get();

        $topFielders = $tournament->playerStatistics()
            ->with(['player', 'team'])
            ->selectRaw('*, (catches + stumpings + run_outs) as total_dismissals')
            ->havingRaw('(catches + stumpings + run_outs) > 0')
            ->orderByDesc('total_dismissals')
            ->limit(10)
            ->get();

        return view('public.tournament.statistics', [
            'tournament' => $tournament,
            'tab' => $tab,
            'topBatsmen' => $topBatsmen,
            'topBowlers' => $topBowlers,
            'topSixHitters' => $topSixHitters,
            'topFielders' => $topFielders,
        ]);
    }

    /**
     * Show teams list
     */
    public function teams(Tournament $tournament): View
    {
        $teams = $tournament->actualTeams()
            ->with(['players.player'])
            ->get();

        return view('public.tournament.teams', [
            'tournament' => $tournament,
            'teams' => $teams,
        ]);
    }
}

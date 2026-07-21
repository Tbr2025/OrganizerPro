<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\MatchAward;
use App\Models\PlayerStatistic;
use App\Models\Tournament;
use Illuminate\Support\Facades\Auth;

class PlayerDashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $player = $user->player;

        if (! $player) {
            return redirect()->route('home');
        }

        // All registrations with tournament info
        $registrations = $player->registrations()
            ->with('tournament')
            ->latest()
            ->get();

        // Status counts
        $statusCounts = [
            'approved' => $registrations->where('status', 'approved')->count(),
            'pending' => $registrations->where('status', 'pending')->count(),
            'rejected' => $registrations->where('status', 'rejected')->count(),
            'queued' => $registrations->where('status', 'queued')->count(),
        ];

        // Latest tournament (for banners)
        $latestRegistration = $registrations->first();
        $tournament = $latestRegistration?->tournament;

        // Career statistics aggregated across all tournaments
        $stats = PlayerStatistic::where('player_id', $player->id)->get();
        $careerStats = [
            'matches' => $stats->sum('matches'),
            'runs' => $stats->sum('runs'),
            'wickets' => $stats->sum('wickets'),
            'catches' => $stats->sum('catches'),
            'highest_score' => $stats->max('highest_score'),
            'fifties' => $stats->sum('fifties'),
            'hundreds' => $stats->sum('hundreds'),
            'best_bowling' => $stats->sortByDesc(function ($s) {
                // Sort by wickets desc, then runs asc for best bowling
                return $s->wickets * 1000 - ($s->runs_conceded ?? 0);
            })->first()?->best_bowling,
        ];

        // Match awards count
        $awardsCount = MatchAward::where('player_id', $player->id)->count();

        return view('backend.pages.player-dashboard.index', compact(
            'player',
            'registrations',
            'statusCounts',
            'tournament',
            'careerStats',
            'awardsCount',
        ));
    }
}

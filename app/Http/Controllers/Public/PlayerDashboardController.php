<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Models\PlayerStatistic;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlayerDashboardController extends Controller
{
    /**
     * Show player's personal dashboard
     */
    public function show(Request $request, Player $player): View
    {
        // Load player with relationships
        $player->load([
            'user',
            'battingProfile',
            'bowlingProfile',
            'playerType',
            'actualTeam',
        ]);

        // Get player statistics across tournaments
        $statistics = PlayerStatistic::with(['tournament', 'team'])
            ->where('player_id', $player->id)
            ->get();

        // Calculate career totals
        $careerStats = [
            'matches' => $statistics->sum('matches'),
            'runs' => $statistics->sum('runs'),
            'wickets' => $statistics->sum('wickets'),
            'catches' => $statistics->sum('catches'),
            'highest_score' => $statistics->max('highest_score'),
            'fifties' => $statistics->sum('fifties'),
            'hundreds' => $statistics->sum('hundreds'),
            'best_bowling' => $this->getBestBowling($statistics),
        ];

        // Get match awards
        $awards = $player->matchAwards()
            ->with(['match.tournament', 'tournamentAward'])
            ->latest()
            ->get();

        // Get appreciations/generated images
        $appreciations = $player->playerAppreciations()
            ->with(['tournament', 'match'])
            ->latest()
            ->get();

        return view('public.player.dashboard', [
            'player' => $player,
            'statistics' => $statistics,
            'careerStats' => $careerStats,
            'awards' => $awards,
            'appreciations' => $appreciations,
        ]);
    }

    /**
     * Get best bowling figures from statistics
     */
    private function getBestBowling($statistics): ?string
    {
        $best = null;
        $bestValue = 0;

        foreach ($statistics as $stat) {
            if ($stat->best_bowling) {
                // Parse "4/25" format
                $parts = explode('/', $stat->best_bowling);
                if (count($parts) === 2) {
                    $wickets = (int) $parts[0];
                    $runs = (int) $parts[1];

                    // Higher wickets is better, then lower runs
                    $value = $wickets * 1000 - $runs;
                    if ($value > $bestValue) {
                        $bestValue = $value;
                        $best = $stat->best_bowling;
                    }
                }
            }
        }

        return $best;
    }

    /**
     * Generate shareable link for player dashboard
     */
    public function generateShareableLink(Player $player): string
    {
        return route('public.player.dashboard', ['player' => $player->id]);
    }

    /**
     * Download player stats card
     */
    public function downloadCard(Player $player)
    {
        // This would generate a stats card image using PHP GD
        // Similar to the welcome image generation
        // For now, redirect to dashboard
        return redirect()->route('public.player.dashboard', $player);
    }
}

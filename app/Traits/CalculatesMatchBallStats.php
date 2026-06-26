<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Ball;
use App\Models\Matches;

/**
 * Shared ball-by-ball aggregation used by the match Result and Summary admin
 * views so both compute identical pre-filled scores from live scoring data.
 */
trait CalculatesMatchBallStats
{
    protected function calculateBallStats(Matches $match): array
    {
        $balls = Ball::where('match_id', $match->id)
            ->orderBy('over')
            ->orderBy('ball_in_over')
            ->get();

        if ($balls->isEmpty()) {
            return [
                'hasBallData' => false,
                'bothInningsComplete' => false,
                'firstInningsComplete' => false,
                'secondInningsStarted' => false,
                'teamA' => ['runs' => 0, 'wickets' => 0, 'overs' => 0, 'extras' => 0],
                'teamB' => ['runs' => 0, 'wickets' => 0, 'overs' => 0, 'extras' => 0],
            ];
        }

        $teamAPlayerIds = $match->teamA?->players?->pluck('id')->toArray() ?? [];
        $teamBPlayerIds = $match->teamB?->players?->pluck('id')->toArray() ?? [];

        $teamABalls = $balls->filter(fn ($b) => in_array($b->batsman_id, $teamAPlayerIds));
        $teamBBalls = $balls->filter(fn ($b) => in_array($b->batsman_id, $teamBPlayerIds));

        $firstInningsComplete = $teamABalls->isNotEmpty();
        $secondInningsStarted = $teamBBalls->isNotEmpty();

        return [
            'hasBallData' => true,
            'bothInningsComplete' => $firstInningsComplete && $secondInningsStarted,
            'firstInningsComplete' => $firstInningsComplete,
            'secondInningsStarted' => $secondInningsStarted,
            'teamA' => $this->calculateInningsStats($teamABalls),
            'teamB' => $this->calculateInningsStats($teamBBalls),
        ];
    }

    protected function calculateInningsStats($balls): array
    {
        if ($balls->isEmpty()) {
            return ['runs' => 0, 'wickets' => 0, 'overs' => 0, 'extras' => 0];
        }

        $totalRuns = $balls->sum('runs') + $balls->sum('extra_runs');
        $totalWickets = $balls->where('is_wicket', 1)->count();
        $totalExtras = $balls->sum('extra_runs');

        $legalBalls = $balls->filter(fn ($b) => !in_array($b->extra_type, ['wide', 'no_ball']))->count();
        $completedOvers = floor($legalBalls / 6);
        $ballsInOver = $legalBalls % 6;
        $overs = $completedOvers + ($ballsInOver / 10); // 10.3 = 10 overs 3 balls

        return [
            'runs' => $totalRuns,
            'wickets' => $totalWickets,
            'overs' => round($overs, 1),
            'extras' => $totalExtras,
        ];
    }
}

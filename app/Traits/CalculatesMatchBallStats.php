<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Matches;
use App\Services\Scoring\MatchStateService;

/**
 * Shared ball-by-ball aggregation used by the match Result and Summary admin
 * views so both compute identical pre-filled scores from live scoring data.
 *
 * Team-keyed (teamA / teamB), whoever batted first — the numbers and the
 * result come from MatchStateService, which knows the batting order and who
 * chased. (This used to assume team A batted first and called any team-B win
 * "by wickets".)
 */
trait CalculatesMatchBallStats
{
    protected function calculateBallStats(Matches $match): array
    {
        $snapshot = app(MatchStateService::class)->snapshot($match);
        $first = $snapshot['innings'][1];
        $second = $snapshot['innings'][2];

        $line = fn (array $i) => [
            'runs' => $i['runs'],
            'wickets' => $i['wickets'],
            'overs' => round($i['completed_overs'] + ($i['legal_balls'] % 6) / 10, 1), // 10.3 = 10 overs 3 balls
            'extras' => $i['extras']['total'],
        ];
        $forTeam = fn (int $teamId) => $first['batting_team_id'] === $teamId ? $line($first) : $line($second);

        $hasBallData = $first['balls_bowled'] > 0 || $second['balls_bowled'] > 0;

        return [
            'hasBallData' => $hasBallData,
            'bothInningsComplete' => $first['balls_bowled'] > 0 && $second['balls_bowled'] > 0,
            'firstInningsComplete' => $first['balls_bowled'] > 0,
            'secondInningsStarted' => $second['balls_bowled'] > 0,
            'teamABattingFirst' => $first['batting_team_id'] === (int) $match->team_a_id,
            'teamA' => $forTeam((int) $match->team_a_id),
            'teamB' => $forTeam((int) $match->team_b_id),
            // winner_team_id / type (runs|wickets|tie) / margin, once the chase is over.
            'result' => $snapshot['result'],
        ];
    }
}

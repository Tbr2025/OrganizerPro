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
        $line = fn (int $teamId) => [
            'runs' => $snapshot['teams'][$teamId]['runs'] ?? 0,
            'wickets' => $snapshot['teams'][$teamId]['wickets'] ?? 0,
            'overs' => (float) ($snapshot['teams'][$teamId]['overs'] ?? 0), // "10.3" = 10 overs 3 balls
            'extras' => $snapshot['teams'][$teamId]['extras'] ?? 0,
        ];
        $played = collect($snapshot['innings'])->filter(fn ($i) => $i['balls_bowled'] > 0);
        $last = $snapshot['innings'][$snapshot['total_innings']];

        return [
            'hasBallData' => $played->isNotEmpty(),
            'bothInningsComplete' => $snapshot['match_complete'] || $last['balls_bowled'] > 0,
            'firstInningsComplete' => $snapshot['innings'][1]['balls_bowled'] > 0,
            'secondInningsStarted' => $snapshot['innings'][2]['balls_bowled'] > 0,
            'inningsPlayed' => $played->count(),
            'teamABattingFirst' => $snapshot['batting_first_team_id'] === (int) $match->team_a_id,
            'teamA' => $line((int) $match->team_a_id),
            'teamB' => $line((int) $match->team_b_id),
            // winner_team_id / type (runs|wickets|tie) / margin, once the chase is over.
            'result' => $snapshot['result'],
        ];
    }
}

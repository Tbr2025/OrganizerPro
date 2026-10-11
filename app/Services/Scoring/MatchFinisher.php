<?php

declare(strict_types=1);

namespace App\Services\Scoring;

use App\Events\MatchScoreUpdated;
use App\Models\MatchResult;
use App\Models\Matches;
use App\Services\LedWall\LedQueueService;
use App\Services\Tournament\PlayerStatisticService;
use App\Services\Tournament\PointTableService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Ending a match from the scorer's page: the natural result once the chase is
 * over, the scorer's call when it was cut short, or no result when it was
 * abandoned (rain, bad light, ground). Writes the result row the summary,
 * posters and points table read, and closes scoring until it is reopened.
 */
class MatchFinisher
{
    public const OUTCOMES = ['auto', 'team_a', 'team_b', 'tie', 'no_result', 'abandoned'];

    public const ABANDON_REASONS = ['Rain', 'Bad light', 'Wet ground', 'Time ran out', 'Other'];

    public function __construct(private readonly MatchStateService $state) {}

    /** @return string the result line, e.g. "Lions won by 12 runs" */
    public function end(Matches $match, string $outcome, ?string $reason = null): string
    {
        $summary = DB::transaction(function () use ($match, $outcome, $reason) {
            $match = Matches::whereKey($match->id)->lockForUpdate()->firstOrFail();
            $s = $this->state->snapshot($match);

            $teamA = (int) $match->team_a_id;
            $teamB = (int) $match->team_b_id;
            $line = fn (int $team) => collect($s['innings'])->first(fn ($i) => (int) $i['batting_team_id'] === $team);
            $a = $line($teamA);
            $b = $line($teamB);

            [$type, $winner, $margin] = match ($outcome) {
                'auto' => $s['result'] && $s['result']['type'] !== 'manual'
                    ? [$s['result']['type'], $s['result']['winner_team_id'], $s['result']['margin']]
                    : throw ValidationException::withMessages(['outcome' => 'The match is not over yet — choose who won, a tie, or no result.']),
                'team_a', 'team_b' => $this->winnerBy($outcome === 'team_a' ? $teamA : $teamB, $s),
                'tie' => ['tie', null, 0],
                'no_result', 'abandoned' => ['no_result', null, 0],
            };

            $result = MatchResult::updateOrCreate(['match_id' => $match->id], [
                'team_a_score' => $a['runs'] ?? 0,
                'team_a_wickets' => $a['wickets'] ?? 0,
                'team_a_overs' => $a['overs'] ?? '0.0',
                'team_b_score' => $b['runs'] ?? 0,
                'team_b_wickets' => $b['wickets'] ?? 0,
                'team_b_overs' => $b['overs'] ?? '0.0',
                'team_a_batting_first' => (int) $s['batting_first_team_id'] === $teamA,
                'result_type' => $type,
                'winner_team_id' => $winner,
                'margin' => $margin,
            ]);

            $text = $result->generateResultSummary();
            if ($outcome === 'abandoned') {
                $why = trim((string) $reason) !== '' ? trim((string) $reason) : 'no reason given';
                $text = "Match abandoned ({$why}) — no result";
                $result->match_notes = trim(($result->match_notes ? $result->match_notes . "\n" : '') . "Abandoned: {$why}");
            }
            $result->result_summary = $text;
            $result->save();

            $match->forceFill([
                'status' => 'completed',
                'winner_team_id' => $winner,
                'scoring_ended_at' => now(),
                'end_reason' => $text,
                'score_version' => (int) $match->score_version + 1,
            ])->save();

            return $text;
        });

        $this->afterEnd($match->fresh());

        return $summary;
    }

    /** A mistake: take balls again. The result row stays until the match ends again. */
    public function reopen(Matches $match): void
    {
        $match->forceFill([
            'status' => 'live',
            'scoring_ended_at' => null,
            'end_reason' => null,
            'score_version' => (int) $match->score_version + 1,
        ])->save();

        $this->afterEnd($match->fresh());
    }

    /** Winner chosen by the scorer: by wickets if they batted second, else by runs. */
    private function winnerBy(int $winner, array $s): array
    {
        $second = $s['innings'][2];
        $first = $s['innings'][1];

        if ((int) $second['batting_team_id'] === $winner) {
            return ['wickets', $winner, max(0, $second['wicket_limit'] - $second['wickets'])];
        }

        return ['runs', $winner, max(0, $first['runs'] - $second['runs'])];
    }

    private function afterEnd(Matches $match): void
    {
        foreach ([
            fn () => $match->isGroupStage() && app(PointTableService::class)->updateFromMatchResult($match),
            fn () => app(PlayerStatisticService::class)->updateFromMatch($match),
            fn () => MatchScoreUpdated::announce($match->id, (int) $match->score_version),
            fn () => app(LedQueueService::class)->onMatchEnd($match),
        ] as $step) {
            try {
                $step();
            } catch (Throwable $e) {
                // The result is saved; a side effect failing must not undo it.
                report($e);
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Scoring;

use App\Events\MatchScoreUpdated;
use App\Models\ActualTeamUser;
use App\Models\Ball;
use App\Models\Matches;
use App\Services\LedWall\LedQueueService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Writes to the ball log, one delivery at a time, under a row lock on the match.
 *
 *  - The innings comes from matches.current_innings, never a browser session.
 *  - Who is at the crease and who bowls next is stored on the match after every
 *    change, so the wall and a second scorer read the same thing.
 *  - matches.score_version goes up on every change. A scorer sending the version
 *    they were looking at is refused if someone else scored in between, instead
 *    of silently stacking a second ball on top.
 *  - event_uuid makes a retried request (flaky ground Wi-Fi) record once.
 */
class BallRecorder
{
    public function __construct(private readonly MatchStateService $state) {}

    /**
     * @param  array{batsman_id:int, non_striker_id:int, bowler_id:int, runs:int, extra_type:?string,
     *               extra_runs:int, is_wicket:bool, dismissal_type:?string, out_batsman:?string,
     *               fielder_id:?int, event_uuid:?string, expected_version:?int}  $input
     * @return array{ball: Ball, duplicate: bool}
     */
    public function record(Matches $match, array $input): array
    {
        $result = DB::transaction(function () use ($match, $input) {
            /** @var Matches $match */
            $match = Matches::whereKey($match->id)->lockForUpdate()->firstOrFail();

            if (! empty($input['event_uuid'])) {
                $existing = Ball::where('event_uuid', $input['event_uuid'])->first();
                if ($existing) {
                    abort_unless((int) $existing->match_id === (int) $match->id, 422, 'That delivery belongs to another match.');

                    return ['ball' => $existing, 'duplicate' => true, 'before' => null, 'after' => null];
                }
            }

            $this->assertVersion($match, $input['expected_version'] ?? null);

            $before = $this->state->snapshot($match);
            $innings = $before['innings'][$before['current_innings']];

            if ($before['match_complete']) {
                $this->fail('innings_complete', 'The match is over.');
            }
            if ($innings['complete']) {
                $this->fail('innings_complete', 'This innings is complete. Start the next innings to keep scoring.');
            }

            $striker = (int) $input['batsman_id'];
            $nonStriker = (int) $input['non_striker_id'];
            $bowler = (int) $input['bowler_id'];

            $this->assertOnTeam([$striker, $nonStriker], $innings['batting_team_id'], 'batsman_id', 'The batters must be from the batting side.');
            $this->assertOnTeam([$bowler], $innings['bowling_team_id'], 'bowler_id', 'The bowler must be from the fielding side.');
            if (! empty($input['fielder_id'])) {
                $this->assertOnTeam([(int) $input['fielder_id']], $innings['bowling_team_id'], 'fielder_id', 'The fielder must be from the fielding side.');
            }
            // A new over: not the bowler of the last one, and not past the quota.
            $newOver = $innings['balls_bowled'] === 0 || $innings['over_complete'];
            if ($newOver && $innings['balls_bowled'] > 0) {
                $lastOver = end($innings['overs_summary']) ?: [];
                if ((int) ($lastOver['bowler_id'] ?? 0) === $bowler) {
                    $this->fail('bowler_id', 'This bowler bowled the last over. Choose a different bowler for the new over.');
                }
            }
            if ($newOver) {
                $figures = collect($innings['bowlers'])->firstWhere('id', $bowler);
                if ($figures && $figures['overs_count'] >= $before['bowler_quota']) {
                    $this->fail('bowler_id', "This bowler has bowled their {$before['bowler_quota']} overs — the most allowed in this match.");
                }
            }
            if ($striker === $nonStriker) {
                $this->fail('non_striker_id', 'Striker and non-striker cannot be the same player.');
            }
            foreach ([$striker, $nonStriker] as $id) {
                if (in_array($id, $innings['out_batter_ids'], true)) {
                    $this->fail('batsman_id', 'That batter is already out.');
                }
            }

            $data = [
                'event_uuid' => $input['event_uuid'] ?? null,
                'match_id' => $match->id,
                'innings' => $before['current_innings'],
                'batsman_id' => $striker,
                'non_striker_id' => $nonStriker,
                'bowler_id' => $bowler,
                'runs' => (int) $input['runs'],
                'extra_type' => ($input['extra_type'] ?? null) ?: null,
                'extra_runs' => (int) ($input['extra_runs'] ?? 0),
                'is_wicket' => (bool) ($input['is_wicket'] ?? false),
                'dismissal_type' => null,
                'out_batsman_id' => null,
                'fielder_id' => ! empty($input['fielder_id']) ? (int) $input['fielder_id'] : null,
                'over' => $before['next_ball']['over'],
                'ball_in_over' => $before['next_ball']['ball_in_over'],
            ];

            if (in_array($data['extra_type'], ['wide', 'no_ball'], true) && $data['extra_runs'] < 1) {
                $data['extra_runs'] = 1; // the penalty run is part of the extra
            }
            if ($data['extra_type'] === 'wide') {
                $data['runs'] = 0; // nothing comes off the bat on a wide
            }

            if ($data['is_wicket']) {
                $type = ($input['dismissal_type'] ?? null) ?: 'bowled';
                $data['dismissal_type'] = $type;
                $runOut = $type === 'run_out';
                $data['out_batsman_id'] = $runOut && ($input['out_batsman'] ?? null) === 'non_striker' ? $nonStriker : $striker;

                // Only dismissals the Laws allow off this delivery: a no-ball,
                // bye or free hit can only be a run-out; a wide also allows
                // stumped and hit wicket.
                $delivery = $data['extra_type'] ?? 'legal';
                $allowed = MatchStateService::DISMISSALS_ON[$delivery];
                if ($innings['free_hit']) {
                    $allowed = array_values(array_intersect($allowed, MatchStateService::DISMISSALS_ON['free_hit']));
                }
                if (! in_array($type, $allowed, true)) {
                    $what = $innings['free_hit'] ? 'a free hit' : match ($delivery) {
                        'wide' => 'a wide', 'no_ball' => 'a no-ball', 'bye' => 'a bye', 'leg_bye' => 'a leg bye', default => 'this ball',
                    };
                    $this->fail('dismissal_type', 'A batter cannot be out ' . str_replace('_', ' ', $type) . " off {$what}. Only: " . implode(', ', array_map(fn ($t) => str_replace('_', ' ', $t), $allowed)) . '.');
                }

                // Only a run-out happens with runs being taken; the others end
                // the delivery (a wide keeps its penalty run).
                if (! $runOut) {
                    $data['runs'] = 0;
                    if ($data['extra_type'] === 'wide') {
                        $data['extra_runs'] = 1;
                    }
                }
                if (! in_array($type, ['caught', 'run_out', 'stumped'], true)) {
                    $data['fielder_id'] = null;
                }
            }

            $ball = Ball::create($data);

            $after = $this->storeState($match);

            return ['ball' => $ball, 'duplicate' => false, 'before' => $before, 'after' => $after];
        });

        // After commit: the screens learn about it only once it is real.
        if (! $result['duplicate']) {
            $this->announce($match, function (Matches $m) use ($result) {
                app(LedQueueService::class)->onBall($m, $result['ball'], $result['before'], $result['after']);
            });
        }

        return ['ball' => $result['ball'], 'duplicate' => $result['duplicate']];
    }

    /** Undo the most recent delivery of the match. */
    public function undo(Matches $match, Ball $ball, ?int $expectedVersion = null): void
    {
        DB::transaction(function () use ($match, $ball, $expectedVersion) {
            $match = Matches::whereKey($match->id)->lockForUpdate()->firstOrFail();
            $this->assertVersion($match, $expectedVersion);

            $last = Ball::where('match_id', $match->id)->orderByDesc('id')->first();
            if (! $last || (int) $last->id !== (int) $ball->id) {
                $this->fail('ball', 'Only the most recent delivery can be undone.');
            }

            $ball->delete();

            // Back to exactly who was in when that ball was bowled.
            $match->forceFill([
                'striker_id' => $ball->batsman_id,
                'non_striker_id' => $ball->non_striker_id,
                'current_bowler_id' => $ball->bowler_id,
                'score_version' => (int) $match->score_version + 1,
            ])->save();
        });

        $this->announce($match, fn (Matches $m) => app(LedQueueService::class)->onUndo($m, (int) $ball->id));
    }

    /**
     * Move to an innings. With $to, idempotent: a double click (or a second
     * scorer) lands on the same innings instead of flipping back. Without it,
     * the next innings (or back one from the last, the old toggle).
     */
    public function switchInnings(Matches $match, ?int $to = null): int
    {
        $from = (int) $match->current_innings;
        $next = DB::transaction(function () use ($match, $to) {
            $match = Matches::whereKey($match->id)->lockForUpdate()->firstOrFail();
            if ($match->scoring_ended_at) {
                $this->fail('innings_complete', 'The match has been ended. Reopen scoring to change innings.');
            }
            $total = app(MatchStateService::class)->totalInnings($match);
            $now = (int) $match->current_innings;
            if ($to !== null && ($to < 1 || $to > $total)) {
                $this->fail('innings', "This match has {$total} innings.");
            }
            $next = $to ?? ($now < $total ? $now + 1 : $now - 1);
            if ($next === (int) $match->current_innings) {
                return $next;
            }

            $match->forceFill([
                'current_innings' => $next,
                'striker_id' => null,
                'non_striker_id' => null,
                'current_bowler_id' => null,
                'score_version' => (int) $match->score_version + 1,
            ])->save();

            return $next;
        });

        // Every step forward is a break: the wall puts up the innings just played.
        $this->announce($match, fn (Matches $m) => $next > $from
            ? app(LedQueueService::class)->onInningsBreak($m) : null);

        return $next;
    }

    /**
     * Fix who bats first from the toss — only while nothing has been bowled,
     * so a toss corrected mid-match cannot reshuffle innings already played.
     */
    public function applyToss(Matches $match): void
    {
        if (Ball::where('match_id', $match->id)->exists()) {
            return;
        }

        $winner = (int) $match->toss_winner_team_id;
        $other = $winner === (int) $match->team_a_id ? (int) $match->team_b_id : (int) $match->team_a_id;

        $match->forceFill([
            'batting_first_team_id' => $match->toss_decision === 'bowl' ? $other : $winner,
            'current_innings' => 1,
            'score_version' => (int) $match->score_version + 1,
        ])->save();

        $this->announce($match);
    }

    /**
     * Tell the screens the score moved, then run any wall work. Never lets a
     * broadcast or queue failure undo a ball that is already committed.
     */
    private function announce(Matches $match, ?callable $then = null): void
    {
        try {
            $fresh = Matches::find($match->id);
            if (! $fresh) {
                return;
            }
            MatchScoreUpdated::announce($fresh->id, (int) $fresh->score_version);
            if ($then) {
                $then($fresh);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function storeState(Matches $match): array
    {
        $after = $this->state->snapshot($match);
        $innings = $after['innings'][$after['current_innings']];

        $match->forceFill([
            'striker_id' => $innings['striker_id'],
            'non_striker_id' => $innings['non_striker_id'],
            'current_bowler_id' => $innings['bowler_id'],
            'score_version' => (int) $match->score_version + 1,
        ])->save();

        return $after;
    }

    private function assertVersion(Matches $match, ?int $expected): void
    {
        if ($expected !== null && $expected !== (int) $match->score_version) {
            throw ValidationException::withMessages([
                'stale' => 'The score changed on another screen. Refresh to see the latest before scoring.',
            ])->status(409);
        }
    }

    /** @param int[] $ids */
    private function assertOnTeam(array $ids, int $teamId, string $field, string $message): void
    {
        $count = ActualTeamUser::whereIn('id', $ids)->where('actual_team_id', $teamId)->count();
        if ($count !== count(array_unique($ids))) {
            $this->fail($field, $message);
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}

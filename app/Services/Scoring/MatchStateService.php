<?php

declare(strict_types=1);

namespace App\Services\Scoring;

use App\Models\ActualTeamUser;
use App\Models\Ball;
use App\Models\Matches;
use Illuminate\Support\Collection;

/**
 * Everything a scoreboard shows, derived from the ball log.
 *
 * The balls table is the record; totals, figures, strike and the result are
 * all re-derived from it here, so an undo or correction can never leave a
 * stale total behind. The scorer page, the scorecard, the auto-created result
 * and the live screens read this one function instead of each re-doing the
 * arithmetic (four copies had drifted: overs counted as over *groups*, economy
 * divided by "2.3" overs, byes charged to the bowler, run-outs credited to him,
 * the partnership measured from the first wicket rather than the last).
 *
 * Conventions — see App\Models\Ball:
 *  - runs is off the bat; extra_runs is the whole extra (plain wide = 1).
 *  - wides and no-balls are not legal deliveries; a no-ball is a ball faced.
 *  - bye/leg-bye runs are extras the bowler is not charged.
 */
class MatchStateService
{
    /** Dismissals the bowler is credited with. Run out (and retirements) are not. */
    public const BOWLER_WICKETS = ['bowled', 'caught', 'lbw', 'stumped', 'hit_wicket'];

    /** Smallest named line-up taken as the side's real size (see wicketLimit()). */
    public const MIN_LINEUP = 5;

    public function snapshot(Matches $match): array
    {
        $match->loadMissing(['teamA.players.player', 'teamB.players.player', 'lineups']);

        $balls = Ball::where('match_id', $match->id)->orderBy('id')->get();

        return $this->build($match, $balls, $this->names($match, $balls));
    }

    /**
     * Pure: the same match + balls always give the same snapshot.
     *
     * @param  Collection<int, Ball>  $balls  in the order they were bowled
     * @param  array<int, array{name: string, team_id: int|null}>  $names  keyed by actual_team_users.id
     */
    public function build(Matches $match, Collection $balls, array $names): array
    {
        $firstTeamId = (int) ($match->batting_first_team_id ?: $match->team_a_id);
        $secondTeamId = (int) ($firstTeamId === (int) $match->team_a_id ? $match->team_b_id : $match->team_a_id);
        $teamFor = fn (int $innings) => $innings === 1 ? $firstTeamId : $secondTeamId;
        $maxBalls = max(1, (int) ($match->overs ?: 20)) * 6;

        // Old rows may lack `innings`; place them by the batsman's team, as the
        // pre-innings-column code did.
        $byInnings = [1 => [], 2 => []];
        foreach ($balls as $ball) {
            $innings = $ball->innings ?: $this->inningsFromTeam($names[$ball->batsman_id]['team_id'] ?? null, $firstTeamId, $secondTeamId);
            if ($innings === 1 || $innings === 2) {
                $byInnings[$innings][] = $ball;
            }
        }

        $innings = [];
        foreach ([1, 2] as $n) {
            $innings[$n] = $this->innings(
                $n,
                $byInnings[$n],
                $teamFor($n),
                $teamFor($n === 1 ? 2 : 1),
                $this->wicketLimit($match, $teamFor($n)),
                $maxBalls,
                $names,
            );
        }

        $target = $innings[1]['balls_bowled'] > 0 || (int) $match->current_innings === 2
            ? $innings[1]['runs'] + 1
            : null;

        // Innings 1 is over once it is complete OR the chase has started.
        $innings[1]['complete'] = $innings[1]['complete'] || $innings[2]['balls_bowled'] > 0;
        if ($target !== null && $innings[2]['runs'] >= $target) {
            $innings[2]['complete'] = true;
        }

        $current = in_array((int) $match->current_innings, [1, 2], true) ? (int) $match->current_innings : 1;
        $live = $innings[$current];

        $chase = null;
        if ($current === 2 && $target !== null) {
            $ballsLeft = max(0, $maxBalls - $live['legal_balls']);
            $need = max(0, $target - $live['runs']);
            $chase = [
                'target' => $target,
                'runs_needed' => $need,
                'balls_left' => $ballsLeft,
                'required_run_rate' => $ballsLeft > 0 ? round($need / ($ballsLeft / 6), 2) : null,
            ];
        }

        $result = $innings[2]['complete'] ? $this->result($innings, $target) : null;

        return [
            'match_id' => (int) $match->id,
            'score_version' => (int) ($match->score_version ?? 0),
            'overs_limit' => intdiv($maxBalls, 6),
            'batting_first_team_id' => $firstTeamId,
            'current_innings' => $current,
            'innings' => $innings,
            'teams' => [
                $firstTeamId => $this->teamLine($innings[1]),
                $secondTeamId => $this->teamLine($innings[2]),
            ],
            'target' => $target,
            'chase' => $chase,
            'result' => $result,
            'match_complete' => $result !== null,
            'next_ball' => [
                'over' => intdiv($live['legal_balls'], 6) + 1,
                'ball_in_over' => ($live['legal_balls'] % 6) + 1,
            ],
            'top_batters' => $this->topBatters($innings),
            'top_bowlers' => $this->topBowlers($innings),
        ];
    }

    /**
     * @param  array<int, Ball>  $balls
     */
    private function innings(int $number, array $balls, int $battingTeamId, int $bowlingTeamId, int $wicketLimit, int $maxBalls, array $names): array
    {
        $runs = 0;
        $wickets = 0;
        $legal = 0;
        $extras = ['wides' => 0, 'no_balls' => 0, 'byes' => 0, 'leg_byes' => 0];
        $batters = [];
        $bowlers = [];
        $fow = [];
        $overs = [];
        $outIds = [];
        $partnership = ['runs' => 0, 'balls' => 0];

        // Who is where, replayed delivery by delivery.
        $striker = null;
        $nonStriker = null;
        $lastBall = null;
        $overEnded = false;

        $name = fn ($id) => $id ? ($names[$id]['name'] ?? 'Unknown') : null;
        $batter = function ($id) use (&$batters, $name) {
            if ($id && ! isset($batters[$id])) {
                $batters[$id] = ['id' => (int) $id, 'name' => $name($id), 'runs' => 0, 'balls' => 0, 'fours' => 0, 'sixes' => 0, 'out' => false, 'how_out' => null];
            }
        };

        foreach ($balls as $ball) {
            $type = $ball->extra_type;
            $isLegal = ! in_array($type, ['wide', 'no_ball'], true);
            $bat = (int) $ball->runs;
            $extra = (int) $ball->extra_runs;
            $overNumber = intdiv($legal, 6) + 1;

            // --- positions before the ball ---
            $striker = (int) $ball->batsman_id;
            if ($ball->non_striker_id) {
                $nonStriker = (int) $ball->non_striker_id;
            } elseif ($nonStriker === $striker || ($nonStriker !== null && in_array($nonStriker, $outIds, true))) {
                $nonStriker = null;
            }
            if ($nonStriker === null) {
                // Legacy rows carry no non-striker: the other not-out batter seen so far.
                foreach (array_keys($batters) as $id) {
                    if ($id !== $striker && ! in_array($id, $outIds, true)) {
                        $nonStriker = $id;
                    }
                }
            }
            $batter($striker);
            $batter($nonStriker);

            // --- team ---
            $runs += $bat + $extra;
            $partnership['runs'] += $bat + $extra;
            match ($type) {
                'wide' => $extras['wides'] += $extra,
                'no_ball' => $extras['no_balls'] += $extra,
                'bye' => $extras['byes'] += $extra,
                'leg_bye' => $extras['leg_byes'] += $extra,
                default => null,
            };

            // --- batter ---
            $batters[$striker]['runs'] += $bat;
            if ($type !== 'wide') {
                $batters[$striker]['balls']++;
            }
            if ($bat === 4) {
                $batters[$striker]['fours']++;
            } elseif ($bat === 6) {
                $batters[$striker]['sixes']++;
            }

            // --- bowler ---
            $bowlerId = (int) $ball->bowler_id;
            if (! isset($bowlers[$bowlerId])) {
                $bowlers[$bowlerId] = ['id' => $bowlerId, 'name' => $name($bowlerId), 'legal_balls' => 0, 'runs' => 0, 'wickets' => 0, 'maidens' => 0, 'wides' => 0, 'no_balls' => 0, 'overs_bowled' => []];
            }
            $conceded = $bat + (in_array($type, ['wide', 'no_ball'], true) ? $extra : 0);
            $bowlers[$bowlerId]['runs'] += $conceded;
            if ($type === 'wide') {
                $bowlers[$bowlerId]['wides'] += $extra;
            } elseif ($type === 'no_ball') {
                $bowlers[$bowlerId]['no_balls'] += $extra;
            }
            $o = &$bowlers[$bowlerId]['overs_bowled'][$overNumber];
            $o = ['legal' => ($o['legal'] ?? 0) + ($isLegal ? 1 : 0), 'conceded' => ($o['conceded'] ?? 0) + $conceded];
            unset($o);

            if ($isLegal) {
                $legal++;
                $bowlers[$bowlerId]['legal_balls']++;
                $partnership['balls']++;
            }

            // --- over summary ---
            if (! isset($overs[$overNumber])) {
                $overs[$overNumber] = ['over' => $overNumber, 'bowler_id' => $bowlerId, 'balls' => [], 'runs' => 0, 'wickets' => 0];
            }
            $overs[$overNumber]['balls'][] = $this->label($ball);
            $overs[$overNumber]['runs'] += $bat + $extra;

            // --- wicket ---
            if ($ball->is_wicket) {
                $outId = (int) ($ball->out_batsman_id ?: $striker);
                $batter($outId);
                $wickets++;
                $outIds[] = $outId;
                $overs[$overNumber]['wickets']++;
                $batters[$outId]['out'] = true;
                $batters[$outId]['how_out'] = $this->howOut($ball, $name);
                if (in_array($ball->dismissal_type, self::BOWLER_WICKETS, true)) {
                    $bowlers[$bowlerId]['wickets']++;
                }
                $fow[] = [
                    'wicket' => $wickets,
                    'score' => $runs,
                    'overs' => $this->overs($legal),
                    'batter_id' => $outId,
                    'name' => $name($outId),
                ];
                $partnership = ['runs' => 0, 'balls' => 0];
            }

            // --- positions after the ball ---
            // Runs actually run: bat runs, plus byes/leg-byes, plus anything
            // run on a wide/no-ball beyond its one-run penalty.
            $ran = $bat + match ($type) {
                'bye', 'leg_bye' => $extra,
                'wide', 'no_ball' => max(0, $extra - 1),
                default => 0,
            };
            if ($ran % 2 === 1) {
                [$striker, $nonStriker] = [$nonStriker, $striker];
            }
            if ($ball->is_wicket) {
                $outId = (int) ($ball->out_batsman_id ?: $ball->batsman_id);
                if ($striker === $outId) {
                    $striker = null;
                } elseif ($nonStriker === $outId) {
                    $nonStriker = null;
                }
            }
            $overEnded = $isLegal && $legal % 6 === 0;
            if ($overEnded) {
                [$striker, $nonStriker] = [$nonStriker, $striker];
            }

            $lastBall = $ball;
        }

        foreach ($bowlers as &$b) {
            foreach ($b['overs_bowled'] as $over) {
                if ($over['legal'] >= 6 && $over['conceded'] === 0) {
                    $b['maidens']++;
                }
            }
            unset($b['overs_bowled']);
            $b['overs'] = $this->overs($b['legal_balls']);
            $b['economy'] = $b['legal_balls'] > 0 ? round($b['runs'] / ($b['legal_balls'] / 6), 2) : 0.0;
        }
        unset($b);

        foreach ($batters as &$b) {
            $b['strike_rate'] = $b['balls'] > 0 ? round($b['runs'] / $b['balls'] * 100, 2) : 0.0;
        }
        unset($b);

        // Bowler for the next ball: the same one mid-over; after an over, the
        // one who bowled the over before (the likely change back), which the
        // scorer confirms.
        $currentBowler = null;
        if ($lastBall) {
            $currentBowler = (int) $lastBall->bowler_id;
            if ($overEnded) {
                $previous = $overs[intdiv($legal, 6) - 1]['bowler_id'] ?? null;
                $currentBowler = $previous !== null && $previous !== (int) $lastBall->bowler_id ? $previous : null;
            }
        }

        $complete = $legal >= $maxBalls || $wickets >= $wicketLimit;

        $lastWicket = null;
        if ($fow !== []) {
            $w = end($fow);
            $lastWicket = [
                'name' => $w['name'],
                'runs' => $batters[$w['batter_id']]['runs'] ?? 0,
                'balls' => $batters[$w['batter_id']]['balls'] ?? 0,
                'how_out' => $batters[$w['batter_id']]['how_out'] ?? null,
                'score' => $w['score'],
                'wicket' => $w['wicket'],
            ];
        }

        $overList = array_values($overs);

        return [
            'number' => $number,
            'batting_team_id' => $battingTeamId,
            'bowling_team_id' => $bowlingTeamId,
            'runs' => $runs,
            'wickets' => $wickets,
            'wicket_limit' => $wicketLimit,
            'legal_balls' => $legal,
            'balls_bowled' => count($balls),
            'overs' => $this->overs($legal),
            'completed_overs' => intdiv($legal, 6),
            'run_rate' => $legal > 0 ? round($runs / ($legal / 6), 2) : 0.0,
            'extras' => $extras + ['total' => array_sum($extras)],
            'batters' => array_values($batters),
            'bowlers' => array_values($bowlers),
            'fall_of_wickets' => $fow,
            'overs_summary' => $overList,
            'this_over' => $overList === [] ? [] : (end($overList)['balls'] ?? []),
            'partnership' => $partnership,
            'last_wicket' => $lastWicket,
            'last_ball_id' => $lastBall?->id,
            'striker_id' => $striker,
            'non_striker_id' => $nonStriker,
            'bowler_id' => $currentBowler,
            'out_batter_ids' => $outIds,
            'over_complete' => $overEnded,
            'needs_new_batter' => ! $complete && $lastBall !== null && ($striker === null || $nonStriker === null),
            'complete' => $complete,
        ];
    }

    /** "3.4" from 22 legal balls. */
    public function overs(int $legalBalls): string
    {
        return intdiv($legalBalls, 6) . '.' . ($legalBalls % 6);
    }

    /** One delivery as the over strip shows it. */
    public function label(Ball $ball): string
    {
        $suffix = match ($ball->extra_type) {
            'wide' => 'wd',
            'no_ball' => 'nb',
            'bye' => 'b',
            'leg_bye' => 'lb',
            default => '',
        };

        if ($ball->is_wicket) {
            return 'W' . $suffix;
        }

        return match ($ball->extra_type) {
            'wide', 'no_ball' => ($ball->runs + $ball->extra_runs) . $suffix,
            'bye', 'leg_bye' => $ball->extra_runs . $suffix,
            default => (string) $ball->runs,
        };
    }

    private function howOut(Ball $ball, callable $name): string
    {
        $bowler = $name($ball->bowler_id);
        $fielder = $name($ball->fielder_id);

        return match ($ball->dismissal_type) {
            'bowled' => "b {$bowler}",
            'lbw' => "lbw b {$bowler}",
            'caught' => $fielder && (int) $ball->fielder_id === (int) $ball->bowler_id
                ? "c & b {$bowler}"
                : 'c ' . ($fielder ?? '?') . " b {$bowler}",
            'stumped' => 'st ' . ($fielder ?? '?') . " b {$bowler}",
            'hit_wicket' => "hit wicket b {$bowler}",
            'run_out' => 'run out' . ($fielder ? " ({$fielder})" : ''),
            default => $ball->dismissal_type ? str_replace('_', ' ', $ball->dismissal_type) : 'out',
        };
    }

    /**
     * All out at (players - 1) wickets: a named line-up decides the side's size
     * (8-a-side, Playing XII), otherwise the standard eleven.
     *
     * A line-up shorter than MIN_LINEUP is treated as half-filled, not as a
     * 3-a-side team — trusting it would end the innings after a wicket or two
     * and lock the scorer out mid-match.
     */
    private function wicketLimit(Matches $match, int $teamId): int
    {
        $lineup = $match->relationLoaded('lineups')
            ? $match->lineups->where('actual_team_id', $teamId)->count()
            : $match->lineups()->where('actual_team_id', $teamId)->count();

        return $lineup >= self::MIN_LINEUP ? $lineup - 1 : 10;
    }

    private function inningsFromTeam(?int $teamId, int $first, int $second): ?int
    {
        return match (true) {
            $teamId === null => null,
            $teamId === $first => 1,
            $teamId === $second => 2,
            default => null,
        };
    }

    private function teamLine(array $innings): array
    {
        return [
            'runs' => $innings['runs'],
            'wickets' => $innings['wickets'],
            'overs' => $innings['overs'],
            'extras' => $innings['extras']['total'],
            'batted' => $innings['balls_bowled'] > 0,
        ];
    }

    private function result(array $innings, int $target): array
    {
        $first = $innings[1];
        $second = $innings[2];

        if ($second['runs'] >= $target) {
            $margin = max(0, $second['wicket_limit'] - $second['wickets']);

            return ['winner_team_id' => $second['batting_team_id'], 'type' => 'wickets', 'margin' => $margin];
        }

        if ($second['runs'] === $target - 1) {
            return ['winner_team_id' => null, 'type' => 'tie', 'margin' => 0];
        }

        return ['winner_team_id' => $first['batting_team_id'], 'type' => 'runs', 'margin' => $target - 1 - $second['runs']];
    }

    private function topBatters(array $innings): array
    {
        return collect($innings[1]['batters'])->concat($innings[2]['batters'])
            ->filter(fn ($b) => $b['balls'] > 0 || $b['runs'] > 0)
            ->sortBy([['runs', 'desc'], ['balls', 'asc']])
            ->take(3)->values()->all();
    }

    private function topBowlers(array $innings): array
    {
        return collect($innings[1]['bowlers'])->concat($innings[2]['bowlers'])
            ->sortBy([['wickets', 'desc'], ['runs', 'asc'], ['legal_balls', 'desc']])
            ->take(3)->values()->all();
    }

    /**
     * Display names for every squad row the balls mention, plus both squads.
     *
     * @return array<int, array{name: string, team_id: int|null}>
     */
    public function names(Matches $match, Collection $balls): array
    {
        $names = [];
        foreach ([$match->teamA, $match->teamB] as $team) {
            foreach ($team?->players ?? [] as $row) {
                $names[$row->id] = ['name' => $row->player?->name ?? 'Unknown', 'team_id' => (int) $row->actual_team_id];
            }
        }

        $missing = $balls->flatMap(fn ($b) => [$b->batsman_id, $b->non_striker_id, $b->bowler_id, $b->fielder_id, $b->out_batsman_id])
            ->filter()->unique()->reject(fn ($id) => isset($names[$id]));

        if ($missing->isNotEmpty()) {
            foreach (ActualTeamUser::with('player')->whereIn('id', $missing)->get() as $row) {
                $names[$row->id] = ['name' => $row->player?->name ?? 'Unknown', 'team_id' => (int) $row->actual_team_id];
            }
        }

        return $names;
    }
}

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

    /** Dismissals possible off each kind of delivery (Laws 21, 22, 23; free hit = run out only). */
    public const DISMISSALS_ON = [
        'legal' => ['bowled', 'caught', 'lbw', 'run_out', 'stumped', 'hit_wicket'],
        'wide' => ['run_out', 'stumped', 'hit_wicket'],
        'no_ball' => ['run_out'],
        'bye' => ['run_out'],
        'leg_bye' => ['run_out'],
        'free_hit' => ['run_out'],
    ];

    /** Smallest named line-up taken as the side's real size (see wicketLimit()). */
    public const MIN_LINEUP = 5;

    /** Most innings one side may have (a four-day style match is two). */
    public const MAX_INNINGS_PER_TEAM = 4;

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
        // The sides bat in turn: 1st, 3rd… innings are the side batting first.
        $teamFor = fn (int $innings) => $innings % 2 === 1 ? $firstTeamId : $secondTeamId;
        $maxBalls = max(1, (int) ($match->overs ?: 20)) * 6;
        $total = $this->totalInnings($match);
        $powerplays = $this->powerplays($match);

        // Old rows may lack `innings`; place them by the batsman's team, as the
        // pre-innings-column code did.
        $byInnings = array_fill_keys(range(1, $total), []);
        foreach ($balls as $ball) {
            $innings = $ball->innings ?: $this->inningsFromTeam($names[$ball->batsman_id]['team_id'] ?? null, $firstTeamId, $secondTeamId);
            if (isset($byInnings[$innings])) {
                $byInnings[$innings][] = $ball;
            }
        }

        $innings = [];
        foreach (range(1, $total) as $n) {
            $innings[$n] = $this->innings(
                $n,
                $byInnings[$n],
                $teamFor($n),
                $teamFor($n + 1),
                $this->wicketLimit($match, $teamFor($n)),
                $maxBalls,
                $names,
                $powerplays,
            );
        }
        $quota = $this->bowlerQuota($match);
        $current = min($total, max(1, (int) $match->current_innings));

        // An innings is over once it is complete, the scorer has moved on, or a
        // later one has begun.
        foreach ($innings as $n => &$inn) {
            $later = collect($innings)->filter(fn ($i, $k) => $k > $n && $i['balls_bowled'] > 0)->isNotEmpty();
            $inn['complete'] = $inn['complete'] || $later || $current > $n;
        }
        unset($inn);

        // The side batting last chases: everything the other side made, less what
        // it made itself in its earlier innings. 1 innings each = 1st innings + 1.
        $last = $innings[$total];
        $chaserId = $last['batting_team_id'];
        $runsOf = fn (int $teamId, ?int $before = null) => collect($innings)
            ->filter(fn ($i, $k) => $i['batting_team_id'] === $teamId && ($before === null || $k < $before))->sum('runs');
        $lastReached = $innings[$total - 1]['balls_bowled'] > 0 || $current === $total;
        $target = $lastReached ? $runsOf($teamFor($total + 1)) - $runsOf($chaserId, $total) + 1 : null;

        // Behind with no innings left to bat? Then the side batting last has won
        // by an innings and need not bat at all.
        $inningsWin = $target !== null && $target <= 0 && $innings[$total - 1]['complete'] && $last['balls_bowled'] === 0;
        if ($target !== null && $last['runs'] >= max(1, $target)) {
            $innings[$total]['complete'] = true;
        }

        $live = $innings[$current];

        $chase = null;
        if ($current === $total && $target !== null && $target > 0) {
            $ballsLeft = max(0, $maxBalls - $live['legal_balls']);
            $need = max(0, $target - $live['runs']);
            $chase = [
                'target' => $target,
                'runs_needed' => $need,
                'balls_left' => $ballsLeft,
                'required_run_rate' => $ballsLeft > 0 ? round($need / ($ballsLeft / 6), 2) : null,
            ];
        }

        $result = match (true) {
            $inningsWin => ['winner_team_id' => $chaserId, 'type' => 'innings', 'margin' => 1 - $target],
            $innings[$total]['complete'] && $target !== null => $this->result($innings, $total, $target),
            default => null,
        };

        // Ended by the scorer (shortened, abandoned, called off): their outcome
        // stands, and the innings in play are closed.
        if ($match->scoring_ended_at) {
            $result ??= [
                'winner_team_id' => $match->winner_team_id ? (int) $match->winner_team_id : null,
                'type' => 'manual',
                'margin' => 0,
                'text' => (string) ($match->end_reason ?: 'Match ended'),
            ];
            foreach ($innings as &$inn) {
                $inn['complete'] = true;
            }
            unset($inn);
        }

        // Once decided there is nothing left to chase.
        if ($result !== null) {
            $chase = null;
        }

        // Between innings: the one just finished is done, the next has no ball yet.
        $break = $result === null && (
            ($current > 1 && $live['balls_bowled'] === 0)
            || ($live['complete'] && $current < $total)
        );

        return [
            'match_id' => (int) $match->id,
            'score_version' => (int) ($match->score_version ?? 0),
            'overs_limit' => intdiv($maxBalls, 6),
            'bowler_quota' => $quota,
            'innings_per_team' => intdiv($total, 2),
            'total_innings' => $total,
            'players_per_team' => $match->players_per_team ? (int) $match->players_per_team : null,
            'powerplays' => $powerplays,
            'in_powerplay' => $this->inPowerplay($powerplays, intdiv($live['legal_balls'], 6) + 1) && ! $live['complete'],
            'batting_first_team_id' => $firstTeamId,
            'current_innings' => $current,
            'innings_break' => $break,
            'innings' => $innings,
            'teams' => [
                $firstTeamId => $this->teamLine($innings, $firstTeamId),
                $secondTeamId => $this->teamLine($innings, $secondTeamId),
            ],
            'target' => $target !== null && $target > 0 ? $target : null,
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

    /** Innings in the match: two sides × innings each (1 = limited overs). */
    public function totalInnings(Matches $match): int
    {
        return 2 * min(self::MAX_INNINGS_PER_TEAM, max(1, (int) ($match->innings_per_team ?: 1)));
    }

    /**
     * Powerplay over ranges, cleaned: within the innings, in order, no overlap.
     *
     * @return array<int, array{from:int, to:int}>
     */
    public function powerplays(Matches $match): array
    {
        $overs = max(1, (int) ($match->overs ?: 20));
        $out = [];
        foreach ((array) ($match->powerplays ?? []) as $p) {
            $from = max(1, (int) ($p['from'] ?? 0));
            $to = min($overs, (int) ($p['to'] ?? 0));
            if ($to >= $from) {
                $out[] = ['from' => $from, 'to' => $to];
            }
        }
        usort($out, fn ($a, $b) => $a['from'] <=> $b['from']);

        return array_values(array_filter($out, function ($p) use (&$end) {
            $ok = $p['from'] > ($end ?? 0);
            $end = $ok ? $p['to'] : $end;

            return $ok;
        }));
    }

    private function inPowerplay(array $powerplays, int $over): bool
    {
        foreach ($powerplays as $p) {
            if ($over >= $p['from'] && $over <= $p['to']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, Ball>  $balls
     */
    private function innings(int $number, array $balls, int $battingTeamId, int $bowlingTeamId, int $wicketLimit, int $maxBalls, array $names, array $powerplays = []): array
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
        $powerplay = ['runs' => 0, 'wickets' => 0, 'legal_balls' => 0];

        // Who is where, replayed delivery by delivery.
        $striker = null;
        $nonStriker = null;
        $lastBall = null;
        $overEnded = false;
        // The delivery after a no-ball is a free hit, and stays one through
        // any wides or no-balls until a legal ball is bowled.
        $freeHit = false;

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
            // Whoever bowled the last ball of the over (a mid-over change for
            // injury hands the over on) is the one barred from the next.
            $overs[$overNumber]['bowler_id'] = $bowlerId;
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

            if ($type === 'no_ball') {
                $freeHit = true;
            } elseif ($isLegal) {
                $freeHit = false;
            }

            if ($this->inPowerplay($powerplays, $overNumber)) {
                $powerplay['runs'] += $bat + $extra;
                $powerplay['wickets'] += $ball->is_wicket ? 1 : 0;
                $powerplay['legal_balls'] += $isLegal ? 1 : 0;
            }

            $lastBall = $ball;
        }

        foreach ($bowlers as &$b) {
            $b['overs_count'] = count($b['overs_bowled']);
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

        // Bowler for the next ball: the same one mid-over. After an over the
        // scorer picks the next bowler, so nothing is guessed for them.
        $currentBowler = null;
        if ($lastBall && ! $overEnded) {
            $currentBowler = (int) $lastBall->bowler_id;
        }

        $complete = $legal >= $maxBalls || $wickets >= $wicketLimit;

        $lastWicket = null;
        if ($fow !== []) {
            $w = end($fow);
            $lastWicket = [
                'id' => (int) $w['batter_id'],
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
            'powerplay' => $powerplays === [] ? null : $powerplay + ['overs' => $this->overs($powerplay['legal_balls'])],
            'last_wicket' => $lastWicket,
            'last_ball_id' => $lastBall?->id,
            'striker_id' => $striker,
            'non_striker_id' => $nonStriker,
            'bowler_id' => $currentBowler,
            'out_batter_ids' => $outIds,
            'over_complete' => $overEnded,
            'free_hit' => $freeHit && ! $complete,
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
        // The scorer's setting wins: an 8-a-side match is all out at 7.
        if ((int) $match->players_per_team >= 2) {
            return (int) $match->players_per_team - 1;
        }

        $lineup = $match->relationLoaded('lineups')
            ? $match->lineups->where('actual_team_id', $teamId)->count()
            : $match->lineups()->where('actual_team_id', $teamId)->count();

        return $lineup >= self::MIN_LINEUP ? $lineup - 1 : 10;
    }

    /**
     * Most overs one bowler may bowl: the match's own setting, else a fifth
     * of the innings rounded up (T20 = 4, 10 overs = 2, 6 overs = 2).
     */
    public function bowlerQuota(Matches $match): int
    {
        $overs = max(1, (int) ($match->overs ?: 20));
        $set = (int) ($match->bowler_overs_limit ?? 0);

        return $set > 0 ? min($set, $overs) : (int) ceil($overs / 5);
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

    /** A side's line: its innings added up (one innings each = that innings). */
    private function teamLine(array $innings, int $teamId): array
    {
        $own = collect($innings)->where('batting_team_id', $teamId)->filter(fn ($i) => $i['balls_bowled'] > 0);
        $latest = $own->last();

        return [
            'runs' => $own->sum('runs'),
            'wickets' => $latest['wickets'] ?? 0,
            'overs' => $latest['overs'] ?? '0.0',
            'extras' => $own->sum(fn ($i) => $i['extras']['total']),
            'batted' => $own->isNotEmpty(),
            // Each innings in turn, e.g. "245 & 180/6".
            'innings' => $own->map(fn ($i) => ['number' => $i['number'], 'runs' => $i['runs'], 'wickets' => $i['wickets'], 'overs' => $i['overs'], 'complete' => $i['complete']])->values()->all(),
        ];
    }

    private function result(array $innings, int $total, int $target): array
    {
        $last = $innings[$total];
        $other = $innings[$total - 1]['batting_team_id'];

        if ($last['runs'] >= $target) {
            $margin = max(0, $last['wicket_limit'] - $last['wickets']);

            return ['winner_team_id' => $last['batting_team_id'], 'type' => 'wickets', 'margin' => $margin];
        }

        if ($last['runs'] === $target - 1) {
            return ['winner_team_id' => null, 'type' => 'tie', 'margin' => 0];
        }

        return ['winner_team_id' => $other, 'type' => 'runs', 'margin' => $target - 1 - $last['runs']];
    }

    private function topBatters(array $innings): array
    {
        return $this->merged($innings, 'batters', ['runs', 'balls', 'fours', 'sixes'])
            ->filter(fn ($b) => $b['balls'] > 0 || $b['runs'] > 0)
            ->sortBy([['runs', 'desc'], ['balls', 'asc']])
            ->take(3)->values()->all();
    }

    private function topBowlers(array $innings): array
    {
        return $this->merged($innings, 'bowlers', ['legal_balls', 'runs', 'wickets', 'maidens', 'wides', 'no_balls'])
            ->sortBy([['wickets', 'desc'], ['runs', 'asc'], ['legal_balls', 'desc']])
            ->take(3)->values()->all();
    }

    /** One row per player across every innings, figures added up. */
    private function merged(array $innings, string $key, array $sum): Collection
    {
        $rows = [];
        foreach ($innings as $i) {
            foreach ($i[$key] as $r) {
                if (! isset($rows[$r['id']])) {
                    $rows[$r['id']] = $r;

                    continue;
                }
                foreach ($sum as $f) {
                    $rows[$r['id']][$f] += $r[$f];
                }
                $rows[$r['id']]['out'] = $r['out'] ?? null;
            }
        }

        return collect($rows)->map(function ($r) use ($key) {
            if ($key === 'bowlers') {
                $r['overs'] = $this->overs($r['legal_balls']);
                $r['economy'] = $r['legal_balls'] > 0 ? round($r['runs'] / ($r['legal_balls'] / 6), 2) : 0.0;
            } else {
                $r['strike_rate'] = $r['balls'] > 0 ? round($r['runs'] / $r['balls'] * 100, 2) : 0.0;
            }

            return $r;
        });
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

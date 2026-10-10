<?php

declare(strict_types=1);

namespace Tests\Unit\Scoring;

use App\Models\Ball;
use App\Models\Matches;
use App\Models\MatchLineup;
use App\Services\Scoring\MatchStateService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The scoreboard arithmetic, against deliveries whose answers are worked out
 * by hand. No database: build() is pure.
 *
 * Squad rows: team 10 = batters 1..11, team 20 = bowlers 21..31.
 */
class MatchStateServiceTest extends TestCase
{
    private int $id = 0;

    private function match(array $attrs = [], int $lineupA = 0): Matches
    {
        $m = new Matches();
        $m->forceFill(array_merge([
            'id' => 1, 'team_a_id' => 10, 'team_b_id' => 20, 'overs' => 2,
            'current_innings' => 1, 'batting_first_team_id' => null, 'score_version' => 0,
        ], $attrs));

        $lineups = collect();
        for ($i = 0; $i < $lineupA; $i++) {
            $lineups->push((new MatchLineup())->forceFill(['actual_team_id' => 10]));
        }
        $m->setRelation('lineups', $lineups);

        return $m;
    }

    private function names(): array
    {
        $names = [];
        foreach (range(1, 11) as $i) {
            $names[$i] = ['name' => "A{$i}", 'team_id' => 10];
        }
        foreach (range(21, 31) as $i) {
            $names[$i] = ['name' => "B{$i}", 'team_id' => 20];
        }

        return $names;
    }

    /** One delivery. */
    private function ball(int $striker, int $nonStriker, int $bowler, int $runs = 0, ?string $extra = null, int $extraRuns = 0, int $innings = 1, array $wicket = []): Ball
    {
        $b = new Ball();
        $b->forceFill(array_merge([
            'id' => ++$this->id, 'match_id' => 1, 'innings' => $innings,
            'batsman_id' => $striker, 'non_striker_id' => $nonStriker, 'bowler_id' => $bowler,
            'runs' => $runs, 'extra_type' => $extra, 'extra_runs' => $extraRuns,
            'is_wicket' => false, 'dismissal_type' => null, 'out_batsman_id' => null, 'fielder_id' => null,
        ], $wicket ? ['is_wicket' => true] + $wicket : []));

        return $b;
    }

    private function state(Matches $m, array $balls): array
    {
        return (new MatchStateService())->build($m, new Collection($balls), $this->names());
    }

    #[Test]
    public function totals_overs_and_run_rate_count_legal_balls(): void
    {
        $balls = [
            $this->ball(1, 2, 21, 1),          // 0.1  1   (rotate: 2 on strike)
            $this->ball(2, 1, 21, 4),          // 0.2  4
            $this->ball(2, 1, 21, 0, 'wide', 1), // wide, not a ball
            $this->ball(2, 1, 21, 0),          // 0.3
            $this->ball(2, 1, 21, 6),          // 0.4
        ];
        $i = $this->state($this->match(), $balls)['innings'][1];

        $this->assertSame(12, $i['runs']);
        $this->assertSame(4, $i['legal_balls']);
        $this->assertSame('0.4', $i['overs']);
        $this->assertSame(18.0, $i['run_rate']);          // 12 runs / (4/6) overs
        $this->assertSame(1, $i['extras']['wides']);
        $this->assertSame(['1', '4', '1wd', '0', '6'], $i['this_over']);
    }

    #[Test]
    public function a_wide_first_ball_does_not_push_the_numbering_on(): void
    {
        $s = $this->state($this->match(), [$this->ball(1, 2, 21, 0, 'wide', 1)]);

        $this->assertSame(['over' => 1, 'ball_in_over' => 1], $s['next_ball']);
    }

    #[Test]
    public function batter_and_bowler_figures(): void
    {
        $balls = [
            $this->ball(1, 2, 21, 4),
            $this->ball(1, 2, 21, 0, 'no_ball', 1),   // faced, not legal, bowler charged 1
            $this->ball(1, 2, 21, 0, 'bye', 2),       // faced, bowler NOT charged
            $this->ball(1, 2, 21, 0, 'leg_bye', 1),   // faced, odd → rotate
            $this->ball(2, 1, 21, 0, 'wide', 3),      // wide + 2 run → rotate; bowler charged 3; not faced
            $this->ball(1, 2, 21, 6),
        ];
        $i = $this->state($this->match(), $balls)['innings'][1];

        $a1 = collect($i['batters'])->firstWhere('id', 1);
        $this->assertSame(10, $a1['runs']);
        $this->assertSame(5, $a1['balls']);           // 4, nb, bye, lb, 6
        $this->assertSame(1, $a1['fours']);
        $this->assertSame(1, $a1['sixes']);
        $this->assertSame(0, collect($i['batters'])->firstWhere('id', 2)['balls']);

        $bowler = $i['bowlers'][0];
        $this->assertSame(14, $bowler['runs']);       // 4 + 1nb + 3wd + 6, byes excluded
        $this->assertSame(4, $bowler['legal_balls']);
        $this->assertSame('0.4', $bowler['overs']);
        $this->assertSame(21.0, $bowler['economy']);  // 14 / (4/6)
        $this->assertSame(17, $i['runs']);            // everything
        $this->assertSame(['wides' => 3, 'no_balls' => 1, 'byes' => 2, 'leg_byes' => 1, 'total' => 7], $i['extras']);
    }

    #[Test]
    public function strike_rotates_on_odd_runs_and_at_the_end_of_the_over(): void
    {
        $balls = [];
        $balls[] = $this->ball(1, 2, 21, 1);   // 2 on strike
        $s = $this->state($this->match(), $balls)['innings'][1];
        $this->assertSame([2, 1], [$s['striker_id'], $s['non_striker_id']]);

        foreach ([0, 0, 0, 0] as $r) {
            $balls[] = $this->ball(2, 1, 21, $r);
        }
        $balls[] = $this->ball(2, 1, 21, 1);   // 6th ball, single: swap, then over swap → 2 keeps strike
        $s = $this->state($this->match(), $balls)['innings'][1];
        $this->assertSame([2, 1], [$s['striker_id'], $s['non_striker_id']]);
        $this->assertTrue($s['over_complete']);
        $this->assertNull($s['bowler_id'], 'Nobody bowled the over before, so no suggestion');
    }

    #[Test]
    public function a_maiden_needs_six_legal_balls_and_nothing_charged(): void
    {
        $balls = [];
        foreach (range(1, 6) as $_) {
            $balls[] = $this->ball(1, 2, 21, 0);
        }
        $balls[2] = $this->ball(1, 2, 21, 0, 'leg_bye', 2);   // leg-byes do not spoil a maiden
        $balls[2]->id = 3;
        $this->assertSame(1, $this->state($this->match(), $balls)['innings'][1]['bowlers'][0]['maidens']);

        $withWide = $balls;
        $withWide[] = $this->ball(2, 1, 22, 0);
        array_splice($withWide, 3, 0, [$this->ball(1, 2, 21, 0, 'wide', 1)]);
        $this->assertSame(0, $this->state($this->match(), $withWide)['innings'][1]['bowlers'][0]['maidens']);
    }

    #[Test]
    public function run_outs_are_not_the_bowlers_and_can_dismiss_the_non_striker(): void
    {
        $balls = [
            $this->ball(1, 2, 21, 4),
            // Striker hits 1, non-striker (2) is run out going for the second.
            $this->ball(1, 2, 21, 1, null, 0, 1, ['dismissal_type' => 'run_out', 'out_batsman_id' => 2, 'fielder_id' => 25]),
        ];
        $i = $this->state($this->match(), $balls)['innings'][1];

        $this->assertSame(1, $i['wickets']);
        $this->assertSame(0, $i['bowlers'][0]['wickets']);
        $this->assertTrue(collect($i['batters'])->firstWhere('id', 2)['out']);
        $this->assertFalse(collect($i['batters'])->firstWhere('id', 1)['out']);
        $this->assertSame(5, collect($i['batters'])->firstWhere('id', 1)['runs']);
        $this->assertSame('run out (B25)', collect($i['batters'])->firstWhere('id', 2)['how_out']);
        // They crossed for the single: 1 is at the non-striker's end, the striker's end is empty.
        $this->assertNull($i['striker_id']);
        $this->assertSame(1, $i['non_striker_id']);
        $this->assertTrue($i['needs_new_batter']);
    }

    #[Test]
    public function caught_credits_the_bowler_and_reads_properly(): void
    {
        $balls = [$this->ball(1, 2, 21, 0, null, 0, 1, ['dismissal_type' => 'caught', 'fielder_id' => 26])];
        $i = $this->state($this->match(), $balls)['innings'][1];

        $this->assertSame(1, $i['bowlers'][0]['wickets']);
        $this->assertSame('c B26 b B21', $i['batters'][0]['how_out']);
        $this->assertSame([['wicket' => 1, 'score' => 0, 'overs' => '0.1', 'batter_id' => 1, 'name' => 'A1']], $i['fall_of_wickets']);
        $this->assertSame('W', $i['this_over'][0]);
    }

    #[Test]
    public function the_partnership_restarts_at_the_last_wicket_not_the_first(): void
    {
        $balls = [
            $this->ball(1, 2, 21, 0, null, 0, 1, ['dismissal_type' => 'bowled']),
            $this->ball(3, 2, 21, 2),
            $this->ball(3, 2, 21, 0, null, 0, 1, ['dismissal_type' => 'bowled']),
            $this->ball(4, 2, 21, 4),
            $this->ball(4, 2, 21, 2),
        ];
        $p = $this->state($this->match(), $balls)['innings'][1]['partnership'];

        $this->assertSame(['runs' => 6, 'balls' => 2], $p);
    }

    #[Test]
    public function the_innings_ends_at_the_overs_or_when_all_out(): void
    {
        $balls = [];
        foreach (range(1, 12) as $_) {
            $balls[] = $this->ball(1, 2, 21, 0);
        }
        $this->assertTrue($this->state($this->match(), $balls)['innings'][1]['complete']);

        // An 8-a-side line-up is all out at 7.
        $balls = [];
        foreach (range(1, 7) as $n) {
            $balls[] = $this->ball($n, 11, 21, 0, null, 0, 1, ['dismissal_type' => 'bowled']);
        }
        $this->assertTrue($this->state($this->match([], 8), $balls)['innings'][1]['complete']);
        $this->assertFalse($this->state($this->match(), $balls)['innings'][1]['complete'], 'Eleven-a-side goes on');

        // A half-filled line-up (3 names) is not a 3-a-side team: still 10 wickets.
        $this->assertSame(10, $this->state($this->match([], 3), [])['innings'][1]['wicket_limit']);
    }

    #[Test]
    public function a_chase_sets_the_target_and_the_required_rate(): void
    {
        $balls = [$this->ball(1, 2, 21, 6), $this->ball(1, 2, 21, 4)];       // 10 in innings 1
        $balls[] = $this->ball(21, 22, 1, 2, null, 0, 2);                     // innings 2: 2 off 1
        $s = $this->state($this->match(['current_innings' => 2]), $balls);

        $this->assertSame(11, $s['target']);
        $this->assertSame(['target' => 11, 'runs_needed' => 9, 'balls_left' => 11, 'required_run_rate' => 4.91], $s['chase']);
        $this->assertNull($s['result']);
    }

    #[Test]
    public function results_by_wickets_by_runs_and_tie(): void
    {
        $first = [$this->ball(1, 2, 21, 6), $this->ball(1, 2, 21, 4)];       // 10, target 11

        $won = [...$first, $this->ball(21, 22, 1, 6, null, 0, 2), $this->ball(21, 22, 1, 6, null, 0, 2)];
        $s = $this->state($this->match(['current_innings' => 2]), $won);
        $this->assertSame(['winner_team_id' => 20, 'type' => 'wickets', 'margin' => 10], $s['result']);
        $this->assertTrue($s['innings'][2]['complete']);

        // Defended: innings 2 bats its 12 balls and makes 3.
        $lost = $first;
        foreach (range(1, 11) as $_) {
            $lost[] = $this->ball(21, 22, 1, 0, null, 0, 2);
        }
        $lost[] = $this->ball(21, 22, 1, 3, null, 0, 2);
        $s = $this->state($this->match(['current_innings' => 2]), $lost);
        $this->assertSame(['winner_team_id' => 10, 'type' => 'runs', 'margin' => 7], $s['result']);

        // Tie: innings 2 finishes level on 10 (a four and a six).
        $tie = $first;
        foreach (range(1, 10) as $_) {
            $tie[] = $this->ball(21, 22, 1, 0, null, 0, 2);
        }
        $tie[] = $this->ball(21, 22, 1, 4, null, 0, 2);
        $tie[] = $this->ball(21, 22, 1, 6, null, 0, 2);
        $s = $this->state($this->match(['current_innings' => 2]), $tie);
        $this->assertSame(12, $s['innings'][2]['legal_balls']);
        $this->assertSame(['winner_team_id' => null, 'type' => 'tie', 'margin' => 0], $s['result']);

        // Not finished yet: no result while the chase is alive.
        $s = $this->state($this->match(['current_innings' => 2]), array_slice($tie, 0, 5));
        $this->assertNull($s['result']);
    }

    #[Test]
    public function the_toss_decides_who_bats_first(): void
    {
        // Team B (20) won the toss and batted: innings 1 is theirs.
        $balls = [$this->ball(21, 22, 1, 4)];
        $s = $this->state($this->match(['batting_first_team_id' => 20]), $balls);

        $this->assertSame(20, $s['innings'][1]['batting_team_id']);
        $this->assertSame(4, $s['teams'][20]['runs']);
        $this->assertFalse($s['teams'][10]['batted']);
    }

    #[Test]
    public function legacy_balls_without_an_innings_are_placed_by_the_batters_team(): void
    {
        $b1 = $this->ball(1, 2, 21, 4);
        $b1->innings = null;
        $b2 = $this->ball(21, 22, 1, 2, null, 0, 2);
        $b2->innings = null;
        $s = $this->state($this->match(), [$b1, $b2]);

        $this->assertSame(4, $s['innings'][1]['runs']);
        $this->assertSame(2, $s['innings'][2]['runs']);
    }
}

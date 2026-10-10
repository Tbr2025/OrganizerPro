<?php

namespace Tests\Unit;

use App\Models\Ball;
use App\Models\Matches;
use App\Services\Scoring\MatchStateService;
use App\Traits\CalculatesMatchBallStats;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The shared ball aggregation trait used by both the Result and Summary admin
 * controllers. It now reads MatchStateService (whose arithmetic is covered in
 * Tests\Unit\Scoring\MatchStateServiceTest); this checks the trait's own job:
 * mapping innings onto team A / team B whoever batted first. No database.
 */
class CalculatesMatchBallStatsTest extends TestCase
{
    private function stats(array $balls, ?int $battingFirst = null): array
    {
        $match = (new Matches())->forceFill([
            'id' => 1, 'team_a_id' => 10, 'team_b_id' => 20, 'overs' => 20,
            'current_innings' => 1, 'batting_first_team_id' => $battingFirst,
        ]);
        $match->setRelation('lineups', collect());

        // The real build(), fed in-memory balls instead of a query.
        $this->app->instance(MatchStateService::class, new class ($balls) extends MatchStateService {
            public function __construct(private array $balls) {}

            public function snapshot(Matches $match): array
            {
                $names = [];
                foreach ([1, 2] as $i) {
                    $names[$i] = ['name' => "A{$i}", 'team_id' => 10];
                    $names[20 + $i] = ['name' => "B{$i}", 'team_id' => 20];
                }

                return $this->build($match, new Collection($this->balls), $names);
            }
        });

        return (new class () {
            use CalculatesMatchBallStats;

            public function run(Matches $m): array
            {
                return $this->calculateBallStats($m);
            }
        })->run($match);
    }

    private function ball(int $innings, int $runs, int $extraRuns = 0, bool $wicket = false, ?string $extraType = null): Ball
    {
        static $id = 0;
        [$bat, $non, $bowl] = $innings === 1 ? [1, 2, 21] : [21, 22, 1];

        return (new Ball())->forceFill([
            'id' => ++$id, 'innings' => $innings, 'batsman_id' => $bat, 'non_striker_id' => $non, 'bowler_id' => $bowl,
            'runs' => $runs, 'extra_runs' => $extraRuns, 'extra_type' => $extraType,
            'is_wicket' => $wicket, 'dismissal_type' => $wicket ? 'bowled' : null,
        ]);
    }

    #[Test]
    public function no_balls_means_no_ball_data(): void
    {
        $s = $this->stats([]);

        $this->assertFalse($s['hasBallData']);
        $this->assertSame(['runs' => 0, 'wickets' => 0, 'overs' => 0.0, 'extras' => 0], $s['teamA']);
        $this->assertNull($s['result']);
    }

    #[Test]
    public function runs_extras_wickets_and_legal_overs(): void
    {
        $s = $this->stats([
            $this->ball(1, 4),
            $this->ball(1, 0, 1, false, 'wide'),   // extra, illegal delivery
            $this->ball(1, 6),
            $this->ball(1, 0, 0, true),            // wicket, legal
        ]);

        $this->assertSame(['runs' => 11, 'wickets' => 1, 'overs' => 0.3, 'extras' => 1], $s['teamA']);
        $this->assertTrue($s['firstInningsComplete']);
        $this->assertFalse($s['secondInningsStarted']);
    }

    #[Test]
    public function team_b_batting_first_lands_in_team_b_and_the_chaser_wins_by_wickets(): void
    {
        // B bats first (innings 1) for 6; A chases with 8 in innings 2.
        $balls = [];
        foreach ([[1, 0], [1, 6]] as [$_, $r]) {
            $balls[] = (new Ball())->forceFill(['id' => count($balls) + 100, 'innings' => 1, 'batsman_id' => 21, 'non_striker_id' => 22, 'bowler_id' => 1, 'runs' => $r, 'extra_runs' => 0, 'is_wicket' => false]);
        }
        $balls[] = (new Ball())->forceFill(['id' => 200, 'innings' => 2, 'batsman_id' => 1, 'non_striker_id' => 2, 'bowler_id' => 21, 'runs' => 4, 'extra_runs' => 0, 'is_wicket' => false]);
        $balls[] = (new Ball())->forceFill(['id' => 201, 'innings' => 2, 'batsman_id' => 1, 'non_striker_id' => 2, 'bowler_id' => 21, 'runs' => 4, 'extra_runs' => 0, 'is_wicket' => false]);

        $s = $this->stats($balls, battingFirst: 20);

        $this->assertFalse($s['teamABattingFirst']);
        $this->assertSame(6, $s['teamB']['runs']);
        $this->assertSame(8, $s['teamA']['runs']);
        $this->assertSame(['winner_team_id' => 10, 'type' => 'wickets', 'margin' => 10], $s['result']);
    }
}

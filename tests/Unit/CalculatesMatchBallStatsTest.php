<?php

namespace Tests\Unit;

use App\Traits\CalculatesMatchBallStats;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit-tests the shared ball aggregation trait used by both the Result and
 * Summary admin controllers. Pure logic — no database.
 */
class CalculatesMatchBallStatsTest extends TestCase
{
    /** Wrapper exposing the protected trait method. */
    private function calc()
    {
        return new class () {
            use CalculatesMatchBallStats;

            public function stats($balls): array
            {
                return $this->calculateInningsStats($balls);
            }
        };
    }

    private function ball(int $runs, int $extraRuns = 0, int $wicket = 0, ?string $extraType = null): object
    {
        return (object) [
            'runs' => $runs,
            'extra_runs' => $extraRuns,
            'is_wicket' => $wicket,
            'extra_type' => $extraType,
        ];
    }

    #[Test]
    public function empty_innings_returns_zeroed_stats(): void
    {
        $this->assertSame(
            ['runs' => 0, 'wickets' => 0, 'overs' => 0, 'extras' => 0],
            $this->calc()->stats(collect([]))
        );
    }

    #[Test]
    public function runs_extras_and_wickets_are_summed(): void
    {
        $balls = collect([
            $this->ball(4),
            $this->ball(0, 1, 0, 'wide'),   // extra, illegal delivery
            $this->ball(6),
            $this->ball(0, 0, 1),           // wicket, legal
        ]);

        $stats = $this->calc()->stats($balls);

        $this->assertSame(11, $stats['runs']);   // 4 + 6 + 1 extra
        $this->assertSame(1, $stats['wickets']);
        $this->assertSame(1, $stats['extras']);
    }

    #[Test]
    public function overs_count_only_legal_deliveries_in_cricket_notation(): void
    {
        // 6 legal + 1 wide + 2 more legal = 8 legal balls = 1 over 2 balls = 1.2
        $balls = collect([]);
        for ($i = 0; $i < 6; $i++) {
            $balls->push($this->ball(1));
        }
        $balls->push($this->ball(0, 1, 0, 'wide'));   // illegal, not counted
        $balls->push($this->ball(1));
        $balls->push($this->ball(1));

        $stats = $this->calc()->stats($balls);

        $this->assertSame(1.2, $stats['overs']);
        $this->assertSame(9, $stats['runs']);   // 8 runs off bat + 1 wide
    }
}

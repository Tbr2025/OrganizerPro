<?php

declare(strict_types=1);

namespace Tests\Feature\Tournament;

use App\Models\Matches;
use App\Models\MatchResult;
use App\Models\PointTableEntry;
use App\Models\TournamentGroup;
use App\Services\Tournament\PointTableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * NRR has to agree with the scoring app the organizers check it against (CricHeroes, ICC rules).
 * The live tables disagreed because an all-out side was charged only the overs it lasted, and
 * because summed overs were rounded to one decimal place after every match.
 */
class NetRunRateTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function playMatches(array $innings): array
    {
        $org = $this->makeOrganization('Org ' . uniqid());
        $tournament = $this->makeTournament($org, 'open');
        $group = TournamentGroup::create(['tournament_id' => $tournament->id, 'name' => 'Pool A']);
        $a = $this->makeTeam($org, 'Blasters', $tournament);
        $b = $this->makeTeam($org, 'Sixers', $tournament);
        $group->teams()->attach([$a->id, $b->id]);

        foreach ($innings as [$aScore, $aWkts, $aOvers, $bScore, $bWkts, $bOvers]) {
            $match = Matches::create([
                'tournament_id' => $tournament->id,
                'tournament_group_id' => $group->id,
                'name' => 'Fixture', 'slug' => 'fixture-' . uniqid(),
                'team_a_id' => $a->id, 'team_b_id' => $b->id,
                'status' => 'completed', 'stage' => 'league', 'overs' => 20,
            ]);
            MatchResult::create([
                'match_id' => $match->id,
                'team_a_score' => $aScore, 'team_a_wickets' => $aWkts, 'team_a_overs' => $aOvers,
                'team_b_score' => $bScore, 'team_b_wickets' => $bWkts, 'team_b_overs' => $bOvers,
                'winner_team_id' => $bScore > $aScore ? $b->id : $a->id,
                'result_type' => 'runs',
            ]);
        }

        app(PointTableService::class)->recalculatePointTable($tournament->fresh());

        return [
            PointTableEntry::where('actual_team_id', $a->id)->firstOrFail(),
            PointTableEntry::where('actual_team_id', $b->id)->firstOrFail(),
        ];
    }

    #[Test]
    public function an_all_out_side_is_charged_its_full_quota_of_overs(): void
    {
        // A live result: 92 all out in 14.5, chased in 9.5.
        [$a, $b] = $this->playMatches([[92, 10, 14.5, 95, 2, 9.5]]);

        // 92/20 - 95/(9 + 5/6) = 4.600 - 9.661
        $this->assertEqualsWithDelta(20.0, (float) $a->overs_faced, 0.0001);
        $this->assertEqualsWithDelta(-5.061, (float) $a->net_run_rate, 0.0005);
        $this->assertEqualsWithDelta(5.061, (float) $b->net_run_rate, 0.0005);
    }

    #[Test]
    public function overs_are_summed_exactly_rather_than_to_one_decimal_place(): void
    {
        // 18.2 overs is 18⅓ — at one decimal place each match lost a thirtieth of an over.
        [$a] = $this->playMatches([
            [150, 5, 18.2, 160, 4, 20.0],
            [150, 5, 18.2, 160, 4, 20.0],
            [150, 5, 18.2, 160, 4, 20.0],
        ]);

        $this->assertEqualsWithDelta(55.0, (float) $a->overs_faced, 0.0001);
        $this->assertEqualsWithDelta(450 / 55 - 480 / 60, (float) $a->net_run_rate, 0.0005);
    }
}

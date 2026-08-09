<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\AuctionTeamBudget;
use App\Services\Auction\AuctionPoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Reading every team\'s purse at a fixed cost.
 *
 * teamPurseState() runs five leaf queries per team and pollState() calls it once per team
 * on a two-second poll, so seven teams meant thirty-five queries every two seconds while
 * the auction was also writing bids to the same table. On the live panel poll-state was
 * taking between one and eight seconds.
 *
 * The batched path must produce figures IDENTICAL to the single-team path. These are the
 * numbers a hall is shown and a manager bids against; a silent divergence between the two
 * would be worse than the slowness it replaces.
 */
class AuctionPurseBatchTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function service(): AuctionPoolService
    {
        return app(AuctionPoolService::class);
    }

    private function scenario(int $teamCount = 3, string $orgName = 'Test Org'): array
    {
        // Organizations are uniquely named, so two scenarios in one test need telling apart.
        $org = $this->makeOrganization($orgName);
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 100_000_000,
            'min_squad_size' => 8,
            'min_price_per_player' => 1_000_000,
        ]);

        $teams = [];
        for ($i = 0; $i < $teamCount; $i++) {
            $teams[] = $this->makeTeam($org, "Team {$i}", $tournament);
        }

        return [$org, $tournament, $auction, $teams];
    }

    #[Test]
    public function the_batch_and_single_paths_agree(): void
    {
        [$org, $tournament, $auction, $teams] = $this->scenario();
        $team = $teams[0];

        // A team with all three kinds of history: a budget override, a bought player and a
        // retained one. Anything less would not exercise the three prefetches.
        AuctionTeamBudget::create([
            'auction_id' => $auction->id,
            'actual_team_id' => $team->id,
            'budget' => 42_000_000,
        ]);

        $this->makeAuctionPlayer($auction, [
            'status' => 'sold',
            'sold_to_team_id' => $team->id,
            'final_price' => 9_000_000,
        ]);
        $this->makeAuctionPlayer($auction, [
            'status' => 'sold',
            'sold_to_team_id' => $team->id,
            'final_price' => 3_500_000,
        ]);
        $this->makeAuctionPlayer($auction, [
            'status' => 'waiting',
            'is_retained' => true,
            'team_id' => $team->id,
            'retained_price' => 5_000_000,
        ]);

        $single = $this->service()->teamPurseState($auction, $team->id, 1_000_000.0);
        $batch = $this->service()->teamPurseStates($auction, [$team->id], 1_000_000.0)[$team->id];

        // Key for key: this is the control that makes the refactor safe.
        $this->assertSame($single, $batch);

        // And the figures are actually right, not merely equal to each other.
        $this->assertSame(42_000_000.0, $batch['allocated']);
        $this->assertSame(17_500_000.0, $batch['spent']);
        $this->assertSame(12_500_000.0, $batch['auction_spent']);
        $this->assertSame(5_000_000.0, $batch['retained_spent']);
        $this->assertSame(3, $batch['slots_filled']);
    }

    #[Test]
    public function a_team_with_nothing_still_gets_a_full_state(): void
    {
        [$org, $tournament, $auction, $teams] = $this->scenario();
        $team = $teams[0];

        // Absent from all three prefetches. SUM() over an empty group is NULL, so without a
        // cast at that boundary this team comes back with nulls where every consumer
        // expects a float.
        $single = $this->service()->teamPurseState($auction, $team->id);
        $batch = $this->service()->teamPurseStates($auction, [$team->id])[$team->id];

        $this->assertSame($single, $batch);
        $this->assertSame(0.0, $batch['spent']);
        $this->assertSame(0, $batch['slots_filled']);
        $this->assertSame(100_000_000.0, $batch['allocated'], 'falls back to the auction-wide budget');
    }

    #[Test]
    public function the_cost_does_not_grow_with_the_number_of_teams(): void
    {
        [$org, $tournament, $auction, $teams] = $this->scenario(3);
        $threeIds = array_map(fn ($t) => $t->id, $teams);

        [$org2, $tournament2, $auction2, $teams2] = $this->scenario(8, 'Second Org');
        $eightIds = array_map(fn ($t) => $t->id, $teams2);

        $count = function (callable $run): int {
            $queries = 0;
            DB::listen(function () use (&$queries) { $queries++; });
            $run();
            // Laravel keeps listeners for the test, so the count is read and reset by
            // measuring each call in its own closure.
            return $queries;
        };

        $forThree = $count(fn () => $this->service()->teamPurseStates($auction, $threeIds));
        $forEight = $count(fn () => $this->service()->teamPurseStates($auction2, $eightIds));

        /*
         * The whole point of the change: eight teams must not cost more than three. Before
         * this it was five queries PER TEAM, so this would have been 15 against 40.
         */
        $this->assertSame(
            $forThree,
            $forEight,
            "three teams cost {$forThree} queries and eight cost {$forEight} — the cost is still per-team"
        );

        $this->assertLessThanOrEqual(5, $forEight, 'the batch should be a handful of grouped reads');
    }

    #[Test]
    public function every_team_is_returned_even_when_only_one_has_players(): void
    {
        [$org, $tournament, $auction, $teams] = $this->scenario(3);
        $ids = array_map(fn ($t) => $t->id, $teams);

        $this->makeAuctionPlayer($auction, [
            'status' => 'sold',
            'sold_to_team_id' => $teams[1]->id,
            'final_price' => 4_000_000,
        ]);

        $states = $this->service()->teamPurseStates($auction, $ids);

        // A missing key would be an undefined-index on the panel rather than a zero.
        $this->assertSame($ids, array_keys($states));
        $this->assertSame(4_000_000.0, $states[$teams[1]->id]['spent']);
        $this->assertSame(0.0, $states[$teams[0]->id]['spent']);
    }

    #[Test]
    public function retained_and_sold_are_not_credited_to_the_wrong_team(): void
    {
        [$org, $tournament, $auction, $teams] = $this->scenario(2);
        [$alpha, $bravo] = $teams;

        // Sold rows key on sold_to_team_id, retained rows on team_id. Reading one with the
        // other credits the wrong side, and the totals still look plausible.
        $this->makeAuctionPlayer($auction, [
            'status' => 'sold',
            'sold_to_team_id' => $alpha->id,
            'team_id' => $bravo->id,
            'final_price' => 7_000_000,
        ]);
        $this->makeAuctionPlayer($auction, [
            'status' => 'waiting',
            'is_retained' => true,
            'team_id' => $bravo->id,
            'retained_price' => 2_000_000,
        ]);

        $states = $this->service()->teamPurseStates($auction, [$alpha->id, $bravo->id]);

        $this->assertSame(7_000_000.0, $states[$alpha->id]['auction_spent']);
        $this->assertSame(0.0, $states[$alpha->id]['retained_spent']);
        $this->assertSame(0.0, $states[$bravo->id]['auction_spent']);
        $this->assertSame(2_000_000.0, $states[$bravo->id]['retained_spent']);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\TeamManager;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * The auction summary on a team manager's dashboard.
 *
 * Reported from live: "Crazy11 Kollam Sailors" had twenty players bought at the You Selects IPL
 * Season-2 auction, and the dashboard showed 0 Auctions / 0 Total Budget / 0 Budget Left /
 * 0 Spent beneath them. The auction had been marked completed, and the dashboard built its whole
 * summary from auctions with status scheduled|running|paused — so finishing an auction erased
 * every figure it had produced.
 */
class DashboardAuctionSummaryTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    #[Test]
    public function a_completed_auction_still_reports_what_the_team_spent(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $team = $this->makeTeam($org, 'Kollam Sailors', $tournament);

        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'status' => 'completed',          // the state that used to zero everything
            'max_budget_per_team' => 1000,
        ]);

        // Two players bought before the hammer came down on the whole auction.
        foreach ([300, 200] as $price) {
            $player = $this->makeApprovedPlayer($org, $tournament);
            // `final_price` is the column the purse state sums — see
            // AuctionPoolService::teamPurseStates(). sold_price is not what the money comes from.
            $this->makeAuctionPlayer($auction, [
                'player_id' => $player->id,
                'status' => 'sold',
                'sold_to_team_id' => $team->id,
                'final_price' => $price,
            ]);
        }

        $manager = $this->makeAuctionOperator($org);
        $manager->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Team Manager', 'guard_name' => 'web']));
        $team->users()->attach($manager->id, ['role' => 'Owner']);

        $response = $this->actingAs($manager)->get(route('team-manager.dashboard'));
        $response->assertOk();

        $budgets = $response->viewData('auctionBudgets');
        $teamAuctions = $response->viewData('teamAuctions');
        $upcoming = $response->viewData('upcomingAuctions');

        // The completed auction is summarised…
        $this->assertArrayHasKey($auction->id, $budgets, 'A finished auction vanished from the summary.');
        $this->assertSame(500.0, (float) collect($budgets)->sum('spent'));
        $this->assertSame(1000.0, (float) collect($budgets)->sum('max'));
        $this->assertCount(1, $teamAuctions);

        // …but it is not "upcoming", which is a different question and still answered correctly.
        $this->assertCount(0, $upcoming, 'A completed auction must not be listed as upcoming.');
    }

    #[Test]
    public function a_live_auction_is_summarised_and_listed_as_upcoming(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $team = $this->makeTeam($org, 'Live Team', $tournament);

        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'status' => 'running',
            'max_budget_per_team' => 800,
        ]);

        $manager = $this->makeAuctionOperator($org);
        $manager->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Team Manager', 'guard_name' => 'web']));
        $team->users()->attach($manager->id, ['role' => 'Owner']);

        $response = $this->actingAs($manager)->get(route('team-manager.dashboard'));
        $response->assertOk();

        // Unchanged behaviour for a live auction — it appears in both places.
        $this->assertCount(1, $response->viewData('upcomingAuctions'));
        $this->assertArrayHasKey($auction->id, $response->viewData('auctionBudgets'));
    }
}

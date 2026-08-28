<?php

namespace Tests\Feature\Auction;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * The team edit page shows the auction budget.
 *
 * Retention is set on that page and its whole effect is to spend the team's auction budget,
 * so editing a retention value without the budget in view was a blind edit.
 */
class ActualTeamBudgetPanelTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    #[Test]
    public function the_team_edit_page_shows_the_auction_budget(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 100_000_000,
        ]);
        $team = $this->makeTeam($org, 'Alpha Strikers', $tournament);

        $player = $this->makePlayer($org, [
            'player_mode' => 'retained',
            'actual_team_id' => $team->id,
            'retained_value' => 6_000_000,
        ]);
        app(\App\Services\Auction\AuctionPoolService::class)->syncRetainedPlayers($auction);

        $admin = $this->makeAuctionOperator($org, ['auction.edit', 'auction.view', 'actual-team.edit']);
        /*
         * The role has to carry its permissions, not just its name. The seeder does not run in
         * tests, so a bare Role::firstOrCreate('Admin') is a hollow shell — and now that
         * ActualTeamController actually checks `team.view`/`team.edit` (it previously checked
         * nothing at all, which let team managers delete teams), a nameless-but-powerless Admin
         * is correctly refused.
         */
        $adminRole = \App\Models\Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        foreach (['team.view', 'team.edit', 'team.delete'] as $permission) {
            $adminRole->givePermissionTo(\Spatie\Permission\Models\Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web'],
                ['group_name' => 'team']
            ));
        }
        $admin->assignRole($adminRole);
        $admin = $admin->fresh();

        $this->actingAs($admin)
            ->get(route('admin.actual-teams.edit', $team))
            ->assertOk()
            ->assertSee('Auction budget')
            ->assertSee('Total budget')
            ->assertSee('Remaining');
    }
}

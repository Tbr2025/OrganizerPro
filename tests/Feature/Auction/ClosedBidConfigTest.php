<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\Auction;
use App\Models\AuctionTeamBudget;
use App\Services\Auction\AuctionPoolService;
use App\Services\Auction\BidIncrementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * The rules a sealed round runs under: the amount grid, the per-player spend cap, and
 * the save-time guards that stop an auction being configured into a state where no legal
 * bid could ever be placed.
 *
 * Note `CreatesAuctionScenario` builds auctions with the squad reserve switched off
 * (`min_squad_size => 1`, `min_price_per_player => 0`), so a cap test here is testing the
 * cap and not accidentally the reserve. Tests that want the reserve opt in explicitly.
 */
class ClosedBidConfigTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function increments(): BidIncrementService
    {
        return app(BidIncrementService::class);
    }

    private function pools(): AuctionPoolService
    {
        return app(AuctionPoolService::class);
    }

    /* ── The amount grid ─────────────────────────────────────────────────────── */

    #[Test]
    public function the_step_defaults_to_one_tenth_of_a_million(): void
    {
        $auction = $this->makeAuction($this->makeOrganization());

        $this->assertNull($auction->closed_bid_step);
        $this->assertSame(100000.0, $auction->closedBidStep());
        $this->assertSame(70.0, $auction->closedBidMaxPct());
        $this->assertSame(2, $auction->closedBidMaxRebidRounds());
        $this->assertSame(3, $auction->closedBidTotalRounds());
    }

    #[Test]
    public function a_sealed_amount_must_be_an_exact_multiple_of_the_step(): void
    {
        $auction = $this->makeAuction($this->makeOrganization(), ['closed_bid_step' => 100000]);
        $inc = $this->increments();

        $this->assertTrue($inc->isLegalSealedAmount($auction, 9_100_000));
        $this->assertTrue($inc->isLegalSealedAmount($auction, 9_000_000));

        // The two the user called out by name.
        $this->assertFalse($inc->isLegalSealedAmount($auction, 9_050_000), '0.05M steps must be refused');
        $this->assertFalse($inc->isLegalSealedAmount($auction, 1_234_567));
    }

    #[Test]
    public function a_step_of_one_tenth_accepts_three_tenths(): void
    {
        // The float trap, directly: fmod(0.3, 0.1) is 0.09999999999999998, so any
        // modulo on floats refuses this legal amount.
        $auction = $this->makeAuction($this->makeOrganization(), ['closed_bid_step' => 0.1]);

        $this->assertTrue($this->increments()->isLegalSealedAmount($auction, 0.3));
        $this->assertTrue($this->increments()->isLegalSealedAmount($auction, 0.7));
        $this->assertFalse($this->increments()->isLegalSealedAmount($auction, 0.35));
    }

    #[Test]
    public function zero_and_negative_amounts_are_never_legal(): void
    {
        $auction = $this->makeAuction($this->makeOrganization(), ['closed_bid_step' => 100000]);

        $this->assertFalse($this->increments()->isLegalSealedAmount($auction, 0));
        $this->assertFalse($this->increments()->isLegalSealedAmount($auction, -100000));
    }

    #[Test]
    public function the_nearest_legal_amounts_are_named_either_side(): void
    {
        $auction = $this->makeAuction($this->makeOrganization(), ['closed_bid_step' => 100000]);

        // "9.05M is not allowed — try 9M or 9.1M."
        $this->assertSame(
            ['below' => 9_000_000.0, 'above' => 9_100_000.0],
            $this->increments()->nearestLegalAmounts($auction, 9_050_000)
        );
    }

    #[Test]
    public function snapping_and_next_legal_stay_on_the_grid(): void
    {
        $auction = $this->makeAuction($this->makeOrganization(), ['closed_bid_step' => 100000]);
        $inc = $this->increments();

        $this->assertSame(9_100_000.0, $inc->snapUpToStep($auction, 9_050_000));
        $this->assertSame(9_000_000.0, $inc->snapUpToStep($auction, 9_000_000), 'an amount already on the grid is untouched');
        // A tie-break floor must be strictly above the tied amount.
        $this->assertSame(9_100_000.0, $inc->nextLegalAbove($auction, 9_000_000));
    }

    /* ── The per-player cap ──────────────────────────────────────────────────── */

    #[Test]
    public function the_per_player_cap_is_a_share_of_the_total_allocation(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 10_000_000,
            'closed_bid_max_pct_of_budget' => 70,
        ]);
        $team = $this->makeTeam($org, 'Strikers', $tournament);

        $this->assertSame(7_000_000.0, $this->pools()->perPlayerCap($auction, $team->id));
    }

    #[Test]
    public function the_cap_is_computed_from_allocated_not_remaining(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 10_000_000,
            'closed_bid_max_pct_of_budget' => 70,
        ]);
        $team = $this->makeTeam($org, 'Strikers', $tournament);

        // 4M already spent. The cap must not shrink to 70% of the remaining 6M.
        $this->makeAuctionPlayer($auction, [
            'status' => 'sold',
            'sold_to_team_id' => $team->id,
            'final_price' => 4_000_000,
        ]);

        $this->assertSame(7_000_000.0, $this->pools()->perPlayerCap($auction, $team->id));
        $this->assertSame(6_000_000.0, $this->pools()->remainingBudget($auction, $team->id));
    }

    #[Test]
    public function the_cap_is_computed_from_a_per_team_budget_override_when_one_exists(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 10_000_000,
            'closed_bid_max_pct_of_budget' => 70,
        ]);
        $team = $this->makeTeam($org, 'Strikers', $tournament);

        AuctionTeamBudget::create([
            'auction_id' => $auction->id,
            'actual_team_id' => $team->id,
            'organization_id' => $org->id,
            'budget' => 5_000_000,
        ]);

        $this->assertSame(3_500_000.0, $this->pools()->perPlayerCap($auction, $team->id));
    }

    #[Test]
    public function the_lower_of_the_two_ceilings_binds(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $team = $this->makeTeam($org, 'Strikers', $tournament);
        $pools = $this->pools();

        // Cap binds: 70% of 10M = 7M, and with no reserve the reserve maximum is 10M.
        $capBinds = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 10_000_000,
            'closed_bid_max_pct_of_budget' => 70,
        ]);
        $this->assertSame(7_000_000.0, $pools->perPlayerCeiling($capBinds, $team->id));

        // Reserve binds: 5 slots at 1M holds back 4M, leaving 6M — below the 9M cap.
        $reserveBinds = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 10_000_000,
            'closed_bid_max_pct_of_budget' => 90,
            'min_squad_size' => 5,
            'min_price_per_player' => 1_000_000,
        ]);
        $this->assertSame(6_000_000.0, $pools->perPlayerCeiling($reserveBinds, $team->id));
    }

    #[Test]
    public function the_blocked_message_names_which_ceiling_bound(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $team = $this->makeTeam($org, 'Strikers', $tournament);

        $capBinds = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 10_000_000,
            'closed_bid_max_pct_of_budget' => 70,
        ]);

        // A team told only "max 7M" cannot tell whether to sell a player or wait.
        $this->assertStringContainsString(
            'one player',
            $this->pools()->sealedBlockedMessage($capBinds, $team->id, 8_000_000, 'Strikers')
        );

        $reserveBinds = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 10_000_000,
            'closed_bid_max_pct_of_budget' => 90,
            'min_squad_size' => 5,
            'min_price_per_player' => 1_000_000,
        ]);

        $this->assertStringContainsString(
            'squad',
            $this->pools()->sealedBlockedMessage($reserveBinds, $team->id, 8_000_000, 'Strikers')
        );
    }

    #[Test]
    public function an_open_tournament_has_no_sealed_ceiling(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org, 'open');
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $team = $this->makeTeam($org, 'Strikers', $tournament);

        $this->assertSame(PHP_FLOAT_MAX, $this->pools()->perPlayerCeiling($auction, $team->id));
        $this->assertTrue($this->pools()->canAffordSealed($auction, $team->id, 1.0e12));
    }

    #[Test]
    public function the_purse_state_carries_both_sealed_ceilings(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 10_000_000,
            'closed_bid_max_pct_of_budget' => 70,
            'min_squad_size' => 5,
            'min_price_per_player' => 1_000_000,
        ]);
        $team = $this->makeTeam($org, 'Strikers', $tournament);

        $state = $this->pools()->teamPurseState($auction, $team->id);

        $this->assertSame(70.0, $state['per_player_cap_pct']);
        $this->assertSame(7_000_000.0, $state['per_player_cap']);
        // Both are published so a team can see which rule is holding it back.
        $this->assertSame(6_000_000.0, $state['max_bid_allowed']);
        $this->assertSame(6_000_000.0, $state['sealed_max_bid'], 'the lower of the two');
    }

    /* ── Save-time guards ────────────────────────────────────────────────────── */

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Sealed Auction',
            'status' => 'scheduled',
            'max_budget_per_team' => 100_000_000,
            'base_price' => 1_000_000,
            'bid_type' => 'open',
            'bid_timer_seconds' => 30,
            'bid_rules' => [['from' => 0, 'to' => 200_000_000, 'increment' => 100_000]],
        ], $overrides);
    }

    #[Test]
    public function the_sealed_settings_round_trip_through_the_edit_wizard(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);

        $this->actingAs($this->makeAuctionOperator($org))
            ->put(route('admin.auctions.update', $auction), $this->payload([
                'organization_id' => $org->id,
                'tournament_id' => $tournament->id,
                'closed_bid_starts_at' => 8_000_000,
                'closed_bid_step' => 100_000,
                'closed_bid_max_pct_of_budget' => 70,
                'closed_bid_max_rebid_rounds' => 2,
                'closed_bid_timer_seconds' => 45,
                'closed_bid_requires_acceptance' => '1',
                'closed_bid_tie_breaker' => 'lot',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $auction->refresh();

        $this->assertSame(100_000.0, $auction->closedBidStep());
        $this->assertSame(70.0, $auction->closedBidMaxPct());
        $this->assertSame(2, $auction->closedBidMaxRebidRounds());
        $this->assertSame(45, $auction->closedBidTimerSeconds());
        /*
         * The column still takes the value — a per-auction setting has to be storable for a
         * future switch to read — but acceptance itself has been removed, so the RULE is false
         * whatever is saved. Asserted as a pair, because a stored 1 that silently did nothing
         * would otherwise look like a bug the next time somebody reads this test.
         */
        $this->assertSame(1, (int) $auction->closed_bid_requires_acceptance);
        $this->assertFalse($auction->closedBidRequiresAcceptance());
        $this->assertSame('lot', $auction->closedBidTieBreaker());
    }

    #[Test]
    public function the_create_wizard_offers_the_sealed_settings_too(): void
    {
        $org = $this->makeOrganization();
        $org->update(['auction_enabled' => true]);

        // The create wizard is materially thinner than edit and has historically been
        // missing fields that store() validates — so a setting added only to edit would
        // silently take its default on every wizard-created auction.
        $this->actingAs($this->makeAuctionOperator($org, ['auction.create', 'auction.edit', 'auction.view']))
            ->get(route('admin.auctions.create'))
            ->assertOk()
            ->assertSee('closed_bid_step', false)
            ->assertSee('closed_bid_max_pct_of_budget', false)
            ->assertSee('closed_bid_max_rebid_rounds', false)
            ->assertSee('closed_bid_tie_breaker', false);
    }

    #[Test]
    public function the_edit_wizard_offers_the_sealed_settings(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);

        $this->actingAs($this->makeAuctionOperator($org))
            ->get(route('admin.auctions.edit', $auction))
            ->assertOk()
            ->assertSee('closed_bid_step', false)
            ->assertSee('closed_bid_max_pct_of_budget', false)
            ->assertSee('Sealed Round');
    }

    #[Test]
    public function a_threshold_off_the_step_grid_is_refused(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);

        // 8M is not a multiple of 300K, so there is no legal bid AT the opening amount.
        $this->actingAs($this->makeAuctionOperator($org))
            ->put(route('admin.auctions.update', $auction), $this->payload([
                'organization_id' => $org->id,
                'tournament_id' => $tournament->id,
                'closed_bid_starts_at' => 8_000_000,
                'closed_bid_step' => 300_000,
            ]))
            ->assertSessionHasErrors('closed_bid_starts_at');
    }

    #[Test]
    public function a_cap_that_cannot_reach_the_threshold_is_refused(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);

        // 70% of a 10M budget is 7M, so nobody could ever bid the 8M opening amount and
        // every sealed round would end with no entrants.
        $this->actingAs($this->makeAuctionOperator($org))
            ->put(route('admin.auctions.update', $auction), $this->payload([
                'organization_id' => $org->id,
                'tournament_id' => $tournament->id,
                'max_budget_per_team' => 10_000_000,
                'closed_bid_starts_at' => 8_000_000,
                'closed_bid_step' => 100_000,
                'closed_bid_max_pct_of_budget' => 70,
            ]))
            ->assertSessionHasErrors('closed_bid_max_pct_of_budget');
    }

    #[Test]
    public function a_reserve_that_leaves_no_room_for_the_threshold_is_refused(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);

        // 11 places at 1M holds back 10M of a 20M budget, so the reserve alone blocks a
        // 15M opening amount even though the 90% cap would allow it.
        $this->actingAs($this->makeAuctionOperator($org))
            ->put(route('admin.auctions.update', $auction), $this->payload([
                'organization_id' => $org->id,
                'tournament_id' => $tournament->id,
                'max_budget_per_team' => 20_000_000,
                'closed_bid_starts_at' => 15_000_000,
                'closed_bid_step' => 100_000,
                'closed_bid_max_pct_of_budget' => 90,
                'min_squad_size' => 11,
                'min_price_per_player' => 1_000_000,
            ]))
            ->assertSessionHasErrors('closed_bid_starts_at');
    }

    #[Test]
    public function a_satisfiable_sealed_configuration_saves(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);

        $this->actingAs($this->makeAuctionOperator($org))
            ->put(route('admin.auctions.update', $auction), $this->payload([
                'organization_id' => $org->id,
                'tournament_id' => $tournament->id,
                'max_budget_per_team' => 100_000_000,
                'closed_bid_starts_at' => 8_000_000,
                'closed_bid_step' => 100_000,
                'closed_bid_max_pct_of_budget' => 70,
                'min_squad_size' => 11,
                'min_price_per_player' => 1_000_000,
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    #[Test]
    public function a_zero_step_is_refused_rather_than_treated_as_no_grid(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);

        $this->actingAs($this->makeAuctionOperator($org))
            ->put(route('admin.auctions.update', $auction), $this->payload([
                'organization_id' => $org->id,
                'tournament_id' => $tournament->id,
                'closed_bid_step' => 0,
            ]))
            ->assertSessionHasErrors('closed_bid_step');
    }

    #[Test]
    public function an_auction_with_no_sealed_threshold_is_never_blocked_by_the_guard(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);

        // Deliberately incoherent sealed settings, but no threshold — so no sealed round
        // can ever open and the guard must stay out of the way.
        $this->actingAs($this->makeAuctionOperator($org))
            ->put(route('admin.auctions.update', $auction), $this->payload([
                'organization_id' => $org->id,
                'tournament_id' => $tournament->id,
                'max_budget_per_team' => 1_000_000,
                'closed_bid_step' => 300_000,
                'closed_bid_max_pct_of_budget' => 1,
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }
}

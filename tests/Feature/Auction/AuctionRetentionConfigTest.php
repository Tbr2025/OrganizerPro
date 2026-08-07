<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\AuctionTeamBudget;
use App\Services\Auction\AuctionPoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Saving the new retention/squad settings, and the per-team budgets that were
 * previously only reachable from the edit wizard.
 */
class AuctionRetentionConfigTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    /** The smallest payload the update endpoint accepts. */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Auction',
            'status' => 'scheduled',
            'max_budget_per_team' => 100_000_000,
            'base_price' => 1_000_000,
            'bid_type' => 'open',
            'bid_timer_seconds' => 30,
            'bid_rules' => [['from' => 0, 'to' => 200_000_000, 'increment' => 100_000]],
        ], $overrides);
    }

    /** store() additionally requires a window; update() does not. */
    private function createPayload(array $overrides = []): array
    {
        return $this->payload(array_merge([
            'start_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'end_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
        ], $overrides));
    }

    #[Test]
    public function the_settings_round_trip_through_the_edit_wizard(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);

        $this->actingAs($this->makeAuctionOperator($org))
            ->put(route('admin.auctions.update', $auction), $this->payload([
                'organization_id' => $org->id,
                'tournament_id' => $tournament->id,
                'min_squad_size' => 15,
                'max_squad_size' => 25,
                'default_retained_value' => 5_000_000,
                'expected_retained_per_team' => 4,
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $auction->refresh();

        $this->assertSame(15, $auction->minSquadSize());
        $this->assertSame(25, $auction->maxSquadSize());
        $this->assertSame(5_000_000.0, $auction->defaultRetainedValue());
        $this->assertSame(4, $auction->expectedRetainedPerTeam());
    }

    #[Test]
    public function the_new_settings_can_be_cleared_again(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_squad_size' => 25,
            'default_retained_value' => 7_000_000,
            'expected_retained_per_team' => 6,
        ]);

        // Preserve-on-absent would make these impossible to clear — the trap the colour
        // fields fell into, where every save stamped a value back on.
        $this->actingAs($this->makeAuctionOperator($org))
            ->put(route('admin.auctions.update', $auction), $this->payload([
                'organization_id' => $org->id,
                'tournament_id' => $tournament->id,
                'max_squad_size' => '',
                'default_retained_value' => '',
                'expected_retained_per_team' => '',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $auction->refresh();

        $this->assertNull($auction->max_squad_size);
        $this->assertNull($auction->default_retained_value);
        $this->assertNull($auction->expected_retained_per_team);
        // ...and the accessors fall back to their constants.
        $this->assertSame(5_000_000.0, $auction->defaultRetainedValue());
        $this->assertSame(4, $auction->expectedRetainedPerTeam());
    }

    #[Test]
    public function a_max_squad_size_below_the_minimum_is_rejected(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);

        $this->actingAs($this->makeAuctionOperator($org))
            ->put(route('admin.auctions.update', $auction), $this->payload([
                'organization_id' => $org->id,
                'tournament_id' => $tournament->id,
                'min_squad_size' => 18,
                'max_squad_size' => 11,
            ]))
            ->assertSessionHasErrors('max_squad_size');
    }

    #[Test]
    public function max_squad_size_never_changes_what_a_team_may_bid(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $team = $this->makeTeam($org, 'Strikers', $tournament);
        $pools = app(AuctionPoolService::class);

        $without = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 10_000_000,
            'min_squad_size' => 5,
            'min_price_per_player' => 100_000,
        ]);
        $with = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 10_000_000,
            'min_squad_size' => 5,
            'min_price_per_player' => 100_000,
            'max_squad_size' => 25,
        ]);

        // max_squad_size is display and warning only. If it ever starts biting, it would
        // change live bidding on every auction the moment somebody fills it in.
        $this->assertSame(
            $pools->maxAllowedBid($without, $team->id),
            $pools->maxAllowedBid($with, $team->id)
        );
    }

    #[Test]
    public function per_team_budgets_can_be_set_when_the_auction_is_created(): void
    {
        $org = $this->makeOrganization();
        $org->update(['auction_enabled' => true]);
        $tournament = $this->makeTournament($org);
        $team = $this->makeTeam($org, 'Strikers', $tournament);

        $this->actingAs($this->makeAuctionOperator($org, ['auction.create', 'auction.edit', 'auction.view']))
            ->post(route('admin.auctions.store'), $this->createPayload([
                'organization_id' => $org->id,
                'tournament_id' => $tournament->id,
                'team_budgets' => [$team->id => 20_000_000],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('auction_team_budgets', [
            'actual_team_id' => $team->id,
            'budget' => 20_000_000,
        ]);
    }

    #[Test]
    public function a_budget_for_a_team_outside_the_tournament_is_ignored(): void
    {
        $org = $this->makeOrganization();
        $org->update(['auction_enabled' => true]);
        $tournament = $this->makeTournament($org);
        $otherTournament = $this->makeTournament($org);
        $outsider = $this->makeTeam($org, 'Outsiders', $otherTournament);

        $this->actingAs($this->makeAuctionOperator($org, ['auction.create', 'auction.edit', 'auction.view']))
            ->post(route('admin.auctions.store'), $this->createPayload([
                'organization_id' => $org->id,
                'tournament_id' => $tournament->id,
                'team_budgets' => [$outsider->id => 20_000_000],
            ]))
            ->assertRedirect();

        // Keys come straight off the request, so without the membership check any
        // actual_team_id could be written — including another organization's.
        $this->assertDatabaseMissing('auction_team_budgets', ['actual_team_id' => $outsider->id]);
    }

    #[Test]
    public function a_blank_per_team_budget_clears_the_override_rather_than_writing_zero(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 100_000_000,
        ]);
        $team = $this->makeTeam($org, 'Strikers', $tournament);

        AuctionTeamBudget::create([
            'auction_id' => $auction->id,
            'actual_team_id' => $team->id,
            'organization_id' => $org->id,
            'budget' => 20_000_000,
        ]);

        $this->actingAs($this->makeAuctionOperator($org))
            ->put(route('admin.auctions.update', $auction), $this->payload([
                'organization_id' => $org->id,
                'tournament_id' => $tournament->id,
                'team_budgets' => [$team->id => ''],
            ]))
            ->assertRedirect();

        // Writing 0 instead of deleting would silently strand the team on a zero purse,
        // because a zero row beats the uniform cap.
        $this->assertDatabaseMissing('auction_team_budgets', [
            'auction_id' => $auction->id,
            'actual_team_id' => $team->id,
        ]);
        $this->assertSame(
            100_000_000.0,
            app(AuctionPoolService::class)->allocatedBudget($auction->fresh(), $team->id)
        );
    }
}

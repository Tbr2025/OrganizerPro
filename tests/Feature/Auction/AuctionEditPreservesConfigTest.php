<?php

namespace Tests\Feature\Auction;

use App\Models\AuctionPool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Saving the auction wizard used to be quietly destructive: it deleted every pool
 * and rebuilt them, so pool base prices, categories and capacities were reset and
 * retained prices were lost. It also 422'd with no visible error on a legacy
 * auction whose organization_id is NULL, and stamped #000000 over unset colours.
 */
class AuctionEditPreservesConfigTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Updated Auction',
            'status' => 'scheduled',
            'max_budget_per_team' => 100000,
            'base_price' => 100,
            'bid_type' => 'open',
            'bid_timer_seconds' => 30,
            'bid_rules' => [['from' => 0, 'to' => 100000, 'increment' => 100]],
        ], $overrides);
    }

    #[Test]
    public function saving_the_wizard_preserves_pool_base_price_and_category(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $operator = $this->makeAuctionOperator($org);

        // Configured on the pools screen, which the wizard cannot edit.
        $pool = $this->makePool($auction, [
            'name' => 'Marquee',
            'base_price' => 5000,
            'category' => 'Marquee',
            'capacity' => 8,
        ]);

        $player = $this->makePlayer($org);
        $this->makeAuctionPlayer($auction, ['player' => $player, 'auction_pool_id' => $pool->id]);

        $this->actingAs($operator)->put(
            route('admin.auctions.update', $auction),
            $this->payload([
                'organization_id' => $org->id,
                'tournament_id' => $tournament->id,
                'pools' => json_encode([[
                    'id' => $pool->id,
                    'name' => 'Marquee',
                    'order_mode' => 'sequential',
                    'players' => [['id' => $player->id, 'base_price' => 200]],
                ]]),
            ])
        )->assertRedirect();

        // Same pool row, settings intact.
        $this->assertSame(1, AuctionPool::where('auction_id', $auction->id)->count());
        $pool->refresh();
        $this->assertSame($pool->id, AuctionPool::where('auction_id', $auction->id)->first()->id);
        $this->assertSame('5000.00', (string) $pool->base_price);
        $this->assertSame('Marquee', $pool->category);
        $this->assertSame(8, $pool->capacity);
    }

    #[Test]
    public function saving_the_wizard_keeps_a_retained_players_price_and_team(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $operator = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'Strikers', $tournament);

        $pool = $this->makePool($auction);
        $player = $this->makePlayer($org, ['player_mode' => 'retained']);
        $this->makeAuctionPlayer($auction, [
            'player' => $player,
            'auction_pool_id' => $pool->id,
            'is_retained' => true,
            'retained_price' => 7500,
            'team_id' => $team->id,
        ]);

        $this->actingAs($operator)->put(
            route('admin.auctions.update', $auction),
            $this->payload([
                'organization_id' => $org->id,
                'tournament_id' => $tournament->id,
                'pools' => json_encode([[
                    'id' => $pool->id,
                    'name' => $pool->name,
                    'order_mode' => 'sequential',
                    'players' => [['id' => $player->id, 'base_price' => 100]],
                ]]),
            ])
        )->assertRedirect();

        $row = $auction->auctionPlayers()->where('player_id', $player->id)->first();
        $this->assertTrue((bool) $row->is_retained);
        $this->assertSame(7500, (int) $row->retained_price);
        $this->assertSame($team->id, $row->team_id);
    }

    #[Test]
    public function a_legacy_null_org_auction_saves_without_an_organization_id(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        // Legacy row: no organization.
        $auction->forceFill(['organization_id' => null])->saveQuietly();
        // A NULL-org auction is invisible to a scoped user (OrganizationScope), so in
        // practice only a Superadmin edits one.
        $operator = $this->makeSuperadmin($org);

        // The edit form posts an empty hidden organization_id, which used to fail the
        // `required` rule and abort the save with no visible error.
        $this->actingAs($operator)->put(
            route('admin.auctions.update', $auction),
            $this->payload([
                'organization_id' => '',
                'tournament_id' => $tournament->id,
                'name' => 'Renamed',
            ])
        )->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $auction->fresh()->name);
        // Org is re-derived from the tournament.
        $this->assertSame($org->id, $auction->fresh()->organization_id);
    }

    #[Test]
    public function saving_does_not_stamp_black_over_unset_branding_colours(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $operator = $this->makeAuctionOperator($org);

        $this->assertNull($auction->primary_color);

        // <input type="color"> always posts, defaulting to #000000 when never touched.
        $this->actingAs($operator)->put(
            route('admin.auctions.update', $auction),
            $this->payload([
                'organization_id' => $org->id,
                'tournament_id' => $tournament->id,
                'primary_color' => '',
                'secondary_color' => '',
            ])
        )->assertRedirect();

        $auction->refresh();
        $this->assertNull($auction->primary_color);
        $this->assertNull($auction->secondary_color);
    }

    #[Test]
    public function an_unsatisfiable_squad_reserve_is_rejected_at_save_time(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $operator = $this->makeAuctionOperator($org);

        // 11 slots x 1,000,000 = 11,000,000 needed against a 10,000,000 purse. The
        // reserve would leave a maximum allowable bid of zero, so no player could ever
        // be bought — this has to fail at save time, not silently on auction day.
        $this->actingAs($operator)->put(
            route('admin.auctions.update', $auction),
            $this->payload([
                'organization_id' => $org->id,
                'tournament_id' => $tournament->id,
                'max_budget_per_team' => 10000000,
                'min_squad_size' => 11,
                'min_price_per_player' => 1000000,
            ])
        )->assertSessionHasErrors('min_price_per_player');
    }

    #[Test]
    public function a_satisfiable_squad_reserve_saves(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $operator = $this->makeAuctionOperator($org);

        // 11 x 1,000,000 = 11,000,000, which fits a 20,000,000 purse.
        $this->actingAs($operator)->put(
            route('admin.auctions.update', $auction),
            $this->payload([
                'organization_id' => $org->id,
                'tournament_id' => $tournament->id,
                'max_budget_per_team' => 20000000,
                'min_squad_size' => 11,
                'min_price_per_player' => 1000000,
            ])
        )->assertRedirect()->assertSessionHasNoErrors();

        $auction->refresh();
        $this->assertSame(11, $auction->min_squad_size);
        $this->assertSame('1000000.00', (string) $auction->min_price_per_player);
    }

    #[Test]
    public function a_blank_minimum_price_falls_back_to_the_auction_base_price(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, [
            'base_price' => 25000,
            'min_price_per_player' => 0,
        ]);

        // 0 means "use base_price", so the rule is always satisfiable.
        $this->assertSame(25000.0, $auction->minPricePerPlayer());
    }

    #[Test]
    public function money_entered_in_millions_round_trips_as_raw_units(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $operator = $this->makeAuctionOperator($org);

        // The wizard shows millions but posts raw units via hidden inputs. These are the
        // exact values 10 / 0.1 / 20 / 0.5 / 1 produce after conversion — a scaling bug
        // here would silently store money 10x or 100x wrong.
        $this->actingAs($operator)->put(route('admin.auctions.update', $auction), $this->payload([
            'organization_id' => $org->id,
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 10000000,   // 10 M
            'base_price' => 100000,             // 0.1 M
            'min_squad_size' => 5,
            'min_price_per_player' => 1000000,  // 1 M
            'closed_bid_starts_at' => 500000,   // 0.5 M
            'online_bid_limit_from' => 100000,  // 0.1 M
            'online_bid_limit_to' => 1000000,   // 1 M
            'bid_rules' => [['from' => 0, 'to' => 20000000, 'increment' => 100000]],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $auction->refresh();
        $this->assertSame('10000000.00', (string) $auction->max_budget_per_team);
        $this->assertSame('100000.00', (string) $auction->base_price);
        $this->assertSame('1000000.00', (string) $auction->min_price_per_player);
        $this->assertSame(5, $auction->min_squad_size);
        $this->assertSame('500000.00', (string) $auction->closed_bid_starts_at);
        $this->assertSame('1000000.00', (string) $auction->online_bid_limit_to);
        $this->assertSame(100000.0, (float) $auction->bid_rules[0]['increment']);
    }

    #[Test]
    public function the_offline_threshold_can_be_set_without_the_online_lower_bound(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $operator = $this->makeAuctionOperator($org);

        // `gt:online_bid_limit_from` used to fire even when that field was left blank,
        // rejecting the save with a message about a field the operator never filled.
        $this->actingAs($operator)->put(route('admin.auctions.update', $auction), $this->payload([
            'organization_id' => $org->id,
            'tournament_id' => $tournament->id,
            'online_bid_limit_from' => '',
            'online_bid_limit_to' => 2000000,
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('2000000.00', (string) $auction->fresh()->online_bid_limit_to);
    }

    #[Test]
    public function quick_bid_steps_are_stored_as_raw_units(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $operator = $this->makeAuctionOperator($org);

        // Entered as 0.5 / 1 / 5 in the wizard.
        $this->actingAs($operator)->put(route('admin.auctions.update', $auction), $this->payload([
            'organization_id' => $org->id,
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 100000000,
            'quick_bid_steps' => [500000, 1000000, 5000000, ''],
        ]))->assertRedirect();

        // Blank rows dropped, values sorted and de-duplicated by the model.
        $this->assertSame([500000.0, 1000000.0, 5000000.0], $auction->fresh()->quickBidSteps());
    }

    #[Test]
    public function a_zero_bid_increment_is_rejected_at_save_time(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $operator = $this->makeAuctionOperator($org);

        // A zero increment is the "maximum bid reached" sentinel at runtime, so it
        // must fail validation rather than deadlock bidding mid-auction.
        $this->actingAs($operator)->put(
            route('admin.auctions.update', $auction),
            $this->payload([
                'organization_id' => $org->id,
                'tournament_id' => $tournament->id,
                'bid_rules' => [['from' => 0, 'to' => 100000, 'increment' => 0]],
            ])
        )->assertSessionHasErrors('bid_rules.0.increment');
    }
}

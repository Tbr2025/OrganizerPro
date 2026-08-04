<?php

namespace Tests\Feature\Auction;

use App\Services\Auction\AuctionPoolService;
use App\Services\Auction\BidIncrementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * The increment ladder was duplicated in six places and three copies disagreed on
 * the band boundary and on the fallback increment. Everything now goes through
 * BidIncrementService, and quick-bid jumps are chosen by index so a client can never
 * name its own amount.
 */
class AuctionBidIncrementTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function service(): BidIncrementService
    {
        return app(BidIncrementService::class);
    }

    #[Test]
    public function the_increment_is_resolved_from_the_matching_band(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, [
            'bid_rules' => [
                ['from' => 0, 'to' => 1000, 'increment' => 100],
                ['from' => 1000, 'to' => 5000, 'increment' => 500],
                ['from' => 5000, 'to' => 20000, 'increment' => 1000],
            ],
        ]);

        $this->assertSame(100.0, $this->service()->incrementFor($auction, 0));
        $this->assertSame(100.0, $this->service()->incrementFor($auction, 900));
        $this->assertSame(500.0, $this->service()->incrementFor($auction, 2000));
        $this->assertSame(1000.0, $this->service()->incrementFor($auction, 10000));
    }

    #[Test]
    public function band_boundaries_are_inclusive_at_both_ends(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, [
            'bid_rules' => [
                ['from' => 0, 'to' => 1000, 'increment' => 100],
                ['from' => 1001, 'to' => 5000, 'increment' => 500],
            ],
        ]);

        // The old JS copy used `< to` here and would have returned 500.
        $this->assertSame(100.0, $this->service()->incrementFor($auction, 1000));
        $this->assertSame(500.0, $this->service()->incrementFor($auction, 1001));
    }

    #[Test]
    public function a_price_in_a_gap_resolves_to_the_next_band_up(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, [
            'bid_rules' => [
                ['from' => 0, 'to' => 1000, 'increment' => 100],
                // Deliberate gap between 1000 and 5000.
                ['from' => 5000, 'to' => 20000, 'increment' => 1000],
            ],
        ]);

        $this->assertSame(1000.0, $this->service()->incrementFor($auction, 3000));
    }

    #[Test]
    public function running_off_the_top_of_the_ladder_reports_max_reached(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, [
            'bid_rules' => [['from' => 0, 'to' => 1000, 'increment' => 100]],
        ]);

        // No fallback constant — the old copies invented 1000 / 10000 / 100000 here.
        $this->assertSame(0.0, $this->service()->incrementFor($auction, 50000));
        $this->assertNull($this->service()->nextBidAmount($auction, 50000));
        $this->assertTrue($this->service()->state($auction, 50000)['max_reached']);
    }

    #[Test]
    public function a_quick_bid_step_applies_a_configured_jump(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, [
            'max_budget_per_team' => 1000000,
            'bid_rules' => [['from' => 0, 'to' => 1000000, 'increment' => 100]],
            'quick_bid_steps' => [500, 1000, 5000],
        ]);
        $operator = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'A');
        $ap = $this->makeAuctionPlayer($auction, ['status' => 'on_auction', 'base_price' => 1000]);
        $ap->update(['current_price' => 1000]);

        // Index 2 => +5000, not the standard +100.
        $this->actingAs($operator)
            ->postJson(route('admin.auctions.players.addBid'), [
                'auctionId' => $auction->id, 'playerID' => $ap->id, 'teamId' => $team->id, 'stepIndex' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('current_price', 6000);
    }

    #[Test]
    public function an_unconfigured_quick_bid_step_is_refused(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, [
            'max_budget_per_team' => 1000000,
            'quick_bid_steps' => [500],
        ]);
        $operator = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'A');
        $ap = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);

        // A client cannot reach past the configured list.
        $this->actingAs($operator)
            ->postJson(route('admin.auctions.players.addBid'), [
                'auctionId' => $auction->id, 'playerID' => $ap->id, 'teamId' => $team->id, 'stepIndex' => 9,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame(100.0, (float) $ap->fresh()->current_price);
    }

    #[Test]
    public function quick_bid_steps_are_cleaned_and_sorted(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, [
            'quick_bid_steps' => [5000, 'nonsense', 500, 0, -100, 1000, 500],
        ]);

        $this->assertSame([500.0, 1000.0, 5000.0], $auction->quickBidSteps());
    }

    #[Test]
    public function base_price_resolves_player_then_pool_then_auction(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, ['base_price' => 100]);
        $pool = $this->makePool($auction, ['base_price' => 5000]);
        $service = app(AuctionPoolService::class);

        // Explicit per-player price wins.
        $this->assertSame(250.0, $service->resolveBasePrice($auction, $pool, 250));
        // Then the pool's price.
        $this->assertSame(5000.0, $service->resolveBasePrice($auction, $pool, null));
        // Then the auction's.
        $this->assertSame(100.0, $service->resolveBasePrice($auction, $this->makePool($auction), null));
        $this->assertSame(100.0, $service->resolveBasePrice($auction, null, null));
    }

    #[Test]
    public function a_pool_base_price_is_applied_to_its_players_on_save(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id, 'base_price' => 100]);
        $operator = $this->makeAuctionOperator($org);

        $pool = $this->makePool($auction, ['name' => 'Marquee', 'base_price' => 5000]);
        $player = $this->makePlayer($org);

        $this->actingAs($operator)->put(route('admin.auctions.update', $auction), [
            'name' => $auction->name,
            'organization_id' => $org->id,
            'tournament_id' => $tournament->id,
            'status' => 'scheduled',
            'max_budget_per_team' => 1000000,
            'base_price' => 100,
            'bid_type' => 'open',
            'bid_timer_seconds' => 30,
            'bid_rules' => [['from' => 0, 'to' => 1000000, 'increment' => 100]],
            'pools' => json_encode([[
                'id' => $pool->id,
                'name' => 'Marquee',
                'order_mode' => 'sequential',
                // No per-player price, so the pool's 5000 must apply — not the
                // auction's 100.
                'players' => [['id' => $player->id]],
            ]]),
        ])->assertRedirect();

        $row = $auction->auctionPlayers()->where('player_id', $player->id)->first();
        $this->assertSame('5000.00', (string) $row->base_price);
    }
}

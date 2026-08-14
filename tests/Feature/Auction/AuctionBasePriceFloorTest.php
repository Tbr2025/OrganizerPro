<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\Auction;
use App\Models\AuctionPool;
use App\Models\Organization;
use App\Models\Tournament;
use App\Services\Auction\AuctionPoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * "Start at 1M in every auction" — and two ways it silently did not.
 *
 * Measured on live: one auction had every lot opening at 1 POINT while its own setting said
 * 1,000,000. The auction's base price is the floor for every lot; a pool or a player may be
 * dearer, never cheaper.
 */
class AuctionBasePriceFloorTest extends TestCase
{
    use RefreshDatabase;

    private function auction(float $base = 1_000_000): Auction
    {
        $org = Organization::create(['name' => 'Org']);
        $tournament = Tournament::create([
            'name' => 'T', 'slug' => 't', 'start_date' => '2026-01-01', 'organization_id' => $org->id,
        ]);

        return Auction::create([
            'name' => 'A', 'status' => 'scheduled', 'max_budget_per_team' => 100_000_000,
            'base_price' => $base, 'organization_id' => $org->id, 'tournament_id' => $tournament->id,
            'bid_type' => 'open', 'bid_timer_seconds' => 30,
            'bid_rules' => [['from' => 0, 'to' => 200_000_000, 'increment' => 100_000]],
        ]);
    }

    private function pool(Auction $auction, $base): AuctionPool
    {
        return AuctionPool::create([
            'auction_id' => $auction->id, 'name' => 'Pool A', 'base_price' => $base,
            'status' => 'pending', 'organization_id' => $auction->organization_id,
        ]);
    }

    #[Test]
    public function a_blank_price_posts_as_zero_and_must_not_win(): void
    {
        $auction = $this->auction();

        // An empty number input posts as 0, and is_numeric(0) is true — which is how 64 players
        // in one live auction ended up opening at nothing.
        $this->assertSame(
            1_000_000.0,
            app(AuctionPoolService::class)->resolveBasePrice($auction, null, 0)
        );
        $this->assertSame(
            1_000_000.0,
            app(AuctionPoolService::class)->resolveBasePrice($auction, null, '0')
        );
    }

    #[Test]
    public function the_pools_default_price_of_one_must_not_beat_the_auctions(): void
    {
        $auction = $this->auction();

        // auction_pools.base_price defaults to 1.00. A pool nobody had touched was opening its
        // lots at ONE point on a 1,000,000 auction.
        $this->assertSame(
            1_000_000.0,
            app(AuctionPoolService::class)->resolveBasePrice($auction, $this->pool($auction, 1.00))
        );
    }

    #[Test]
    public function a_dearer_pool_or_player_still_wins(): void
    {
        $auction = $this->auction();
        $service = app(AuctionPoolService::class);

        // The floor is a floor, not a fixed price: a marquee pool above it is the point of pools.
        $this->assertSame(5_000_000.0, $service->resolveBasePrice($auction, $this->pool($auction, 5_000_000)));
        $this->assertSame(2_500_000.0, $service->resolveBasePrice($auction, null, 2_500_000));
    }

    #[Test]
    public function an_auction_with_no_base_price_still_resolves_to_zero_rather_than_failing(): void
    {
        $auction = $this->auction(0);

        $this->assertSame(0.0, app(AuctionPoolService::class)->resolveBasePrice($auction, null, null));
    }
}

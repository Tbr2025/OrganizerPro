<?php

namespace Tests\Feature\Auction;

use App\Models\AuctionPool;
use App\Services\Auction\AuctionPoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Pool-locked auctioning: the organizer starts one pool and the auction serves only
 * that pool until it is closed.
 *
 * Before this, `auction_pools.status` was never written or read, both panels picked
 * the next player with Math.random(), and nothing stopped a player from another pool
 * being put on the block mid-round.
 */
class AuctionPoolLockTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    #[Test]
    public function activating_a_pool_locks_the_queue_to_it(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org);
        $operator = $this->makeAuctionOperator($org);

        $poolA = $this->makePool($auction, ['name' => 'Pool A', 'sequence' => 1]);
        $poolB = $this->makePool($auction, ['name' => 'Pool B', 'sequence' => 2]);

        $inA = $this->makeAuctionPlayer($auction, ['auction_pool_id' => $poolA->id, 'lot_number' => 1]);
        $inB = $this->makeAuctionPlayer($auction, ['auction_pool_id' => $poolB->id, 'lot_number' => 1]);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.pool.activate', [$auction, $poolA]))
            ->assertOk()
            ->assertJsonPath('success', true);

        $poolA->refresh();
        $this->assertSame(AuctionPool::STATUS_ACTIVE, $poolA->status);
        $this->assertSame(1, $poolA->times_used);
        $this->assertNotNull($poolA->activated_at);

        // The queue now contains only Pool A's player.
        $state = $this->actingAs($operator)
            ->getJson(route('admin.auction.organizer.api.poll-state', $auction))
            ->assertOk()
            ->json();

        $queuedIds = array_column($state['available_players'], 'id');
        $this->assertContains($inA->id, $queuedIds);
        $this->assertNotContains($inB->id, $queuedIds);
        $this->assertSame('Pool A', $state['active_pool']['name']);
    }

    #[Test]
    public function a_player_outside_the_active_pool_cannot_be_put_on_the_block(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org);
        $operator = $this->makeAuctionOperator($org);

        $poolA = $this->makePool($auction, ['name' => 'Pool A', 'sequence' => 1, 'status' => AuctionPool::STATUS_ACTIVE]);
        $poolB = $this->makePool($auction, ['name' => 'Pool B', 'sequence' => 2]);

        $this->makeAuctionPlayer($auction, ['auction_pool_id' => $poolA->id, 'lot_number' => 1]);
        $inB = $this->makeAuctionPlayer($auction, ['auction_pool_id' => $poolB->id, 'lot_number' => 1]);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.player.onbid', $auction), ['auction_player_id' => $inB->id])
            ->assertStatus(400)
            ->assertJsonPath('success', false);

        $this->assertSame('waiting', $inB->fresh()->status);
    }

    #[Test]
    public function next_player_follows_lot_order_within_the_active_pool(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org);

        // Pool B is active even though Pool A comes first in sequence — the lock wins.
        $poolA = $this->makePool($auction, ['name' => 'Pool A', 'sequence' => 1]);
        $poolB = $this->makePool($auction, ['name' => 'Pool B', 'sequence' => 2, 'status' => AuctionPool::STATUS_ACTIVE]);

        $this->makeAuctionPlayer($auction, ['auction_pool_id' => $poolA->id, 'lot_number' => 1]);
        $bSecond = $this->makeAuctionPlayer($auction, ['auction_pool_id' => $poolB->id, 'lot_number' => 2]);
        $bFirst = $this->makeAuctionPlayer($auction, ['auction_pool_id' => $poolB->id, 'lot_number' => 1]);

        $next = app(AuctionPoolService::class)->nextPlayer($auction);

        $this->assertSame($bFirst->id, $next->id);

        // And the second lot follows once the first is gone.
        $bFirst->update(['status' => 'sold']);
        $this->assertSame($bSecond->id, app(AuctionPoolService::class)->nextPlayer($auction)->id);
    }

    #[Test]
    public function a_disabled_pool_cannot_be_activated(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org);
        $operator = $this->makeAuctionOperator($org);

        $pool = $this->makePool($auction, ['name' => 'Retired Pool', 'is_enabled' => false]);
        $this->makeAuctionPlayer($auction, ['auction_pool_id' => $pool->id, 'lot_number' => 1]);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.pool.activate', [$auction, $pool]))
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame(AuctionPool::STATUS_PENDING, $pool->fresh()->status);
        $this->assertSame(0, $pool->fresh()->times_used);
    }

    #[Test]
    public function a_disabled_pool_is_excluded_from_the_fallback_queue(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org);

        // No pool active — the fallback runs across pools, but must skip disabled ones.
        $disabled = $this->makePool($auction, ['sequence' => 1, 'is_enabled' => false]);
        $enabled = $this->makePool($auction, ['sequence' => 2, 'is_enabled' => true]);

        $this->makeAuctionPlayer($auction, ['auction_pool_id' => $disabled->id, 'lot_number' => 1]);
        $wanted = $this->makeAuctionPlayer($auction, ['auction_pool_id' => $enabled->id, 'lot_number' => 1]);

        $this->assertSame($wanted->id, app(AuctionPoolService::class)->nextPlayer($auction)->id);
    }

    #[Test]
    public function the_running_pool_cannot_be_disabled_until_it_is_closed(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org);
        $operator = $this->makeAuctionOperator($org);

        $pool = $this->makePool($auction, ['status' => AuctionPool::STATUS_ACTIVE]);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.pool.toggle-enabled', [$auction, $pool]), ['is_enabled' => false])
            ->assertStatus(422);

        $this->assertTrue($pool->fresh()->isEnabled());

        // Close it, then disabling is allowed.
        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.pool.complete', [$auction, $pool]))
            ->assertOk();

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.pool.toggle-enabled', [$auction, $pool]), ['is_enabled' => false])
            ->assertOk();

        $this->assertFalse($pool->fresh()->isEnabled());
    }

    #[Test]
    public function pools_cannot_be_switched_while_a_player_is_on_the_block(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org);
        $operator = $this->makeAuctionOperator($org);

        $poolA = $this->makePool($auction, ['sequence' => 1, 'status' => AuctionPool::STATUS_ACTIVE]);
        $poolB = $this->makePool($auction, ['sequence' => 2]);

        $this->makeAuctionPlayer($auction, ['auction_pool_id' => $poolA->id, 'status' => 'on_auction']);
        $this->makeAuctionPlayer($auction, ['auction_pool_id' => $poolB->id, 'lot_number' => 1]);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.pool.activate', [$auction, $poolB]))
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame(AuctionPool::STATUS_PENDING, $poolB->fresh()->status);
    }

    #[Test]
    public function completing_a_pool_suggests_the_next_one_without_starting_it(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org);
        $operator = $this->makeAuctionOperator($org);

        $poolA = $this->makePool($auction, ['name' => 'Pool A', 'sequence' => 1, 'status' => AuctionPool::STATUS_ACTIVE]);
        $poolB = $this->makePool($auction, ['name' => 'Pool B', 'sequence' => 2]);
        $this->makeAuctionPlayer($auction, ['auction_pool_id' => $poolB->id, 'lot_number' => 1]);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.pool.complete', [$auction, $poolA]))
            ->assertOk()
            ->assertJsonPath('next_pool.name', 'Pool B');

        $poolA->refresh();
        $this->assertSame(AuctionPool::STATUS_COMPLETED, $poolA->status);
        $this->assertNotNull($poolA->completed_at);

        // Crucially, Pool B is NOT auto-started — the organizer decides.
        $this->assertSame(AuctionPool::STATUS_PENDING, $poolB->fresh()->status);
    }

    #[Test]
    public function re_running_a_pool_increments_its_usage_count(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org);
        $operator = $this->makeAuctionOperator($org);

        $pool = $this->makePool($auction);
        $this->makeAuctionPlayer($auction, ['auction_pool_id' => $pool->id, 'lot_number' => 1]);

        foreach ([1, 2, 3] as $expected) {
            $this->actingAs($operator)
                ->postJson(route('admin.auction.organizer.api.pool.activate', [$auction, $pool]))
                ->assertOk();
            $this->assertSame($expected, $pool->fresh()->times_used);

            $this->actingAs($operator)
                ->postJson(route('admin.auction.organizer.api.pool.complete', [$auction, $pool]))
                ->assertOk();
        }
    }

    #[Test]
    public function an_empty_pool_cannot_be_activated(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org);
        $operator = $this->makeAuctionOperator($org);

        $pool = $this->makePool($auction, ['name' => 'Empty Pool']);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.pool.activate', [$auction, $pool]))
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}

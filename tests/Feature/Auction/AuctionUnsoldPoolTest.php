<?php

namespace Tests\Feature\Auction;

use App\Models\Auction;
use App\Models\AuctionPool;
use App\Services\Auction\AuctionPoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * A player nobody bids on is set aside in an unsold holding pool tied to the pool they
 * came from, so final allotment after the auction can be run pool by pool.
 *
 * `is_unsold_pool` existed on auction_pools from the start but was never written or
 * read by anything.
 */
class AuctionUnsoldPoolTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function passing_a_player_moves_them_into_their_pools_unsold_pool(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org);
        $operator = $this->makeAuctionOperator($org);

        $pool = $this->makePool($auction, ['name' => 'Pool A']);
        $ap = $this->makeAuctionPlayer($auction, [
            'auction_pool_id' => $pool->id,
            'status' => 'on_auction',
            'lot_number' => 1,
        ]);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.player.pass', $auction), ['auction_player_id' => $ap->id])
            ->assertOk();

        $unsoldPool = AuctionPool::where('auction_id', $auction->id)->where('is_unsold_pool', true)->first();

        $this->assertNotNull($unsoldPool, 'An unsold holding pool should have been created.');
        $this->assertSame('Pool A — Unsold', $unsoldPool->name);
        $this->assertSame($pool->id, $unsoldPool->parent_pool_id);
        // Holding pool, never a bidding round.
        $this->assertFalse($unsoldPool->isEnabled());

        $ap->refresh();
        $this->assertSame('unsold', $ap->status);
        $this->assertSame($unsoldPool->id, $ap->auction_pool_id);
        $this->assertNull($ap->lot_number);
    }

    #[Test]
    public function each_pool_gets_its_own_unsold_pool(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org);
        $operator = $this->makeAuctionOperator($org);

        $poolA = $this->makePool($auction, ['name' => 'Pool A', 'sequence' => 1]);
        $poolB = $this->makePool($auction, ['name' => 'Pool B', 'sequence' => 2]);

        foreach ([$poolA, $poolB] as $pool) {
            $ap = $this->makeAuctionPlayer($auction, ['auction_pool_id' => $pool->id, 'status' => 'on_auction']);
            $this->actingAs($operator)
                ->postJson(route('admin.auction.organizer.api.player.pass', $auction), ['auction_player_id' => $ap->id])
                ->assertOk();
        }

        $unsold = AuctionPool::where('auction_id', $auction->id)->where('is_unsold_pool', true)->get();

        $this->assertCount(2, $unsold);
        $this->assertEqualsCanonicalizing(
            [$poolA->id, $poolB->id],
            $unsold->pluck('parent_pool_id')->all()
        );
    }

    #[Test]
    public function a_second_unsold_player_reuses_the_same_holding_pool(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org);
        $operator = $this->makeAuctionOperator($org);
        $pool = $this->makePool($auction, ['name' => 'Pool A']);

        foreach (range(1, 3) as $i) {
            $ap = $this->makeAuctionPlayer($auction, ['auction_pool_id' => $pool->id, 'status' => 'on_auction']);
            $this->actingAs($operator)
                ->postJson(route('admin.auction.organizer.api.player.pass', $auction), ['auction_player_id' => $ap->id])
                ->assertOk();
        }

        $this->assertSame(1, AuctionPool::where('auction_id', $auction->id)->where('is_unsold_pool', true)->count());
        $unsoldPool = AuctionPool::where('auction_id', $auction->id)->where('is_unsold_pool', true)->first();
        $this->assertSame(3, $unsoldPool->players()->count());
    }

    #[Test]
    public function an_unsold_pool_is_never_served_to_the_auction(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org);
        $operator = $this->makeAuctionOperator($org);
        $pool = $this->makePool($auction, ['name' => 'Pool A']);

        $ap = $this->makeAuctionPlayer($auction, ['auction_pool_id' => $pool->id, 'status' => 'on_auction']);
        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.player.pass', $auction), ['auction_player_id' => $ap->id])
            ->assertOk();

        $unsoldPool = AuctionPool::where('auction_id', $auction->id)->where('is_unsold_pool', true)->first();

        // Not offered as the next pool, and not startable.
        $this->assertNull(app(AuctionPoolService::class)->nextEnabledPool($auction));
        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.pool.activate', [$auction, $unsoldPool]))
            ->assertStatus(422);

        // Absent from the panel's pool list, which only shows biddable pools.
        $state = $this->actingAs($operator)
            ->getJson(route('admin.auction.organizer.api.poll-state', $auction))
            ->assertOk()->json();

        $this->assertNotContains($unsoldPool->id, array_column($state['pools'], 'id'));
    }

    #[Test]
    public function undoing_a_pass_returns_the_player_to_their_original_pool(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org);
        $operator = $this->makeAuctionOperator($org);
        $pool = $this->makePool($auction, ['name' => 'Pool A']);

        $ap = $this->makeAuctionPlayer($auction, [
            'auction_pool_id' => $pool->id,
            'status' => 'on_auction',
            'lot_number' => 4,
        ]);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.player.pass', $auction), ['auction_player_id' => $ap->id])
            ->assertOk();

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.undo', $auction))
            ->assertOk()
            ->assertJsonPath('success', true);

        $ap->refresh();
        $this->assertSame('on_auction', $ap->status);
        $this->assertSame($pool->id, $ap->auction_pool_id);
        $this->assertSame(4, $ap->lot_number);
    }

    #[Test]
    public function a_re_auction_round_brings_unsold_players_back_into_their_pool(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org);
        $operator = $this->makeAuctionOperator($org);
        $pool = $this->makePool($auction, ['name' => 'Pool A']);

        $first = $this->makeAuctionPlayer($auction, ['auction_pool_id' => $pool->id, 'status' => 'on_auction']);
        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.player.pass', $auction), ['auction_player_id' => $first->id])
            ->assertOk();

        $second = $this->makeAuctionPlayer($auction, ['auction_pool_id' => $pool->id, 'status' => 'on_auction']);
        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.player.pass', $auction), ['auction_player_id' => $second->id])
            ->assertOk();

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.start-reauction-round', $auction))
            ->assertOk()
            ->assertJsonPath('repooled_count', 2);

        // Back in the biddable pool, with fresh lots, so the auction can serve them.
        foreach ([$first, $second] as $player) {
            $player->refresh();
            $this->assertSame('waiting', $player->status);
            $this->assertSame($pool->id, $player->auction_pool_id);
            $this->assertNotNull($player->lot_number);
        }

        $this->assertNotNull(app(AuctionPoolService::class)->nextPlayer($auction));
    }

    #[Test]
    public function an_auto_sell_expiry_with_no_bids_sets_the_player_aside(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, ['timer_expiry_action' => Auction::TIMER_AUTO_SELL]);
        $operator = $this->makeAuctionOperator($org);
        $pool = $this->makePool($auction, ['name' => 'Pool A']);

        $ap = $this->makeAuctionPlayer($auction, ['auction_pool_id' => $pool->id, 'status' => 'on_auction']);

        Carbon::setTestNow(now());
        $auction->update(['timer_started_at' => now()->subSeconds(120)]);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.timer-expired', $auction), ['auction_player_id' => $ap->id])
            ->assertOk()
            ->assertJsonPath('action', 'unsold');

        $unsoldPool = AuctionPool::where('auction_id', $auction->id)->where('is_unsold_pool', true)->first();
        $this->assertNotNull($unsoldPool);
        $this->assertSame($unsoldPool->id, $ap->fresh()->auction_pool_id);
    }

    #[Test]
    public function unsold_pools_are_listed_for_allotment_with_their_source_pool(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org);
        $operator = $this->makeAuctionOperator($org);
        $pool = $this->makePool($auction, ['name' => 'Marquee']);

        $ap = $this->makeAuctionPlayer($auction, ['auction_pool_id' => $pool->id, 'status' => 'on_auction']);
        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.player.pass', $auction), ['auction_player_id' => $ap->id])
            ->assertOk();

        $pools = app(AuctionPoolService::class)->unsoldPools($auction);

        $this->assertCount(1, $pools);
        $this->assertSame('Marquee', $pools->first()->parentPool->name);
        $this->assertSame(1, (int) $pools->first()->unsold_count);
    }
}

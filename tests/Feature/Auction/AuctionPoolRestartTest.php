<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\AuctionBid;
use App\Models\AuctionPool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Re-running one pool without wiping the pools around it.
 *
 * The only restart was whole-auction, which is the wrong tool once an auction is several
 * pools deep: redoing a finished pool meant throwing away every other pool's results with
 * it. A pool restart undoes that pool's sales — the teams get their purse back, which is
 * what separates it from a second pass over whoever went unsold — and leaves the rest of
 * the auction exactly as it was.
 */
class AuctionPoolRestartTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    #[Test]
    public function restarting_a_pool_puts_its_players_back_and_undoes_its_sales(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id, 'status' => 'running']);
        $operator = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'Alpha', $tournament);

        $pool = $this->makePool($auction, ['name' => 'Pool A', 'sequence' => 1, 'status' => AuctionPool::STATUS_ACTIVE]);

        $sold = $this->makeAuctionPlayer($auction, [
            'auction_pool_id' => $pool->id,
            'lot_number' => 1,
            'status' => 'sold',
            'sold_to_team_id' => $team->id,
            'final_price' => 5_000_000,
            'current_price' => 5_000_000,
        ]);
        $unsold = $this->makeAuctionPlayer($auction, [
            'auction_pool_id' => $pool->id,
            'lot_number' => 2,
            'status' => 'unsold',
        ]);

        AuctionBid::create([
            'auction_id' => $auction->id,
            'auction_player_id' => $sold->id,
            'player_id' => $sold->player_id,
            'team_id' => $team->id,
            'user_id' => $operator->id,
            'amount' => 5_000_000,
            'bid_source' => 'offline',
        ]);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.pool.restart', [$auction, $pool]))
            ->assertOk()
            ->assertJsonPath('success', true);

        // Both back on the block, at their base price, with no buyer.
        foreach ([$sold, $unsold] as $ap) {
            $ap->refresh();
            $this->assertSame('waiting', $ap->status);
            $this->assertNull($ap->sold_to_team_id);
            $this->assertNull($ap->final_price);
            $this->assertEquals($ap->base_price, $ap->current_price);
        }

        // The money goes back with them: purses are derived from live bids, so the bid
        // being gone IS the refund.
        $this->assertSame(0, AuctionBid::where('auction_player_id', $sold->id)->count());

        // And the pool is in play again rather than sitting completed.
        $this->assertSame(AuctionPool::STATUS_ACTIVE, $pool->fresh()->status);
    }

    #[Test]
    public function other_pools_keep_their_results(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id, 'status' => 'running']);
        $operator = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'Alpha', $tournament);

        $poolA = $this->makePool($auction, ['name' => 'Pool A', 'sequence' => 1, 'status' => AuctionPool::STATUS_ACTIVE]);
        $poolB = $this->makePool($auction, ['name' => 'Pool B', 'sequence' => 2, 'status' => AuctionPool::STATUS_COMPLETED]);

        $this->makeAuctionPlayer($auction, [
            'auction_pool_id' => $poolA->id,
            'lot_number' => 1,
            'status' => 'unsold',
        ]);

        $elsewhere = $this->makeAuctionPlayer($auction, [
            'auction_pool_id' => $poolB->id,
            'lot_number' => 1,
            'status' => 'sold',
            'sold_to_team_id' => $team->id,
            'final_price' => 7_000_000,
        ]);

        $keptBid = AuctionBid::create([
            'auction_id' => $auction->id,
            'auction_player_id' => $elsewhere->id,
            'player_id' => $elsewhere->player_id,
            'team_id' => $team->id,
            'user_id' => $operator->id,
            'amount' => 7_000_000,
            'bid_source' => 'offline',
        ]);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.pool.restart', [$auction, $poolA]))
            ->assertOk();

        // This is the whole point: the sale in the other pool survives untouched.
        $elsewhere->refresh();
        $this->assertSame('sold', $elsewhere->status);
        $this->assertSame($team->id, $elsewhere->sold_to_team_id);
        $this->assertDatabaseHas('auction_bids', ['id' => $keptBid->id]);
        $this->assertSame(AuctionPool::STATUS_COMPLETED, $poolB->fresh()->status);
    }

    #[Test]
    public function a_player_on_the_block_blocks_the_restart(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, ['status' => 'running']);
        $operator = $this->makeAuctionOperator($org);

        $pool = $this->makePool($auction, ['name' => 'Pool A', 'sequence' => 1, 'status' => AuctionPool::STATUS_ACTIVE]);

        $live = $this->makeAuctionPlayer($auction, [
            'auction_pool_id' => $pool->id,
            'lot_number' => 1,
            'status' => 'on_auction',
        ]);

        // Resetting under a live board would strand whoever is mid-bid.
        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.pool.restart', [$auction, $pool]))
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame('on_auction', $live->fresh()->status);
    }

    #[Test]
    public function a_pool_from_another_auction_is_refused(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, ['status' => 'running']);
        $operator = $this->makeAuctionOperator($org);

        $otherAuction = $this->makeAuction($org, ['status' => 'running']);
        $foreignPool = $this->makePool($otherAuction, ['name' => 'Elsewhere', 'sequence' => 1]);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.pool.restart', [$auction, $foreignPool]))
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    #[Test]
    public function the_pool_reports_finished_only_once_nobody_is_on_the_block(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, ['status' => 'running']);
        $operator = $this->makeAuctionOperator($org);

        $pool = $this->makePool($auction, ['name' => 'Pool A', 'sequence' => 1, 'status' => AuctionPool::STATUS_ACTIVE]);

        $player = $this->makeAuctionPlayer($auction, [
            'auction_pool_id' => $pool->id,
            'lot_number' => 1,
            'status' => 'on_auction',
        ]);

        $state = fn () => $this->actingAs($operator)
            ->getJson(route('admin.auction.organizer.api.poll-state', $auction))
            ->json('active_pool');

        /*
         * The queue empties as the LAST player goes up, so `exhausted` is already true here
         * while that player is still being auctioned. The panel announced "Pool complete"
         * off it and offered the pool's closing controls over a live board.
         */
        $live = $state();
        $this->assertTrue($live['exhausted']);
        $this->assertFalse($live['finished']);
        $this->assertSame(1, $live['on_block']);

        $player->update(['status' => 'unsold']);

        $done = $state();
        $this->assertTrue($done['exhausted']);
        $this->assertTrue($done['finished']);
        $this->assertSame(0, $done['on_block']);
    }
}

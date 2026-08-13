<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\AuctionPool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * "No enabled pool has players left" used to be the end of the road.
 *
 * Once every pool had been closed or taken out of play, `activatePool()` refused both — a closed
 * pool has no waiting players (closing it sets its uncalled ones unsold) and a disabled one is
 * refused outright. The only way on was the pools admin screen, in the middle of a live auction,
 * in front of a room.
 *
 * Reopening is deliberately NOT restarting. A restart undoes that pool's sales and refunds the
 * purses — right when a pool was run wrongly, and wrong when the organizer simply wants another
 * go at the players nobody took. This keeps the sales and brings back only the unsold, which is
 * possible at all because the origin travels on the player (`source_pool_id`): unsold players
 * share one pile per auction, so the pile cannot say where anybody came from.
 */
class ReopenAPoolMidAuctionTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    #[Test]
    public function reopening_brings_back_the_unsold_and_keeps_the_sales(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 100_000_000,
            'min_squad_size' => 11,
        ]);
        $operator = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'Buyers', $tournament);

        $pool = $this->makePool($auction, ['name' => 'Pool A']);

        $sold = $this->makeAuctionPlayer($auction, [
            'auction_pool_id' => $pool->id, 'status' => 'sold',
            'sold_to_team_id' => $team->id, 'final_price' => 5_000,
        ]);
        $left = $this->makeAuctionPlayer($auction, [
            'auction_pool_id' => $pool->id, 'status' => 'waiting', 'lot_number' => 2,
        ]);

        // Closed early, so `$left` goes to the unsold pile with its origin recorded.
        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.pool.activate', [$auction, $pool]))
            ->assertOk();
        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.pool.complete', [$auction, $pool]))
            ->assertOk();

        $this->assertSame('unsold', $left->fresh()->status);
        $this->assertSame(AuctionPool::STATUS_COMPLETED, $pool->fresh()->status);

        // Activating it now is refused — this is the dead end being fixed.
        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.pool.activate', [$auction, $pool->fresh()]))
            ->assertStatus(422);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.pool.reopen', [$auction, $pool]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('reclaimed', 1);

        // Back on the block, in the pool, with a fresh lot.
        $left->refresh();
        $this->assertSame('waiting', $left->status);
        $this->assertSame($pool->id, $left->auction_pool_id);
        $this->assertNull($left->source_pool_id);
        $this->assertNotNull($left->lot_number);

        // The sale stands. This is the whole difference from Restart, and confusing the two
        // costs a team its squad.
        $sold->refresh();
        $this->assertSame('sold', $sold->status);
        $this->assertSame($team->id, $sold->sold_to_team_id);

        // And the pool is running again.
        $this->assertSame(AuctionPool::STATUS_ACTIVE, $pool->fresh()->status);
        $this->assertTrue((bool) $pool->fresh()->is_enabled);
    }

    #[Test]
    public function a_disabled_pool_is_re_enabled_rather_than_refused(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org);
        $operator = $this->makeAuctionOperator($org);

        $pool = $this->makePool($auction, ['name' => 'Benched', 'is_enabled' => false]);
        $this->makeAuctionPlayer($auction, ['auction_pool_id' => $pool->id, 'status' => 'waiting']);

        // activatePool() says "enable it first" — reopening IS that decision, so it carries it
        // out rather than reporting a state the operator then has to go and fix by hand.
        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.pool.activate', [$auction, $pool]))
            ->assertStatus(422);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.pool.reopen', [$auction, $pool]))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue((bool) $pool->fresh()->is_enabled);
        $this->assertSame(AuctionPool::STATUS_ACTIVE, $pool->fresh()->status);
    }

    #[Test]
    public function a_pool_whose_players_were_all_sold_says_so(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $operator = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'Buyers', $tournament);

        $pool = $this->makePool($auction, ['name' => 'All Gone']);
        $this->makeAuctionPlayer($auction, [
            'auction_pool_id' => $pool->id, 'status' => 'sold',
            'sold_to_team_id' => $team->id, 'final_price' => 100,
        ]);

        // Nothing to bring back, and reopening an empty pool would leave the auction locked to
        // a pool with no lots — so it refuses and says which of the two nothings it is.
        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.pool.reopen', [$auction, $pool]))
            ->assertStatus(422)
            ->assertJsonPath('message', 'All Gone has nobody to auction — every player in it was sold.');
    }

    #[Test]
    public function a_player_on_the_block_has_to_be_finished_first(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org);
        $operator = $this->makeAuctionOperator($org);

        $running = $this->makePool($auction, ['name' => 'Running']);
        $other = $this->makePool($auction, ['name' => 'Other']);

        $this->makeAuctionPlayer($auction, ['auction_pool_id' => $running->id, 'status' => 'on_auction']);
        $this->makeAuctionPlayer($auction, ['auction_pool_id' => $other->id, 'status' => 'waiting']);

        // Switching pools under a live player would strand them mid-bid.
        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.pool.reopen', [$auction, $other]))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Finish the player currently on the block first.');
    }

    #[Test]
    public function the_unsold_pile_is_not_offered_as_a_pool_to_run(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org);
        $operator = $this->makeAuctionOperator($org);

        $pool = $this->makePool($auction, ['name' => 'Pool A']);
        $player = $this->makeAuctionPlayer($auction, ['auction_pool_id' => $pool->id, 'status' => 'unsold']);
        app(\App\Services\Auction\AuctionPoolService::class)->moveToUnsoldPool($player);

        $pile = AuctionPool::where('auction_id', $auction->id)->where('is_unsold_pool', true)->sole();

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.pool.reopen', [$auction, $pile]))
            ->assertStatus(422);

        // It is also absent from the panel's pool list, which is biddable()-scoped — so the
        // chooser cannot offer it in the first place.
        $pools = $this->actingAs($operator)
            ->getJson(route('admin.auction.organizer.api.poll-state', $auction))
            ->assertOk()
            ->json('pools');

        $this->assertNotContains($pile->id, array_column($pools, 'id'));
    }

    #[Test]
    public function the_panel_is_told_how_many_a_pool_would_reclaim(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org);
        $operator = $this->makeAuctionOperator($org);
        $pools = app(\App\Services\Auction\AuctionPoolService::class);

        $pool = $this->makePool($auction, ['name' => 'Pool A']);

        foreach (range(1, 3) as $i) {
            $player = $this->makeAuctionPlayer($auction, ['auction_pool_id' => $pool->id, 'status' => 'unsold']);
            $pools->moveToUnsoldPool($player);
        }

        /*
         * Counted through source_pool_id, because an unsold player has been moved OUT of the pool
         * — `waiting` is zero and the pool looks empty. This is the figure the second confirmation
         * quotes, and the operator cannot see it from the strip.
         */
        $payload = $this->actingAs($operator)
            ->getJson(route('admin.auction.organizer.api.poll-state', $auction))
            ->assertOk()
            ->json('pools');

        $row = collect($payload)->firstWhere('id', $pool->id);

        $this->assertSame(0, $row['waiting']);
        $this->assertSame(3, $row['unsold_from']);
    }
}

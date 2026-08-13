<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\AuctionPool;
use App\Services\Auction\AuctionPoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Unsold players go to ONE pile for the auction, not one per pool they came from.
 *
 * The per-pool split read well on paper — "run allotment pool by pool" — and was wrong in the
 * room. Allotment asks which teams are short of a legal squad and which players are left, and
 * both are properties of the whole auction. Divided by origin, the screen showed several short
 * lists that had to be recombined in the operator's head before any of them could be acted on,
 * and a team's remaining slots had to be tracked across all of them.
 */
class UnsoldPlayersShareOnePoolTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    #[Test]
    public function players_unsold_from_different_pools_land_in_the_same_place(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org);
        $pools = app(AuctionPoolService::class);

        $poolA = $this->makePool($auction, ['name' => 'Pool A']);
        $poolB = $this->makePool($auction, ['name' => 'Pool B']);

        $fromA = $this->makeAuctionPlayer($auction, ['auction_pool_id' => $poolA->id, 'status' => 'unsold']);
        $fromB = $this->makeAuctionPlayer($auction, ['auction_pool_id' => $poolB->id, 'status' => 'unsold']);

        $pools->moveToUnsoldPool($fromA);
        $pools->moveToUnsoldPool($fromB);

        $unsoldPools = AuctionPool::where('auction_id', $auction->id)->where('is_unsold_pool', true)->get();

        $this->assertCount(1, $unsoldPools, 'One pile for the auction, whatever they came from.');
        $this->assertSame('Unsold', $unsoldPools->first()->name);
        // It belongs to the auction, not to any one pool.
        $this->assertNull($unsoldPools->first()->parent_pool_id);

        $this->assertSame($unsoldPools->first()->id, $fromA->fresh()->auction_pool_id);
        $this->assertSame($unsoldPools->first()->id, $fromB->fresh()->auction_pool_id);
    }

    #[Test]
    public function allotment_shows_one_list_rather_than_one_per_source_pool(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org);
        $pools = app(AuctionPoolService::class);

        foreach (['Pool A', 'Pool B', 'Pool C'] as $name) {
            $pool = $this->makePool($auction, ['name' => $name]);
            $pools->moveToUnsoldPool(
                $this->makeAuctionPlayer($auction, ['auction_pool_id' => $pool->id, 'status' => 'unsold'])
            );
        }

        $groups = $pools->allotmentGroups($auction->fresh());

        $this->assertCount(1, $groups);
        $this->assertCount(3, $groups->first()['players']);
    }

    #[Test]
    public function an_unsold_pool_does_not_nest_another_inside_itself(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org);
        $pools = app(AuctionPoolService::class);

        $pool = $this->makePool($auction, ['name' => 'Pool A']);
        $pools->moveToUnsoldPool(
            $this->makeAuctionPlayer($auction, ['auction_pool_id' => $pool->id, 'status' => 'unsold'])
        );

        $unsold = AuctionPool::where('auction_id', $auction->id)->where('is_unsold_pool', true)->sole();

        // A re-auctioned player who goes unsold again stays where they are.
        $again = $this->makeAuctionPlayer($auction, ['auction_pool_id' => $unsold->id, 'status' => 'unsold']);
        $pools->moveToUnsoldPool($again);

        $this->assertSame(1, AuctionPool::where('auction_id', $auction->id)->where('is_unsold_pool', true)->count());
        $this->assertSame($unsold->id, $again->fresh()->auction_pool_id);
    }

    #[Test]
    public function closing_a_pool_early_actually_sets_its_remaining_players_aside(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $operator = $this->makeAuctionOperator($org);

        $pool = $this->makePool($auction, ['name' => 'Pool 1']);

        $left = collect(range(1, 3))->map(fn ($i) => $this->makeAuctionPlayer($auction, [
            'auction_pool_id' => $pool->id,
            'status' => 'waiting',
            'lot_number' => $i,
        ]));

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.pool.activate', [$auction, $pool]))
            ->assertOk();

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.pool.complete', [$auction, $pool]))
            ->assertOk()
            ->assertJsonPath('unsold_count', 3);

        $unsold = AuctionPool::where('auction_id', $auction->id)->where('is_unsold_pool', true)->sole();

        /*
         * The confirm dialog has always said "N player(s) still in it will be left unsold" and
         * nothing carried it out: the pool was stamped completed and its players were left at
         * status `waiting` inside it — not auctioned, not unsold, absent from allotment, and
         * unreachable from the panel because their pool was closed.
         */
        foreach ($left as $player) {
            $player->refresh();
            $this->assertSame('unsold', $player->status);
            $this->assertSame($unsold->id, $player->auction_pool_id);
            $this->assertSame($pool->id, $player->source_pool_id);
        }

        // And they reach the screen that exists to place them.
        $this->actingAs($operator)
            ->get(route('admin.auctions.allotment', $auction))
            ->assertOk()
            ->assertSee('from Pool 1');
    }

    #[Test]
    public function a_re_auction_returns_players_closed_out_of_a_pool_to_it(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org);
        $operator = $this->makeAuctionOperator($org);

        $pool = $this->makePool($auction, ['name' => 'Pool 1']);
        $ap = $this->makeAuctionPlayer($auction, ['auction_pool_id' => $pool->id, 'status' => 'waiting', 'lot_number' => 1]);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.pool.activate', [$auction, $pool]))
            ->assertOk();
        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.pool.complete', [$auction, $pool]))
            ->assertOk();

        // The shared pile has no parent pool, so this only works because the origin travels
        // on the player. Without it every closed-out player would be stranded.
        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.start-reauction-round', $auction))
            ->assertOk()
            ->assertJsonPath('repooled_count', 1);

        $ap->refresh();
        $this->assertSame('waiting', $ap->status);
        $this->assertSame($pool->id, $ap->auction_pool_id);
        $this->assertNull($ap->source_pool_id);
        $this->assertNotNull($ap->lot_number);
    }

    #[Test]
    public function an_auction_that_already_had_several_keeps_using_the_first(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org);
        $pools = app(AuctionPoolService::class);

        $poolA = $this->makePool($auction, ['name' => 'Pool A']);
        $poolB = $this->makePool($auction, ['name' => 'Pool B']);

        // Two legacy per-pool piles, as an auction from before this change carries.
        $legacyA = $this->makePool($auction, ['name' => 'Pool A — Unsold', 'is_unsold_pool' => true, 'parent_pool_id' => $poolA->id]);
        $this->makePool($auction, ['name' => 'Pool B — Unsold', 'is_unsold_pool' => true, 'parent_pool_id' => $poolB->id]);

        // No third pool is opened alongside them; the oldest is adopted, and the migration
        // is what folds the other away.
        $resolved = $pools->unsoldPoolFor($poolB->fresh());

        $this->assertSame($legacyA->id, $resolved->id);
        $this->assertSame(2, AuctionPool::where('auction_id', $auction->id)->where('is_unsold_pool', true)->count());
    }
}

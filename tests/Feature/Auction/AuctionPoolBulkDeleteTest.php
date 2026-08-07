<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\AuctionPlayer;
use App\Models\AuctionPool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Deleting pools, one or many.
 *
 * The screen deletes over fetch so it keeps its scroll position and any inline edit forms
 * open, which means both endpoints have to answer JSON. The interesting part is not the
 * happy path but what must NOT happen: a running pool is the queue the control panel is
 * drawing from, and a partial bulk delete would leave an organizer unable to tell which
 * half went through.
 */
class AuctionPoolBulkDeleteTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function scenario(): array
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id, 'status' => 'running']);

        return [$org, $auction];
    }

    #[Test]
    public function several_pools_are_deleted_in_one_request(): void
    {
        [$org, $auction] = $this->scenario();
        $a = $this->makePool($auction, ['name' => 'Pool A', 'sequence' => 1]);
        $b = $this->makePool($auction, ['name' => 'Pool B', 'sequence' => 2]);
        $keep = $this->makePool($auction, ['name' => 'Pool C', 'sequence' => 3]);

        $this->actingAs($this->makeAuctionOperator($org))
            ->deleteJson(route('admin.auctions.pools.bulk-destroy', $auction), [
                'pool_ids' => [$a->id, $b->id],
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            // The client hides exactly what the server says it removed, never what it asked
            // for, so this list is load-bearing.
            ->assertJsonPath('deleted', [$a->id, $b->id]);

        $this->assertNull(AuctionPool::find($a->id));
        $this->assertNull(AuctionPool::find($b->id));
        $this->assertNotNull(AuctionPool::find($keep->id), 'an unselected pool must survive');
    }

    #[Test]
    public function a_running_pool_in_the_selection_blocks_the_whole_delete(): void
    {
        [$org, $auction] = $this->scenario();
        $ordinary = $this->makePool($auction, ['name' => 'Pool A', 'sequence' => 1]);
        $running = $this->makePool($auction, [
            'name' => 'Live Pool',
            'sequence' => 2,
            'status' => AuctionPool::STATUS_ACTIVE,
        ]);

        $this->actingAs($this->makeAuctionOperator($org))
            ->deleteJson(route('admin.auctions.pools.bulk-destroy', $auction), [
                'pool_ids' => [$ordinary->id, $running->id],
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        /*
         * All or nothing. Skipping the running pool and deleting the rest would report
         * "1 pool deleted" while the one that actually matters survived — and on this screen
         * that difference decides whether the live auction still has a queue.
         */
        $this->assertNotNull(AuctionPool::find($ordinary->id), 'nothing may be deleted when one is refused');
        $this->assertNotNull(AuctionPool::find($running->id));
    }

    #[Test]
    public function a_running_pool_cannot_be_deleted_on_its_own_either(): void
    {
        [$org, $auction] = $this->scenario();
        $running = $this->makePool($auction, ['status' => AuctionPool::STATUS_ACTIVE]);

        $this->actingAs($this->makeAuctionOperator($org))
            ->deleteJson(route('admin.auctions.pools.destroy', [$auction, $running]))
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertNotNull(AuctionPool::find($running->id));
    }

    #[Test]
    public function pools_from_another_auction_are_not_touched(): void
    {
        [$org, $auction] = $this->scenario();
        $mine = $this->makePool($auction, ['name' => 'Mine']);

        // This endpoint has no {pool} route binding, so nothing else scopes the ids.
        $otherTournament = $this->makeTournament($org);
        $otherAuction = $this->makeAuction($org, ['tournament_id' => $otherTournament->id, 'name' => 'Other Auction']);
        $theirs = $this->makePool($otherAuction, ['name' => 'Theirs']);

        $this->actingAs($this->makeAuctionOperator($org))
            ->deleteJson(route('admin.auctions.pools.bulk-destroy', $auction), [
                'pool_ids' => [$mine->id, $theirs->id],
            ])
            ->assertOk()
            ->assertJsonPath('deleted', [$mine->id]);

        $this->assertNull(AuctionPool::find($mine->id));
        $this->assertNotNull(AuctionPool::find($theirs->id), 'another auction\'s pool must be out of reach');
    }

    #[Test]
    public function waiting_players_are_released_but_actioned_ones_keep_their_result(): void
    {
        [$org, $auction] = $this->scenario();
        $pool = $this->makePool($auction);

        $waiting = $this->makeAuctionPlayer($auction, [
            'status' => 'waiting',
            'auction_pool_id' => $pool->id,
        ]);
        $sold = $this->makeAuctionPlayer($auction, [
            'status' => 'sold',
            'auction_pool_id' => $pool->id,
            'final_price' => 500,
        ]);

        $this->actingAs($this->makeAuctionOperator($org))
            ->deleteJson(route('admin.auctions.pools.bulk-destroy', $auction), [
                'pool_ids' => [$pool->id],
            ])
            ->assertOk();

        // Waiting rows go, so the player returns to the unassigned bucket.
        $this->assertNull(AuctionPlayer::find($waiting->id));

        // A completed sale is a result, not a pool membership — it survives, detached.
        $soldRow = AuctionPlayer::find($sold->id);
        $this->assertNotNull($soldRow, 'a sold player must not be deleted with their pool');
        $this->assertNull($soldRow->auction_pool_id);
        $this->assertSame('sold', $soldRow->status);
    }

    #[Test]
    public function several_players_are_removed_from_their_pool_in_one_request(): void
    {
        [$org, $auction] = $this->scenario();
        $pool = $this->makePool($auction);

        $a = $this->makeAuctionPlayer($auction, ['status' => 'waiting', 'auction_pool_id' => $pool->id, 'lot_number' => 1]);
        $b = $this->makeAuctionPlayer($auction, ['status' => 'waiting', 'auction_pool_id' => $pool->id, 'lot_number' => 2]);
        $keep = $this->makeAuctionPlayer($auction, ['status' => 'waiting', 'auction_pool_id' => $pool->id, 'lot_number' => 3]);

        $this->actingAs($this->makeAuctionOperator($org))
            ->postJson(route('admin.auctions.pools.bulk-unassign', $auction), [
                'player_ids' => [$a->player_id, $b->player_id],
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('affected', [$a->player_id, $b->player_id]);

        $this->assertNull(AuctionPlayer::find($a->id));
        $this->assertNull(AuctionPlayer::find($b->id));

        // Lot numbers are positional, so the survivor is redrawn rather than left at 3
        // with two gaps in front of it.
        $this->assertSame(1, AuctionPlayer::find($keep->id)->lot_number);
    }

    #[Test]
    public function a_player_already_in_play_blocks_the_whole_removal(): void
    {
        [$org, $auction] = $this->scenario();
        $pool = $this->makePool($auction);

        $waiting = $this->makeAuctionPlayer($auction, ['status' => 'waiting', 'auction_pool_id' => $pool->id]);
        $onBlock = $this->makeAuctionPlayer($auction, ['status' => 'on_auction', 'auction_pool_id' => $pool->id]);

        $this->actingAs($this->makeAuctionOperator($org))
            ->postJson(route('admin.auctions.pools.bulk-unassign', $auction), [
                'player_ids' => [$waiting->player_id, $onBlock->player_id],
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        // Reporting "1 removed" while the player under the hammer stayed would be a
        // quietly wrong answer to the only question being asked.
        $this->assertNotNull(AuctionPlayer::find($waiting->id));
        $this->assertNotNull(AuctionPlayer::find($onBlock->id));
    }

    #[Test]
    public function removing_a_single_player_answers_json_for_the_screen(): void
    {
        [$org, $auction] = $this->scenario();
        $pool = $this->makePool($auction);
        $ap = $this->makeAuctionPlayer($auction, ['status' => 'waiting', 'auction_pool_id' => $pool->id]);

        // The old endpoint only ever redirected, so the screen had to reload to remove one
        // player and lost its scroll position each time.
        $this->actingAs($this->makeAuctionOperator($org))
            ->postJson(route('admin.auctions.pools.unassign', $auction), ['player_id' => $ap->player_id])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('affected', [$ap->player_id]);

        $this->assertNull(AuctionPlayer::find($ap->id));
    }

    #[Test]
    public function players_from_another_auction_are_not_removed(): void
    {
        [$org, $auction] = $this->scenario();
        $otherAuction = $this->makeAuction($org, [
            'tournament_id' => $this->makeTournament($org)->id,
            'name' => 'Other Auction',
        ]);
        $theirPool = $this->makePool($otherAuction);
        $theirs = $this->makeAuctionPlayer($otherAuction, ['status' => 'waiting', 'auction_pool_id' => $theirPool->id]);

        $this->actingAs($this->makeAuctionOperator($org))
            ->postJson(route('admin.auctions.pools.bulk-unassign', $auction), [
                'player_ids' => [$theirs->player_id],
            ])
            ->assertStatus(422);

        $this->assertNotNull(AuctionPlayer::find($theirs->id));
    }

    #[Test]
    public function an_empty_selection_is_rejected(): void
    {
        [$org, $auction] = $this->scenario();

        $this->actingAs($this->makeAuctionOperator($org))
            ->deleteJson(route('admin.auctions.pools.bulk-destroy', $auction), ['pool_ids' => []])
            ->assertStatus(422);
    }

    #[Test]
    public function the_pools_screen_renders_with_selection_controls_and_no_native_confirm(): void
    {
        [$org, $auction] = $this->scenario();
        $this->makePool($auction, ['name' => 'Pool A', 'sequence' => 1]);
        $running = $this->makePool($auction, ['name' => 'Live Pool', 'sequence' => 2, 'status' => AuctionPool::STATUS_ACTIVE]);

        // Rendering is the check that matters here: `view:cache` compiles a Blade file
        // without executing it, so a template that compiles to invalid PHP only fails when
        // something actually renders it.
        $response = $this->actingAs($this->makeAuctionOperator($org))
            ->get(route('admin.auctions.pools.index', $auction))
            ->assertOk();

        $response->assertSee('poolManager(', false)
            ->assertSee('data-pool-select', false)
            ->assertSee('confirmBulkDelete()', false);

        // Native dialogs cannot show the list of pools or the count of players about to be
        // released, which on a bulk delete is the whole point of confirming.
        $html = $response->getContent();
        $this->assertSame(
            0,
            preg_match_all('/(?<![A-Za-z0-9_$.])(?:window\.)?(alert|confirm)\s*\(/', (string) $html),
            'the pools screen must confirm in-page, not with a native browser dialog'
        );

        // The running pool gets no checkbox: offering one and then refusing the delete
        // wastes the organizer's time mid-auction.
        $this->assertStringNotContainsString('data-pool-id="' . $running->id . '"', (string) $html);
    }

    #[Test]
    public function deleting_pools_needs_auction_edit(): void
    {
        [$org, $auction] = $this->scenario();
        $pool = $this->makePool($auction);

        // auction.view alone is a Team Manager's level of access.
        $viewer = $this->makeAuctionOperator($org, ['auction.view']);

        $this->actingAs($viewer)
            ->deleteJson(route('admin.auctions.pools.bulk-destroy', $auction), ['pool_ids' => [$pool->id]])
            ->assertForbidden();

        $this->assertNotNull(AuctionPool::find($pool->id));
    }
}

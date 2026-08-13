<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\AuctionPool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * An ended auction had exactly one way back, and it was the nuclear one.
 *
 * The Restart button fell through to `restartAuction`, which resets every player and every bid
 * in every pool. That is almost never what a finished auction needs: the usual reason to come
 * back is one pool that wants running again, or one that was closed early and should now be
 * revisited. Nuking three finished pools to redo the fourth is not a recovery, it is a second
 * auction.
 */
class RestartAfterAuctionEndsTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    #[Test]
    public function the_panel_offers_a_pool_to_restart_rather_than_only_everything(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, ['status' => 'completed']);
        $operator = $this->makeAuctionOperator($org);
        $this->makePool($auction, ['name' => 'Pool A']);

        $html = $this->actingAs($operator)
            ->get(route('admin.auction.organizer.panel', $auction))
            ->assertOk()
            ->getContent();

        // The chooser is reached only from the ended state; a running auction keeps the
        // controls it has always had.
        $this->assertStringContainsString('restartAfterEnd()', $html);
        $this->assertStringContainsString("auctionStatus === 'completed' ? restartAfterEnd()", html_entity_decode($html));

        // Resetting the lot is still offered — last, and named for what it does.
        $this->assertStringContainsString('Reset the entire auction', $html);
    }

    #[Test]
    public function an_ended_auction_can_carry_on_with_a_pool_that_still_has_players(): void
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
            'sold_to_team_id' => $team->id, 'final_price' => 4_000,
        ]);
        $waiting = $this->makeAuctionPlayer($auction, [
            'auction_pool_id' => $pool->id, 'status' => 'waiting', 'lot_number' => 2,
        ]);

        $auction->update(['status' => 'completed']);

        /*
         * "Carry on" rather than "restart". Ending an auction with players still in a pool is
         * ordinary — a break, a room that has to be cleared — and the way back should not cost
         * every sale already made.
         */
        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.pool.reopen', [$auction, $pool]))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('running', $auction->fresh()->status);
        $this->assertSame('waiting', $waiting->fresh()->status);
        // The sale stands.
        $this->assertSame($team->id, $sold->fresh()->sold_to_team_id);
    }

    #[Test]
    public function the_panel_offers_carrying_on_before_it_offers_a_reset(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, ['status' => 'completed']);
        $this->makePool($auction, ['name' => 'Pool A']);

        $html = $this->actingAs($this->makeAuctionOperator($org))
            ->get(route('admin.auction.organizer.panel', $auction))
            ->assertOk()
            ->getContent();

        // Order matters: the non-destructive option has to be the one read first.
        $carryOn = strpos($html, 'Carry on with ');
        $fromScratch = strpos($html, 'again from scratch');
        $resetAll = strpos($html, 'Reset the entire auction');

        $this->assertNotFalse($carryOn);
        $this->assertNotFalse($fromScratch);
        $this->assertLessThan($fromScratch, $carryOn);
        $this->assertLessThan($resetAll, $fromScratch);
    }

    #[Test]
    public function restarting_one_pool_of_an_ended_auction_leaves_the_others_alone(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 100_000_000,
        ]);
        $operator = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'Alpha', $tournament);

        $poolA = $this->makePool($auction, ['name' => 'Pool A', 'sequence' => 1]);
        $poolB = $this->makePool($auction, ['name' => 'Pool B', 'sequence' => 2]);

        $inA = $this->makeAuctionPlayer($auction, [
            'auction_pool_id' => $poolA->id, 'status' => 'sold',
            'sold_to_team_id' => $team->id, 'final_price' => 500,
        ]);
        $inB = $this->makeAuctionPlayer($auction, [
            'auction_pool_id' => $poolB->id, 'status' => 'sold',
            'sold_to_team_id' => $team->id, 'final_price' => 700,
        ]);

        $auction->update(['status' => 'completed']);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.pool.restart', [$auction, $poolA]))
            ->assertOk()
            ->assertJsonPath('success', true);

        // Pool A comes back...
        $this->assertSame('waiting', $inA->fresh()->status);
        $this->assertNull($inA->fresh()->sold_to_team_id);

        // ...and Pool B keeps its result, which is the whole reason not to reset everything.
        $this->assertSame('sold', $inB->fresh()->status);
        $this->assertSame($team->id, $inB->fresh()->sold_to_team_id);

        // The auction is live again and the pool is the one running, both settled server-side
        // so the panel does not have to hold a second opinion about it.
        $this->assertSame('running', $auction->fresh()->status);
        $this->assertSame(AuctionPool::STATUS_ACTIVE, $poolA->fresh()->status);
    }

    #[Test]
    public function a_completed_auction_is_a_state_the_endpoint_accepts(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, ['status' => 'completed']);
        $operator = $this->makeAuctionOperator($org);
        $pool = $this->makePool($auction, ['name' => 'Pool A']);
        $this->makeAuctionPlayer($auction, ['auction_pool_id' => $pool->id, 'status' => 'unsold']);

        // Restarting a pool on an ended auction needs no separate resume — this endpoint
        // brings the auction back to running itself, in the same transaction.
        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.pool.restart', [$auction, $pool]))
            ->assertOk();

        $this->assertSame('running', $auction->fresh()->status);
    }
}

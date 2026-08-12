<?php

namespace Tests\Feature\Auction;

use App\Models\Auction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * The bid timer used to be a purely decorative client-side countdown: nothing was
 * enforced and nothing happened at zero. It is now stamped and checked server-side.
 */
class AuctionTimerTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function putting_a_player_on_the_block_starts_the_clock(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, ['bid_timer_seconds' => 30]);
        $operator = $this->makeAuctionOperator($org);
        $ap = $this->makeAuctionPlayer($auction);

        $this->assertNull($auction->timer_started_at);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.player.onbid', $auction), ['auction_player_id' => $ap->id])
            ->assertOk();

        $auction->refresh();
        $this->assertNotNull($auction->timer_started_at);
        $this->assertSame(30, $auction->timerSecondsRemaining());
    }

    /**
     * The clock binds the room, not the person running it.
     *
     * This used to assert that the operator's own bid was refused after expiry, on the
     * reasoning that their buttons should obey the same rule the teams do. In a hall that is
     * backwards: the countdown exists to stop teams stalling, and when it runs out the
     * organizer is the one who has to record what the room already heard — a raise called a
     * second before the hammer, a correction, a bid from the floor. Refusing them left the
     * only person who could put it right unable to act, with a player stuck on the block.
     *
     * The lock did not go away, it moved: a TEAM's own bid after expiry is still refused
     * (AuctionBiddingController::placeBid), as is a late sealed submission. See
     * PoolRestartChoicesTest for both sides of that.
     */
    #[Test]
    public function the_operator_may_still_bid_after_the_clock_runs_out(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, ['bid_timer_seconds' => 30, 'max_budget_per_team' => 100000]);
        $operator = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'A');
        $ap = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);

        Carbon::setTestNow(now());
        $auction->update(['timer_started_at' => now()]);

        // Within the window: accepted.
        Carbon::setTestNow(now()->addSeconds(10));
        $this->actingAs($operator)
            ->postJson(route('admin.auctions.players.addBid'), [
                'auctionId' => $auction->id, 'playerID' => $ap->id, 'teamId' => $team->id,
            ])->assertOk();

        // That bid reset the clock, so wind past the shorter reset window.
        Carbon::setTestNow(now()->addSeconds(120));

        $teamB = $this->makeTeam($org, 'B');
        $this->actingAs($operator)
            ->postJson(route('admin.auctions.players.addBid'), [
                'auctionId' => $auction->id, 'playerID' => $ap->id, 'teamId' => $teamB->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame($teamB->id, $ap->fresh()->current_bid_team_id);
    }

    #[Test]
    public function a_successful_bid_restarts_the_clock_at_the_reset_window(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, [
            'bid_timer_seconds' => 30,
            'bid_timer_reset_seconds' => 15,
            'max_budget_per_team' => 100000,
        ]);
        $operator = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'A');
        $ap = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);

        Carbon::setTestNow(now());
        $auction->update(['timer_started_at' => now()->subSeconds(20)]);

        // 20s elapsed of a 30s window — still live.
        $this->actingAs($operator)
            ->postJson(route('admin.auctions.players.addBid'), [
                'auctionId' => $auction->id, 'playerID' => $ap->id, 'teamId' => $team->id,
            ])->assertOk();

        // Now the player has a bid, so the shorter reset window applies.
        $auction->refresh();
        $ap->refresh();
        $this->assertSame(15, $auction->timerStateFor($ap)['limit']);
        $this->assertSame(15, $auction->timerStateFor($ap)['remaining']);
    }

    #[Test]
    public function expiry_in_auto_sell_mode_awards_the_highest_bidder(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, [
            'max_budget_per_team' => 100000,
            'timer_expiry_action' => Auction::TIMER_AUTO_SELL,
            'bid_timer_seconds' => 30,
        ]);
        $operator = $this->makeAuctionOperator($org);
        $teamA = $this->makeTeam($org, 'A');
        $teamB = $this->makeTeam($org, 'B');
        $ap = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);

        $this->makeBid($ap, $teamA, 500, $operator);
        $this->makeBid($ap, $teamB, 900, $operator);
        $ap->update(['current_price' => 900, 'current_bid_team_id' => $teamB->id]);

        Carbon::setTestNow(now());
        $auction->update(['timer_started_at' => now()->subSeconds(120)]);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.timer-expired', $auction), ['auction_player_id' => $ap->id])
            ->assertOk()
            ->assertJsonPath('handled', true)
            ->assertJsonPath('action', 'sold');

        $ap->refresh();
        $this->assertSame('sold', $ap->status);
        $this->assertSame($teamB->id, $ap->sold_to_team_id);
    }

    #[Test]
    public function expiry_in_auto_sell_mode_marks_a_bidless_player_unsold(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, ['timer_expiry_action' => Auction::TIMER_AUTO_SELL]);
        $operator = $this->makeAuctionOperator($org);
        $ap = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);

        Carbon::setTestNow(now());
        $auction->update(['timer_started_at' => now()->subSeconds(120)]);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.timer-expired', $auction), ['auction_player_id' => $ap->id])
            ->assertOk()
            ->assertJsonPath('action', 'unsold');

        $this->assertSame('unsold', $ap->fresh()->status);
    }

    #[Test]
    public function expiry_in_manual_mode_locks_bidding_but_does_not_sell(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, [
            'max_budget_per_team' => 100000,
            'timer_expiry_action' => Auction::TIMER_MANUAL,
        ]);
        $operator = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'A');
        $ap = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);
        $this->makeBid($ap, $team, 500, $operator);
        $ap->update(['current_price' => 500, 'current_bid_team_id' => $team->id]);

        Carbon::setTestNow(now());
        $auction->update(['timer_started_at' => now()->subSeconds(120)]);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.timer-expired', $auction), ['auction_player_id' => $ap->id])
            ->assertOk()
            ->assertJsonPath('action', 'locked');

        // The organizer still has to press SELL.
        $this->assertSame('on_auction', $ap->fresh()->status);
    }

    #[Test]
    public function timer_expiry_is_idempotent_across_concurrent_panels(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, [
            'max_budget_per_team' => 100000,
            'timer_expiry_action' => Auction::TIMER_AUTO_SELL,
        ]);
        $operator = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'A');
        $ap = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);
        $this->makeBid($ap, $team, 500, $operator);
        $ap->update(['current_price' => 500, 'current_bid_team_id' => $team->id]);

        Carbon::setTestNow(now());
        $auction->update(['timer_started_at' => now()->subSeconds(120)]);

        // Two panels fire at once; only the first does anything.
        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.timer-expired', $auction), ['auction_player_id' => $ap->id])
            ->assertOk()->assertJsonPath('handled', true);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.timer-expired', $auction), ['auction_player_id' => $ap->id])
            ->assertOk()->assertJsonPath('handled', false);

        // Exactly one sale.
        $this->assertSame(1, $auction->auctionPlayers()->where('status', 'sold')->count());
    }

    #[Test]
    public function expiry_reported_early_is_refused(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, [
            'timer_expiry_action' => Auction::TIMER_AUTO_SELL,
            'bid_timer_seconds' => 30,
        ]);
        $operator = $this->makeAuctionOperator($org);
        $ap = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);

        Carbon::setTestNow(now());
        $auction->update(['timer_started_at' => now()->subSeconds(5)]);

        // A tampered or slow client claiming expiry gets nowhere — the server clock decides.
        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.timer-expired', $auction), ['auction_player_id' => $ap->id])
            ->assertOk()
            ->assertJsonPath('handled', false);

        $this->assertSame('on_auction', $ap->fresh()->status);
    }

    #[Test]
    public function a_re_bid_restarts_the_clock(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, [
            'max_budget_per_team' => 100000,
            'bid_timer_seconds' => 30,
        ]);
        $operator = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'A');
        $ap = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);
        $this->makeBid($ap, $team, 500, $operator);
        $ap->update(['current_price' => 500, 'current_bid_team_id' => $team->id]);

        Carbon::setTestNow(now());
        // Clock long expired from the previous round.
        $auction->update(['timer_started_at' => now()->subSeconds(300)]);
        $this->assertTrue($auction->fresh()->timerStateFor($ap->fresh())['expired']);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.player.re-bid', $auction), ['auction_player_id' => $ap->id])
            ->assertOk();

        // Fresh clock, so the player is genuinely back up for bidding rather than
        // instantly locked (or auto-sold) on an inherited expired timer.
        $auction->refresh();
        $ap->refresh();
        $this->assertFalse($auction->timerStateFor($ap)['expired']);
        $this->assertSame(30, $auction->timerStateFor($ap)['remaining']);
    }

    #[Test]
    public function a_re_auction_restarts_the_clock(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, ['bid_timer_seconds' => 30]);
        $operator = $this->makeAuctionOperator($org);
        $ap = $this->makeAuctionPlayer($auction, ['status' => 'unsold']);

        Carbon::setTestNow(now());
        $auction->update(['timer_started_at' => now()->subSeconds(300)]);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.player.re-auction', $auction), ['auction_player_id' => $ap->id])
            ->assertOk();

        $auction->refresh();
        $this->assertSame('on_auction', $ap->fresh()->status);
        $this->assertSame(30, $auction->timerStateFor($ap->fresh())['remaining']);
    }

    #[Test]
    public function a_re_bid_with_the_timer_off_simply_shows_the_player(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, [
            'open_bid_mode' => 'offline',
            'timer_enabled' => false,
        ]);
        $operator = $this->makeAuctionOperator($org);
        $ap = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.player.re-bid', $auction), ['auction_player_id' => $ap->id])
            ->assertOk();

        // No clock to run, so nothing can expire and bidding stays open.
        $auction->refresh();
        $this->assertNull($auction->timerSecondsRemaining());
        $this->assertFalse($auction->timerHasExpired());
        $this->assertSame('on_auction', $ap->fresh()->status);
    }

    #[Test]
    public function the_timer_cannot_be_turned_off_while_bidding_is_online(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, ['open_bid_mode' => 'online']);
        $operator = $this->makeAuctionOperator($org);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.toggle-timer', $auction), ['timer_enabled' => false])
            ->assertStatus(422);

        $this->assertTrue($auction->fresh()->timerApplies());
    }

    #[Test]
    public function the_timer_is_optional_in_offline_mode(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, ['open_bid_mode' => 'offline']);
        $operator = $this->makeAuctionOperator($org);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.toggle-timer', $auction), ['timer_enabled' => false])
            ->assertOk()
            ->assertJsonPath('timer_enabled', false);

        $auction->refresh();
        $this->assertFalse($auction->timerApplies());
        // With no clock there is nothing to expire, so bidding stays open.
        $this->assertNull($auction->timerSecondsRemaining());
    }
}

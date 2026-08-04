<?php

namespace Tests\Feature\Auction;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Closing calls: in the last seconds the display escalates FIRST → SECOND → FINAL at a
 * fixed gap, then the configured expiry action resolves the player.
 *
 * The thresholds are computed server-side and shipped with every payload so that the
 * organizer panel, offline panel, bidding page and the public LED wall all escalate on
 * the same second — none of them re-implements the rule.
 */
class AuctionFinalCallTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function three_calls_are_spaced_by_the_configured_interval(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, ['final_call_interval_seconds' => 3]);

        // Final closest to zero, so the first match walking the list is the most
        // advanced call reached.
        $this->assertSame(
            [['at' => 3, 'stage' => 3], ['at' => 6, 'stage' => 2], ['at' => 9, 'stage' => 1]],
            array_map(fn ($s) => ['at' => $s['at'], 'stage' => $s['stage']], $auction->finalCallStages())
        );
    }

    #[Test]
    public function the_call_escalates_as_the_clock_runs_down(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, ['final_call_interval_seconds' => 3]);

        // Outside the closing window.
        $this->assertNull($auction->finalCallFor(30));
        $this->assertNull($auction->finalCallFor(10));

        $this->assertSame('FIRST CALL', $auction->finalCallFor(9)['label']);
        $this->assertSame('FIRST CALL', $auction->finalCallFor(7)['label']);
        $this->assertSame('SECOND CALL', $auction->finalCallFor(6)['label']);
        $this->assertSame('SECOND CALL', $auction->finalCallFor(4)['label']);
        $this->assertSame('FINAL CALL', $auction->finalCallFor(3)['label']);
        $this->assertSame('FINAL CALL', $auction->finalCallFor(0)['label']);

        $this->assertTrue($auction->finalCallFor(1)['is_final']);
        $this->assertFalse($auction->finalCallFor(9)['is_final']);
    }

    #[Test]
    public function a_custom_interval_moves_the_thresholds(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, ['final_call_interval_seconds' => 5]);

        // Calls at 15s, 10s, 5s.
        $this->assertNull($auction->finalCallFor(16));
        $this->assertSame(1, $auction->finalCallFor(15)['stage']);
        $this->assertSame(2, $auction->finalCallFor(10)['stage']);
        $this->assertSame(3, $auction->finalCallFor(5)['stage']);
    }

    #[Test]
    public function calls_can_be_switched_off(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, ['final_call_enabled' => false]);

        $this->assertSame([], $auction->finalCallStages());
        $this->assertNull($auction->finalCallFor(2));
    }

    #[Test]
    public function no_call_is_reported_when_the_timer_is_off(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, [
            'open_bid_mode' => 'offline',
            'timer_enabled' => false,
            'final_call_enabled' => true,
        ]);

        // No clock means nothing to count down to.
        $this->assertSame([], $auction->finalCallStages());
        $this->assertNull($auction->finalCallFor(2));
    }

    #[Test]
    public function the_organizer_poll_reports_the_current_call(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, [
            'bid_timer_seconds' => 30,
            'final_call_interval_seconds' => 3,
        ]);
        $operator = $this->makeAuctionOperator($org);
        $ap = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);

        Carbon::setTestNow(now());
        // 25s into a 30s clock → 5s left → SECOND CALL.
        $auction->update(['timer_started_at' => now()->subSeconds(25)]);

        $this->actingAs($operator)
            ->getJson(route('admin.auction.organizer.api.poll-state', $auction))
            ->assertOk()
            ->assertJsonPath('timer_seconds_remaining', 5)
            ->assertJsonPath('final_call.label', 'SECOND CALL')
            ->assertJsonPath('final_call.stage', 2)
            // Thresholds travel with the payload for the client-side tick.
            ->assertJsonCount(3, 'final_call_stages');
    }

    #[Test]
    public function the_public_display_feed_reports_the_same_call(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, [
            'bid_timer_seconds' => 30,
            'final_call_interval_seconds' => 3,
        ]);
        $ap = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);

        Carbon::setTestNow(now());
        $auction->update(['timer_started_at' => now()->subSeconds(28)]);

        // Unauthenticated audience display — same figures as the panel.
        $this->getJson("/auction/{$auction->id}/active-player")
            ->assertOk()
            ->assertJsonPath('timer_seconds_remaining', 2)
            ->assertJsonPath('final_call.label', 'FINAL CALL')
            ->assertJsonPath('timer_enabled', true);
    }

    #[Test]
    public function the_public_feed_no_longer_leaks_the_bid_rules(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org);
        $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);

        // bid_rules revealed the sealed-bid ceiling to anyone who opened the display.
        $this->getJson("/auction/{$auction->id}/active-player")
            ->assertOk()
            ->assertJsonMissingPath('bid_rules');
    }

    #[Test]
    public function a_new_bid_restarts_the_clock_and_clears_the_call(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, [
            'max_budget_per_team' => 100000,
            'bid_timer_seconds' => 30,
            'bid_timer_reset_seconds' => 15,
            'final_call_interval_seconds' => 3,
        ]);
        $operator = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'A');
        $ap = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);

        Carbon::setTestNow(now());
        // Down to 2s: FINAL CALL is showing.
        $auction->update(['timer_started_at' => now()->subSeconds(28)]);
        $this->assertSame('FINAL CALL', $auction->fresh()->finalCallFor(2)['label']);

        // A bid lands in the nick of time.
        $this->actingAs($operator)
            ->postJson(route('admin.auctions.players.addBid'), [
                'auctionId' => $auction->id, 'playerID' => $ap->id, 'teamId' => $team->id,
            ])->assertOk();

        // Clock restarts at the reset window, so no call is in force any more.
        $state = $this->actingAs($operator)
            ->getJson(route('admin.auction.organizer.api.poll-state', $auction))
            ->assertOk()
            ->json();

        $this->assertSame(15, $state['timer_seconds_remaining']);
        $this->assertNull($state['final_call']);
    }

    #[Test]
    public function the_final_call_runs_into_an_automatic_sale(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, [
            'max_budget_per_team' => 100000,
            'timer_expiry_action' => \App\Models\Auction::TIMER_AUTO_SELL,
            'bid_timer_seconds' => 30,
            'final_call_interval_seconds' => 3,
        ]);
        $operator = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'A');
        $ap = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);
        $this->makeBid($ap, $team, 750, $operator);
        $ap->update(['current_price' => 750, 'current_bid_team_id' => $team->id]);

        Carbon::setTestNow(now());

        // FINAL CALL is in force at 2s remaining...
        $auction->update(['timer_started_at' => now()->subSeconds(13)]);
        $this->assertSame('FINAL CALL', $auction->fresh()->finalCallFor(2)['label']);

        // ...and once the clock runs out, the player is allotted automatically.
        $auction->update(['timer_started_at' => now()->subSeconds(60)]);
        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.timer-expired', $auction), ['auction_player_id' => $ap->id])
            ->assertOk()
            ->assertJsonPath('action', 'sold');

        $ap->refresh();
        $this->assertSame('sold', $ap->status);
        $this->assertSame($team->id, $ap->sold_to_team_id);
    }
}

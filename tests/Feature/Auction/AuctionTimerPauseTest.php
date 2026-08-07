<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Pausing must stop the bid clock, not just the label on it.
 *
 * The countdown is wall-clock arithmetic against `timer_started_at`, and pausing only ever
 * flipped `auctions.status`. So the clock kept running through a pause: hold a 30-second
 * timer for a minute and the player came back already expired — and with
 * `timer_expiry_action = auto_sell` they would be sold the instant the room resumed.
 *
 * The same frozen figure now reaches the panel, the LED wall and the ticker, so the operator,
 * the hall and the stream cannot disagree about how long is left.
 */
class AuctionTimerPauseTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function runningAuction(array $overrides = [])
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);

        $auction = $this->makeAuction($org, array_merge([
            'tournament_id' => $tournament->id,
            'status' => 'running',
            'bid_timer_seconds' => 30,
            'timer_enabled' => true,
            'timer_started_at' => now(),
        ], $overrides));

        return [$org, $auction];
    }

    #[Test]
    public function a_pause_does_not_eat_the_players_remaining_seconds(): void
    {
        [$org, $auction] = $this->runningAuction();

        // 10 seconds gone, 20 left.
        $this->travel(10)->seconds();
        $this->assertSame(20, $auction->timerSecondsRemaining());

        $this->actingAs($this->makeAuctionOperator($org))
            ->postJson(route('admin.auction.organizer.api.toggle-pause', $auction))
            ->assertOk();

        $auction->refresh();
        $this->assertSame('paused', $auction->status);
        $this->assertTrue($auction->timerIsPaused());

        // A long pause. Before this, the clock ran straight through it.
        $this->travel(120)->seconds();

        $this->assertSame(20, $auction->fresh()->timerSecondsRemaining(), 'a frozen clock must not move');
        $this->assertFalse($auction->fresh()->timerHasExpired(), 'a frozen clock cannot expire');

        // Resume: the same 20 seconds are handed back.
        $this->actingAs($this->makeAuctionOperator($org))
            ->postJson(route('admin.auction.organizer.api.toggle-pause', $auction))
            ->assertOk();

        $auction->refresh();
        $this->assertSame('running', $auction->status);
        $this->assertFalse($auction->timerIsPaused());
        $this->assertSame(20, $auction->timerSecondsRemaining());
    }

    #[Test]
    public function a_paused_clock_at_one_second_does_not_auto_sell_on_resume(): void
    {
        // The case that makes this a correctness bug rather than a cosmetic one.
        [$org, $auction] = $this->runningAuction(['timer_expiry_action' => 'auto_sell']);

        $this->travel(29)->seconds();
        $this->assertSame(1, $auction->timerSecondsRemaining());

        $this->actingAs($this->makeAuctionOperator($org))
            ->postJson(route('admin.auction.organizer.api.toggle-pause', $auction))
            ->assertOk();

        $this->travel(300)->seconds();

        $this->assertFalse(
            $auction->fresh()->timerHasExpired(),
            'five minutes paused must not expire a clock with a second left on it'
        );
    }

    #[Test]
    public function pausing_twice_does_not_move_the_mark(): void
    {
        [$org, $auction] = $this->runningAuction();
        $this->travel(10)->seconds();

        $auction->pauseTimer();
        $mark = $auction->fresh()->timer_paused_at;

        $this->travel(45)->seconds();
        $auction->fresh()->pauseTimer();

        $this->assertEquals($mark, $auction->fresh()->timer_paused_at, 'a second pause must be a no-op');
        $this->assertSame(20, $auction->fresh()->timerSecondsRemaining());
    }

    #[Test]
    public function every_screen_is_told_the_clock_is_frozen(): void
    {
        [$org, $auction] = $this->runningAuction();
        $player = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);
        $operator = $this->makeAuctionOperator($org);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.toggle-pause', $auction))
            ->assertOk();

        // The organizer's panel...
        $this->actingAs($operator)
            ->getJson(route('admin.auction.organizer.api.poll-state', $auction))
            ->assertOk()
            ->assertJsonPath('timer_paused', true);

        // ...the LED wall...
        $this->getJson("/auction/{$auction->id}/active-player")
            ->assertOk()
            ->assertJsonPath('timer_paused', true);

        // ...and the broadcast ticker. One flag, three screens — otherwise each guesses
        // from `status` and they drift apart in front of an audience.
        $this->getJson("/auction/{$auction->id}/ticker-feed")
            ->assertOk()
            ->assertJsonPath('timer.paused', true);
    }

    #[Test]
    public function resuming_an_auction_that_was_never_timing_is_harmless(): void
    {
        // No player up, so no clock was ever started.
        [$org, $auction] = $this->runningAuction(['timer_started_at' => null]);

        $auction->pauseTimer();
        $this->assertFalse($auction->fresh()->timerIsPaused(), 'nothing to freeze');

        $auction->resumeTimer();
        $this->assertNull($auction->fresh()->timer_started_at);
        $this->assertNull($auction->fresh()->timerSecondsRemaining());
    }

    #[Test]
    public function the_clock_does_not_run_when_nobody_is_on_the_block(): void
    {
        [$org, $auction] = $this->runningAuction(['timer_started_at' => now()->subSeconds(45)]);

        /*
         * The clock was only ever cleared by a full restart, so after a sale it counted on
         * through the gap to the next player. With nobody up, timerHasExpired() returned
         * TRUE — and with timer_expiry_action = auto_sell that is the last thing that should
         * be true while the organizer is deciding who to auction next.
         */
        $state = $auction->timerStateFor(null);

        $this->assertFalse($state['applies'], 'no player on the block means no clock');
        $this->assertNull($state['remaining']);
        $this->assertFalse($state['expired']);
        $this->assertNull($state['final_call'], 'and certainly no closing call');
    }

    #[Test]
    public function a_player_who_has_left_the_block_carries_no_clock(): void
    {
        [$org, $auction] = $this->runningAuction(['timer_started_at' => now()->subSeconds(45)]);
        $sold = $this->makeAuctionPlayer($auction, ['status' => 'sold']);

        // Guarded on the player's status, not merely on being handed a player: the wall's
        // "last action" payload hands over a sold player to keep the card on screen.
        $this->assertFalse($auction->timerStateFor($sold)['applies']);
    }

    #[Test]
    public function selling_stops_the_clock_so_the_next_player_starts_fresh(): void
    {
        [$org, $auction] = $this->runningAuction();
        $tournament = $auction->tournament;
        $team = $this->makeTeam($org, 'Alpha', $tournament);
        $player = $this->makeAuctionPlayer($auction, ['status' => 'on_auction', 'current_price' => 500]);

        $this->actingAs($this->makeAuctionOperator($org))
            ->postJson(route('admin.auction.organizer.api.player.sell-to-team', $auction), [
                'auction_player_id' => $player->id,
                'team_id' => $team->id,
                'amount' => 500,
            ]);

        $this->assertNull($auction->fresh()->timer_started_at, 'the clock stops with the player');
    }
}

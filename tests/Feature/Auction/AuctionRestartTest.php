<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\Auction;
use App\Models\AuctionClosedBidRound;
use App\Services\Auction\ClosedBidService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Restarting an auction.
 *
 * A restart wipes the run, so it must not leave a player looking unsold, must not leave a
 * clock counting down over the waiting screen, and must announce itself on the big screen
 * rather than blanking without explanation.
 */
class AuctionRestartTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function scenario(): array
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'status' => 'running',
            'max_budget_per_team' => 100_000_000,
            'closed_bid_starts_at' => 8_000_000,
            'timer_started_at' => now(),
        ]);
        $team = $this->makeTeam($org, 'Alpha', $tournament);

        return compact('org', 'tournament', 'auction', 'team');
    }

    #[Test]
    public function a_restart_returns_an_unpicked_player_to_the_queue_not_to_unsold(): void
    {
        ['org' => $org, 'auction' => $auction] = $this->scenario();

        // Nobody bid on this player.
        $player = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);

        $this->actingAs($this->makeAuctionOperator($org))
            ->postJson(route('admin.auction.organizer.api.restart', $auction))
            ->assertOk();

        // Back in the queue, ready to be auctioned again — not written off.
        $this->assertSame('waiting', $player->fresh()->status);
    }

    #[Test]
    public function a_restart_stops_the_clock(): void
    {
        ['org' => $org, 'auction' => $auction] = $this->scenario();
        $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);

        $this->assertNotNull($auction->timer_started_at);

        $this->actingAs($this->makeAuctionOperator($org))
            ->postJson(route('admin.auction.organizer.api.restart', $auction))
            ->assertOk();

        // Left set, the big screen counts down a player who is no longer on the block.
        $this->assertNull($auction->fresh()->timer_started_at);
    }

    #[Test]
    public function a_restart_is_announced_for_a_fixed_window(): void
    {
        ['org' => $org, 'auction' => $auction] = $this->scenario();

        $this->actingAs($this->makeAuctionOperator($org))
            ->postJson(route('admin.auction.organizer.api.restart', $auction))
            ->assertOk();

        $auction->refresh();

        $this->assertTrue($auction->isRestarting());
        $this->assertSame(Auction::RESTART_NOTICE_SECONDS, $auction->restartNoticeRemaining());

        // The window is measured on the server, so every screen watching agrees rather
        // than each timing its own from whenever it last polled.
        $this->getJson("/auction/{$auction->id}/active-player")
            ->assertOk()
            ->assertJsonPath('restarting', true);

        // And it closes on its own.
        $auction->update(['restarted_at' => now()->subSeconds(Auction::RESTART_NOTICE_SECONDS + 1)]);

        $this->assertFalse($auction->fresh()->isRestarting());
        $this->getJson("/auction/{$auction->id}/active-player")
            ->assertOk()
            ->assertJsonPath('restarting', false);
    }

    #[Test]
    public function the_organizer_panel_is_told_about_the_restart_too(): void
    {
        ['org' => $org, 'auction' => $auction] = $this->scenario();
        $operator = $this->makeAuctionOperator($org);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.restart', $auction))
            ->assertOk();

        /*
         * Without this the panel had to guess, and it guessed wrong: the player who had
         * been on the block was suddenly neither on_auction nor in sold_players, which the
         * poll read as "passed on" and stamped a red UNSOLD across a freshly reset room.
         */
        $this->actingAs($operator)
            ->getJson(route('admin.auction.organizer.api.poll-state', $auction))
            ->assertOk()
            ->assertJsonPath('restarting', true)
            ->assertJsonPath('restart_seconds', Auction::RESTART_NOTICE_SECONDS);
    }

    #[Test]
    public function the_waiting_screen_is_told_how_far_through_the_room_is(): void
    {
        ['org' => $org, 'auction' => $auction] = $this->scenario();

        $this->makeAuctionPlayer($auction, ['status' => 'sold']);
        $this->makeAuctionPlayer($auction, ['status' => 'unsold']);
        $this->makeAuctionPlayer($auction, ['status' => 'skipped']);
        $this->makeAuctionPlayer($auction, ['status' => 'waiting']);
        $this->makeAuctionPlayer($auction, ['status' => 'waiting']);

        // Nobody is on the block, which is the case the waiting screen exists for. The
        // headline turns on these counts: "waiting for auction" is wrong once the room has
        // started working, and `status` alone cannot tell the difference.
        $this->getJson("/auction/{$auction->id}/active-player")
            ->assertOk()
            ->assertJsonPath('progress.sold', 1)
            // Skipped is a kind of unsold as far as the audience is concerned.
            ->assertJsonPath('progress.unsold', 2)
            ->assertJsonPath('progress.waiting', 2)
            ->assertJsonPath('progress.done', 3)
            ->assertJsonPath('progress.total', 5);
    }

    #[Test]
    public function the_progress_counts_are_present_while_a_player_is_on_the_block(): void
    {
        ['org' => $org, 'auction' => $auction] = $this->scenario();

        $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);
        $this->makeAuctionPlayer($auction, ['status' => 'sold']);

        // Both branches of activePlayer() return the same shape, so the screen never has to
        // check which one it got.
        $this->getJson("/auction/{$auction->id}/active-player")
            ->assertOk()
            ->assertJsonPath('progress.total', 2)
            ->assertJsonPath('progress.done', 1)
            ->assertJsonPath('progress.waiting', 0);
    }

    #[Test]
    public function a_restart_abandons_a_sealed_round_without_destroying_its_record(): void
    {
        ['org' => $org, 'auction' => $auction] = $this->scenario();
        $auction->update(['bid_type' => 'closed']);

        $player = $this->makeAuctionPlayer($auction, [
            'status' => 'on_auction',
            'current_price' => 8_000_000,
        ]);
        $round = app(ClosedBidService::class)->openRoundFor($player, $auction);

        $this->actingAs($this->makeAuctionOperator($org))
            ->postJson(route('admin.auction.organizer.api.restart', $auction))
            ->assertOk();

        // The row survives: a restart deletes bids and action logs, so this is the only
        // durable record of who bid what in a round that may later be disputed.
        $this->assertSame(AuctionClosedBidRound::STATE_ABANDONED, $round->fresh()->state);
        $this->assertNull($player->fresh()->closed_bid_round_id);
    }
}

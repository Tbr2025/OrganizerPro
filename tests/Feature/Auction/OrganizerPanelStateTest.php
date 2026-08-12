<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\AuctionPlayer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * The panel must describe the auction, not just the block.
 *
 * Three symptoms with one cause: the panel read "is somebody on the block right now" and used
 * it to answer questions that fact cannot answer.
 *
 *  - The main control read `currentPlayer ? 'NEXT' : 'START'`, so it reverted to START in the
 *    gap after every sale — an auction thirty players in kept offering to start itself.
 *  - The empty state had one fixed message inviting the operator to press START, shown for
 *    every waiting state including between players and while paused.
 *  - loadNextPlayer() dropped to that empty state BEFORE awaiting a poll, so the
 *    never-started screen flashed on the way to each player.
 */
class OrganizerPanelStateTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function scenario(array $auctionOverrides = []): array
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, array_merge([
            'tournament_id' => $tournament->id,
            'status' => 'running',
            'bid_type' => 'open',
            'max_budget_per_team' => 100_000_000,
            'base_price' => 1_000_000,
            'bid_timer_seconds' => 30,
            'bid_rules' => [['from' => 0, 'to' => 200_000_000, 'increment' => 100_000]],
        ], $auctionOverrides));

        $team = $this->makeTeam($org, 'Alpha', $tournament);

        return compact('org', 'tournament', 'auction', 'team');
    }

    private function panel($org, $auction): string
    {
        return $this->actingAs($this->makeSuperadmin($org))
            ->get(route('admin.auction.organizer.panel', $auction))
            ->assertOk()
            ->getContent();
    }

    #[Test]
    public function the_control_is_named_from_the_auctions_status(): void
    {
        ['org' => $org, 'auction' => $auction] = $this->scenario();

        $html = $this->panel($org, $auction);

        // One getter feeds the button and the empty state's keyboard hint, so the two cannot
        // describe the same key differently.
        $this->assertStringContainsString('nextActionLabel', $html);
        $this->assertStringContainsString("'NEXT'", $html);

        // And it must not be decided by whether a player happens to be up.
        $this->assertStringNotContainsString("currentPlayer ? 'NEXT (N)' : 'START (N)'", $html);
    }

    #[Test]
    public function the_empty_state_says_which_empty_it_is(): void
    {
        ['org' => $org, 'auction' => $auction] = $this->scenario();

        $html = $this->panel($org, $auction);

        // Between players, paused, and never-started are three different situations.
        $this->assertStringContainsString('Between players', $html);
        $this->assertStringContainsString('Ready to Auction', $html);
        $this->assertStringContainsString('Paused', $html);
    }

    #[Test]
    public function nothing_empty_is_shown_on_the_way_to_the_next_player(): void
    {
        ['org' => $org, 'auction' => $auction] = $this->scenario();

        $html = $this->panel($org, $auction);

        // The overlay goes up before the first await, so the poll's round trip is covered.
        $this->assertMatchesRegularExpression(
            '/showShuffleOverlay = true;\s*\n\s*await this\.pollAuctionState\(\);/',
            $html
        );
    }

    #[Test]
    public function the_poll_recovers_a_panel_that_lost_track_of_a_live_player(): void
    {
        ['org' => $org, 'auction' => $auction] = $this->scenario();

        $html = $this->panel($org, $auction);

        // A matching id must never mean "nothing to do" while the panel and the server disagree
        // about whether that player is up — nothing used to recover that.
        $this->assertStringContainsString('mustAdopt', $html);
        $this->assertStringContainsString("this.displayState !== 'bidding'", $html);
    }

    #[Test]
    public function the_team_strip_lays_out_from_the_team_count(): void
    {
        ['org' => $org, 'auction' => $auction] = $this->scenario();

        $html = $this->panel($org, $auction);

        // Rows follow the count rather than being pinned at two, which is what spread six teams
        // across three full-width columns.
        $this->assertStringContainsString('teamGridColumns', $html);
        $this->assertStringNotContainsString('grid-rows-2 grid-flow-col auto-cols-fr', $html);
    }

    #[Test]
    public function the_clock_running_out_does_not_tell_the_organizer_bidding_is_closed(): void
    {
        ['org' => $org, 'auction' => $auction, 'team' => $team] = $this->scenario([
            'timer_expiry_action' => 'manual',
        ]);

        $player = $this->makeAuctionPlayer($auction, ['status' => 'on_auction', 'current_price' => 1_000_000]);
        $auction->forceFill(['timer_started_at' => now()->subMinutes(5)])->save();

        $response = $this->actingAs($this->makeSuperadmin($org))
            ->postJson(route('admin.auction.organizer.api.timer-expired', $auction), [
                'auction_player_id' => $player->id,
            ])
            ->assertOk();

        // The clock is an announcement to the operator, not a lock on them — addBid no longer
        // refuses their bid, so the message must not claim bidding is closed.
        $message = (string) $response->json('message');
        $this->assertStringContainsString('Time up', $message);
        $this->assertStringNotContainsString('closed', $message);

        // And the player is still theirs to resolve however they choose.
        $this->assertSame('on_auction', $player->fresh()->status);
    }

    #[Test]
    public function a_bid_shows_on_screen_before_the_server_answers(): void
    {
        ['org' => $org, 'auction' => $auction] = $this->scenario();

        $html = $this->panel($org, $auction);

        // The price and leader used to change only once the round trip came back, so in a hall
        // the chip was pressed and nothing happened until the request landed.
        $this->assertStringContainsString('const previous = {', $html);
        $this->assertMatchesRegularExpression('/this\.currentBid = this\.nextBidAmount;/', $html);

        // And the server still wins: its figure overwrites the guess, a refusal restores what
        // was on screen.
        $this->assertStringContainsString('this.currentBid = data.current_price;', $html);
        $this->assertStringContainsString('this.currentBid = previous.bid;', $html);
    }

    #[Test]
    public function a_refused_team_does_not_wedge_the_bid_lock(): void
    {
        ['org' => $org, 'auction' => $auction] = $this->scenario();

        $html = $this->panel($org, $auction);

        // The excluded check used to sit AFTER `_isBidding = true` and return without releasing
        // it, so one tap on a priced-out team made the panel ignore every bid for the rest of
        // the lot. Every refusal is now decided before the lock is taken.
        $lockAt = strpos($html, 'this._isBidding = true;');
        $excludedAt = strpos($html, 'cannot bid on this player.');

        $this->assertNotFalse($lockAt);
        $this->assertNotFalse($excludedAt);
        $this->assertLessThan($lockAt, $excludedAt, 'The excluded-team refusal must come before the lock.');

        // The lock also disables the chips, so a double-tap cannot post twice.
        $this->assertStringContainsString('|| this._isBidding;', $html);
    }

    #[Test]
    public function the_cover_stays_up_until_the_player_is_actually_live(): void
    {
        ['org' => $org, 'auction' => $auction] = $this->scenario();

        $html = $this->panel($org, $auction);

        // The reveal used to lower the overlay the moment it landed on a name — but the player is
        // not on the block until the POST after it has been applied, so a default screen appeared
        // right after the selection.
        $this->assertStringContainsString('keepOverlay: true', $html);
        $this->assertStringContainsString('_waitForPlayerLive', $html);

        // And it comes down in a finally, so a refusal or a dropped request cannot leave the
        // panel behind a spinner that never lands.
        $this->assertMatchesRegularExpression(
            '/finally \{[^}]*this\.showShuffleOverlay = false;/s',
            $html
        );
    }
}

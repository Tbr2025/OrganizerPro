<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\AuctionActionLog;
use App\Models\AuctionClosedBidEntry;
use App\Models\AuctionClosedBidRound;
use App\Services\Auction\AuctionUndoService;
use App\Services\Auction\ClosedBidService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * The organizer's controls over a running sealed round: adjusting a team's amount,
 * withdrawing and restoring teams, undoing any of it, and settling a round nobody entered.
 *
 * Also covers two live hazards the sealed round introduced into existing screens — the
 * expiry handler that would have auto-sold past a sealed round, and the offline panel's
 * "−" button, which pops the auction's undo stack and would therefore have retracted a
 * team's sealed bid.
 */
class ClosedBidAdminTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function closedBids(): ClosedBidService
    {
        return app(ClosedBidService::class);
    }

    private function scenario(array $auctionOverrides = [], string $orgName = 'Test Org'): array
    {
        $org = $this->makeOrganization($orgName);
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, array_merge([
            'tournament_id' => $tournament->id,
            'status' => 'running',
            'bid_type' => 'closed',
            'max_budget_per_team' => 100_000_000,
            'closed_bid_starts_at' => 8_000_000,
            'closed_bid_step' => 100_000,
            'closed_bid_max_pct_of_budget' => 70,
            'bid_rules' => [['from' => 0, 'to' => 200_000_000, 'increment' => 100_000]],
        ], $auctionOverrides));

        $teamA = $this->makeTeam($org, 'Alpha', $tournament);
        $teamB = $this->makeTeam($org, 'Bravo', $tournament);

        $player = $this->makeAuctionPlayer($auction, [
            'status' => 'on_auction',
            'current_price' => 8_000_000,
            'current_bid_team_id' => $teamA->id,
        ]);

        $round = $this->closedBids()->openRoundFor($player, $auction);
        $this->closedBids()->start($round, null);

        return compact('org', 'tournament', 'auction', 'teamA', 'teamB', 'player', 'round');
    }

    /* ── Admin adjustment ────────────────────────────────────────────────────── */

    #[Test]
    public function the_admin_can_step_a_teams_amount_up_and_down(): void
    {
        ['org' => $org, 'auction' => $auction, 'teamA' => $teamA, 'round' => $round] = $this->scenario();

        $this->closedBids()->accept($round, $teamA);
        $this->closedBids()->submit($round, $teamA, 9_000_000, null);

        $entry = $round->entries()->where('actual_team_id', $teamA->id)->first();
        $operator = $this->makeAuctionOperator($org);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.closed-bid.entries.adjust', [$auction, $entry]), [
                'direction' => 'up',
            ])
            ->assertOk()
            ->assertJsonPath('handled', true);

        $this->assertSame(9_100_000.0, (float) $entry->fresh()->amount);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.closed-bid.entries.adjust', [$auction, $entry]), [
                'direction' => 'down',
            ])
            ->assertOk();

        $this->assertSame(9_000_000.0, (float) $entry->fresh()->amount);
    }

    #[Test]
    public function an_admin_custom_amount_obeys_the_same_step_rule(): void
    {
        ['round' => $round, 'teamA' => $teamA] = $this->scenario();

        $this->closedBids()->accept($round, $teamA);
        $this->closedBids()->submit($round, $teamA, 9_000_000, null);

        $entry = $round->entries()->where('actual_team_id', $teamA->id)->first();

        // Typed from the organizer's desk, but the rule is the team's rule.
        $result = $this->closedBids()->adjust($entry, 9_123_456, '', null);

        $this->assertFalse($result['handled']);
        $this->assertSame(9_000_000.0, (float) $entry->fresh()->amount, 'the stored amount is untouched');
    }

    #[Test]
    public function an_adjustment_is_recorded_on_the_entry_as_well_as_the_undo_log(): void
    {
        ['round' => $round, 'teamA' => $teamA] = $this->scenario();

        $this->closedBids()->accept($round, $teamA);
        $this->closedBids()->submit($round, $teamA, 9_000_000, null);

        $entry = $round->entries()->where('actual_team_id', $teamA->id)->first();
        $this->closedBids()->adjust($entry, 9_500_000, '', null);

        $entry->refresh();

        // Doubled on purpose: the action log makes it undoable, the JSON column survives
        // rebidPlayer() deleting that log.
        $this->assertSame(1, $entry->adjusted_count);
        $this->assertSame(9_000_000.0, (float) $entry->adjustments[0]['from']);
        $this->assertSame(9_500_000.0, (float) $entry->adjustments[0]['to']);
        $this->assertSame('admin', $entry->adjustments[0]['source']);

        $this->assertDatabaseHas('auction_action_logs', [
            'action' => AuctionActionLog::ACTION_CLOSED_ADJUST,
        ]);
    }

    #[Test]
    public function an_adjustment_after_the_reveal_is_refused(): void
    {
        ['round' => $round, 'teamA' => $teamA] = $this->scenario();

        $this->closedBids()->accept($round, $teamA);
        $this->closedBids()->submit($round, $teamA, 9_000_000, null);
        $this->closedBids()->lockAndReveal($round, null);

        $entry = $round->entries()->where('actual_team_id', $teamA->id)->first();

        // Editing a board everybody has already seen is rewriting history, not fixing a slip.
        $this->assertFalse($this->closedBids()->adjust($entry->fresh(), 9_900_000, '', null)['handled']);
    }

    #[Test]
    public function an_entry_from_another_auction_is_not_reachable(): void
    {
        ['org' => $org, 'round' => $round, 'teamA' => $teamA] = $this->scenario();
        ['auction' => $otherAuction] = $this->scenario([], 'Other Org');

        $this->closedBids()->accept($round, $teamA);
        $this->closedBids()->submit($round, $teamA, 9_000_000, null);
        $entry = $round->entries()->where('actual_team_id', $teamA->id)->first();

        // Route-model binding hands over any id in the URL, and {entry} is not a type
        // EnsureOrganizerCanAccess inspects.
        $this->actingAs($this->makeSuperadmin($org))
            ->postJson(route('admin.auction.organizer.api.closed-bid.entries.adjust', [$otherAuction, $entry]), [
                'direction' => 'up',
            ])
            ->assertNotFound();
    }

    /* ── Withdraw and restore from the desk ──────────────────────────────────── */

    #[Test]
    public function the_admin_can_withdraw_and_restore_a_team(): void
    {
        ['org' => $org, 'auction' => $auction, 'teamA' => $teamA, 'round' => $round] = $this->scenario();

        $this->closedBids()->accept($round, $teamA);
        $this->closedBids()->submit($round, $teamA, 9_000_000, null);

        $entry = $round->entries()->where('actual_team_id', $teamA->id)->first();
        $operator = $this->makeAuctionOperator($org);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.closed-bid.entries.withdraw', [$auction, $entry]))
            ->assertOk()
            ->assertJsonPath('handled', true);

        $entry->refresh();
        $this->assertTrue($entry->isWithdrawn());
        // Which actor withdrew matters: it decides whether the team is invited to a re-bid.
        $this->assertSame(AuctionClosedBidEntry::ROLE_ADMIN, $entry->withdrawn_by_role);
        $this->assertSame(0, $round->entries()->standing()->count());

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.closed-bid.entries.reinstate', [$auction, $entry]))
            ->assertOk();

        // The amount stands again on reinstatement — it was never deleted.
        $this->assertSame(1, $round->entries()->standing()->count());
        $this->assertSame(9_000_000.0, (float) $entry->fresh()->amount);
    }

    /* ── Undo ────────────────────────────────────────────────────────────────── */

    #[Test]
    public function a_sealed_bid_can_be_undone(): void
    {
        ['auction' => $auction, 'round' => $round, 'teamA' => $teamA] = $this->scenario();

        $this->closedBids()->accept($round, $teamA);
        $this->closedBids()->submit($round, $teamA, 9_000_000, null);

        // The second change comes from the ORGANIZER, because a team's own bid is final once
        // submitted. The guarantee under test is unchanged: undoing an amount change returns the
        // previous amount rather than clearing the bid.
        $entry = $round->entries()->where('actual_team_id', $teamA->id)->first();
        $this->closedBids()->submit($round, $teamA, 9_500_000, null, \App\Models\AuctionClosedBidEntry::ROLE_ADMIN);

        $result = app(AuctionUndoService::class)->undoLast($auction);

        $this->assertTrue($result['success'], $result['message'] ?? '');
        $this->assertSame(
            9_000_000.0,
            (float) $round->entries()->where('actual_team_id', $teamA->id)->first()->amount,
            'undo rolls back to the previous amount, not to nothing'
        );
    }

    #[Test]
    public function an_admin_adjustment_can_be_undone_and_the_trail_shrinks_with_it(): void
    {
        ['auction' => $auction, 'round' => $round, 'teamA' => $teamA] = $this->scenario();

        $this->closedBids()->accept($round, $teamA);
        $this->closedBids()->submit($round, $teamA, 9_000_000, null);

        $entry = $round->entries()->where('actual_team_id', $teamA->id)->first();
        $this->closedBids()->adjust($entry, 9_500_000, '', null);

        $this->assertTrue(app(AuctionUndoService::class)->undoLast($auction)['success']);

        $entry->refresh();
        $this->assertSame(9_000_000.0, (float) $entry->amount);
        $this->assertSame(0, $entry->adjusted_count);
        $this->assertSame([], $entry->adjustments ?? []);
    }

    #[Test]
    public function a_withdrawal_can_be_undone(): void
    {
        ['auction' => $auction, 'round' => $round, 'teamA' => $teamA] = $this->scenario();

        $this->closedBids()->accept($round, $teamA);
        $this->closedBids()->submit($round, $teamA, 9_000_000, null);

        $entry = $round->entries()->where('actual_team_id', $teamA->id)->first();
        $this->closedBids()->withdraw($entry, null, AuctionClosedBidEntry::ROLE_ADMIN);

        $this->assertTrue(app(AuctionUndoService::class)->undoLast($auction)['success']);
        $this->assertFalse($entry->fresh()->isWithdrawn());
    }

    /**
     * Undo takes the reveal off first, then the bids beneath it.
     *
     * A revealed round used to be a dead end: the reveal was not recorded, so undo reached
     * straight past it to the bid and hit the guard below every time, leaving no way to
     * correct a mistyped amount short of re-bidding the player.
     */
    #[Test]
    public function undo_takes_the_reveal_off_before_the_bids_beneath_it(): void
    {
        ['auction' => $auction, 'round' => $round, 'teamA' => $teamA] = $this->scenario();

        $this->closedBids()->accept($round, $teamA);
        $this->closedBids()->submit($round, $teamA, 9_000_000, null);
        $this->closedBids()->lockAndReveal($round, null);

        $this->assertTrue($round->fresh()->isRevealed());

        $first = app(AuctionUndoService::class)->undoLast($auction);

        $this->assertTrue($first['success']);
        $this->assertFalse($round->fresh()->isRevealed());
        $this->assertSame(AuctionClosedBidRound::STATE_COLLECTING, $round->fresh()->state);
        $this->assertNull($round->fresh()->winner_team_id);

        // With the board no longer revealed, the amount underneath comes off normally.
        $second = app(AuctionUndoService::class)->undoLast($auction);

        $this->assertTrue($second['success']);
        $this->assertNull($round->entries()->where('actual_team_id', $teamA->id)->first()->amount);
    }

    #[Test]
    public function a_sealed_bid_cannot_be_undone_while_the_board_is_still_revealed(): void
    {
        ['auction' => $auction, 'round' => $round, 'teamA' => $teamA] = $this->scenario();

        $this->closedBids()->accept($round, $teamA);
        $this->closedBids()->submit($round, $teamA, 9_000_000, null);
        $this->closedBids()->lockAndReveal($round, null);

        /*
         * Take the reveal out of the stack WITHOUT restoring the round, so the board stays
         * revealed and the bid is the next undoable action. That is how a round revealed
         * before reveals were recorded still looks, and it is the only way to aim undo at
         * the bid while the amounts are public — the ordinary ladder removes the reveal
         * first, which is what the test above covers.
         */
        AuctionActionLog::where('auction_id', $auction->id)
            ->where('action', AuctionActionLog::ACTION_CLOSED_REVEAL)
            ->update(['undone_at' => now()]);

        $result = app(AuctionUndoService::class)->undoLast($auction);

        // Putting an amount back onto a board the room has seen is rewriting history.
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('revealed', $result['message']);
    }

    /* ── Nobody entered ──────────────────────────────────────────────────────── */

    #[Test]
    public function a_round_nobody_entered_can_be_awarded_to_the_standing_leader(): void
    {
        ['org' => $org, 'auction' => $auction, 'teamA' => $teamA, 'round' => $round, 'player' => $player] = $this->scenario();

        // force: the clock is what ends an empty round now — a premature manual lock is
        // refused so an organizer cannot discard a round they have not typed into yet.
        $this->closedBids()->lockAndReveal($round, null, force: true);
        $this->assertSame(AuctionClosedBidRound::STATE_NO_ENTRIES, $round->fresh()->state);

        $this->actingAs($this->makeAuctionOperator($org))
            ->postJson(route('admin.auction.organizer.api.closed-bid.no-entries-decision', $auction), [
                'choice' => 'award_leader',
            ])
            ->assertOk()
            ->assertJsonPath('handled', true);

        $player->refresh();

        // The player was already at the threshold with a leading team when the sealed
        // round opened, so this loses nothing.
        $this->assertSame('sold', $player->status);
        $this->assertSame($teamA->id, $player->sold_to_team_id);
        $this->assertSame(8_000_000.0, (float) $player->final_price);
        $this->assertSame(
            AuctionClosedBidRound::RESOLUTION_LEADER_AT_THRESHOLD,
            $round->fresh()->resolution
        );
    }

    #[Test]
    public function a_round_nobody_entered_can_be_sent_to_unsold_instead(): void
    {
        ['org' => $org, 'auction' => $auction, 'round' => $round, 'player' => $player] = $this->scenario();

        // force: the clock is what ends an empty round now — a premature manual lock is
        // refused so an organizer cannot discard a round they have not typed into yet.
        $this->closedBids()->lockAndReveal($round, null, force: true);

        $this->actingAs($this->makeAuctionOperator($org))
            ->postJson(route('admin.auction.organizer.api.closed-bid.no-entries-decision', $auction), [
                'choice' => 'unsold',
            ])
            ->assertOk()
            ->assertJsonPath('handled', true);

        $this->assertSame(AuctionClosedBidRound::STATE_UNSOLD, $round->fresh()->state);
        $this->assertNotSame('sold', $player->fresh()->status);
    }

    #[Test]
    public function the_organizer_panel_renders_the_sealed_console(): void
    {
        ['org' => $org, 'auction' => $auction] = $this->scenario();

        $this->actingAs($this->makeAuctionOperator($org))
            ->get(route('admin.auction.organizer.panel', $auction))
            ->assertOk()
            ->assertSee('Sealed Round')
            ->assertSee('Start Closed Bid')
            ->assertSee('Lock &amp; Reveal', false)
            ->assertSee('DRAW LOT')
            // The draw takes its winner from the server before the animation runs;
            // spinning and then revealing a locally-picked name would be a fabricated
            // draw, which is worse than showing no animation at all.
            ->assertSee('sealedDrawLot', false)
            // The dead close-bidding path is gone rather than left lying around.
            ->assertDontSee('closeBidding(', false);
    }

    /* ── The two hazards in existing screens ─────────────────────────────────── */

    #[Test]
    public function an_expiring_sealed_round_is_held_instead_of_auto_selling_to_the_open_leader(): void
    {
        ['org' => $org, 'auction' => $auction, 'teamA' => $teamA, 'teamB' => $teamB, 'round' => $round, 'player' => $player] = $this->scenario([
            // The setting that would have caused the damage.
            'timer_expiry_action' => 'auto_sell',
            'closed_bid_timer_seconds' => 5,
        ]);

        $this->closedBids()->accept($round, $teamB);
        $this->closedBids()->submit($round, $teamB, 9_500_000, null);

        // Wind the round's clock past its limit.
        $round->update(['timer_started_at' => now()->subMinutes(5)]);

        $this->actingAs($this->makeAuctionOperator($org))
            ->postJson(route('admin.auction.organizer.api.timer-expired', $auction), [
                'auction_player_id' => $player->id,
            ])
            ->assertOk()
            ->assertJsonPath('action', 'sealed_held');

        $player->refresh();

        /*
         * The guarantee this test exists for is unchanged: left to the open-bid path an expiring
         * sealed round would have sold the player to Alpha — the OPEN leader at the threshold —
         * skipping the sealed round entirely.
         *
         * What changed is that time up now HOLDS the round rather than locking it. A clock can
         * reach zero with every team still accepting, and resolving on that turned a countdown
         * into the thing that decided the player. Ending it is an act: Lock & Reveal, or Extend.
         */
        $this->assertNotSame('sold', $player->status);
        $this->assertSame(AuctionClosedBidRound::STATE_COLLECTING, $round->fresh()->state);
        $this->assertNull($round->fresh()->winner_team_id);

        // The deadline itself still stands — holding is not extra time.
        $late = $this->closedBids()->submit($round->fresh(), $teamA, 9_900_000, null);
        $this->assertFalse($late['handled']);
        $this->assertStringContainsString('Time is up', $late['message']);

        // And Extend is the way to give the room longer, deliberately.
        $this->assertTrue($this->closedBids()->extendTimer($round->fresh(), null)['handled']);
        $this->assertFalse($auction->fresh()->closedBidRoundTimerState($round->fresh())['expired']);
    }

    #[Test]
    public function the_offline_minus_button_cannot_retract_a_sealed_bid(): void
    {
        ['org' => $org, 'auction' => $auction, 'round' => $round, 'teamA' => $teamA, 'player' => $player] = $this->scenario();

        $this->closedBids()->accept($round, $teamA);
        $this->closedBids()->submit($round, $teamA, 9_000_000, null);

        // "−" pops the auction's undo stack, and during a sealed round the newest entry
        // on it is very likely a team's sealed bid.
        $this->actingAs($this->makeAuctionOperator($org))
            ->postJson(route('admin.auctions.players.decreaseBid'), [
                'auctionId' => $auction->id,
                'playerID' => $player->id,
            ])
            ->assertStatus(422);

        $this->assertSame(
            9_000_000.0,
            (float) $round->entries()->where('actual_team_id', $teamA->id)->first()->amount
        );
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\AuctionClosedBidEntry;
use App\Models\AuctionClosedBidRound;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Undoing an open bid has to take the sealed round built on it with it.
 *
 * A round's floor is derived from the price at the moment it opens, and its teams are invited
 * on that basis. The round used to be cleared only when the undo took the price back BELOW the
 * sealed threshold — so undoing 8.1M down to 8.0M against an 8M threshold left a live sealed
 * board with a floor snapped to a bid that no longer existed. The only ways out were UNDO again
 * (which reverses by action, not by consequence) or withdrawing every invitation one at a time.
 *
 * And the confirm dialog said none of this: `AuctionActionLog::description` is written when the
 * action happens, so it offered "Will undo: Bid 8.1M by TEST Delta" over a board the same click
 * was about to cancel.
 */
class UndoClearsStaleSealedRoundTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    /**
     * An auction sitting above its sealed threshold with a round open on the current player.
     *
     * @return array{auction: \App\Models\Auction, user: \App\Models\User, ap: \App\Models\AuctionPlayer, round: AuctionClosedBidRound, teamA: \App\Models\ActualTeam, teamB: \App\Models\ActualTeam}
     */
    private function scenario(array $auctionAttrs = []): array
    {
        $org = $this->makeOrganization();

        $auction = $this->makeAuction($org, array_merge([
            'max_budget_per_team' => 100_000_000,
            'bid_type' => 'closed',
            'closed_bid_starts_at' => 250,
            'bid_type_manually_overridden' => false,
        ], $auctionAttrs));

        $user = $this->makeAuctionOperator($org);
        $teamA = $this->makeTeam($org, 'Alpha');
        $teamB = $this->makeTeam($org, 'Bravo');

        $ap = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);

        /*
         * Four bids: 100 -> 200 -> 300 -> 400. Undoing the last leaves 300, which is still above
         * the 250 threshold — the case where the round used to survive.
         *
         * Four rather than three because the opening bid TAKES the 100 base rather than raising
         * it, so three bids now land on 300 and an undo would drop below the threshold, which is
         * a different scenario from the one this test is for.
         */
        foreach ([$teamA, $teamB, $teamA, $teamB] as $team) {
            $this->actingAs($user)->postJson(route('admin.auctions.players.addBid'), [
                'auctionId' => $auction->id, 'playerID' => $ap->id, 'teamId' => $team->id,
            ])->assertOk();
        }

        $round = AuctionClosedBidRound::create([
            'auction_id' => $auction->id,
            'auction_player_id' => $ap->id,
            'state' => AuctionClosedBidRound::STATE_COLLECTING,
            'floor' => (float) $ap->fresh()->current_price,
            'step' => 10,
            'max_pct_of_budget' => 100,
            'opened_at' => now(),
            'entry_opened_at' => now(),
        ]);

        $ap->update(['closed_bid_round_id' => $round->id]);

        return compact('auction', 'user', 'ap', 'round', 'teamA', 'teamB');
    }

    #[Test]
    public function the_round_is_cancelled_even_when_the_price_stays_above_the_threshold(): void
    {
        ['auction' => $auction, 'user' => $user, 'ap' => $ap, 'round' => $round] = $this->scenario();

        // 400 -> 300 on a threshold of 250: still sealed territory, and previously the round
        // survived with a floor derived from a bid that had just been voided.
        $this->assertSame(400.0, (float) $ap->fresh()->current_price);

        $this->actingAs($user)
            ->postJson(route('admin.auction.organizer.api.undo', $auction))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(300.0, (float) $ap->fresh()->current_price);
        $this->assertSame(AuctionClosedBidRound::STATE_ABANDONED, $round->fresh()->state);
        $this->assertNull($ap->fresh()->closed_bid_round_id);

        // The phase is a separate question, and 300 is still above 250 — so the auction stays
        // sealed with no round, ready for a fresh one at the floor that is now true.
        $this->assertSame('closed', $auction->fresh()->bid_type);
    }

    #[Test]
    public function falling_below_the_threshold_still_returns_the_auction_to_open_bidding(): void
    {
        ['auction' => $auction, 'user' => $user, 'ap' => $ap, 'round' => $round] = $this->scenario([
            'closed_bid_starts_at' => 350,
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.auction.organizer.api.undo', $auction))
            ->assertOk();

        $this->assertSame(AuctionClosedBidRound::STATE_ABANDONED, $round->fresh()->state);
        $this->assertSame('open', $auction->fresh()->bid_type, '300 is below the 350 threshold.');
    }

    #[Test]
    public function a_team_with_a_real_amount_in_keeps_the_round_alive(): void
    {
        ['auction' => $auction, 'user' => $user, 'ap' => $ap, 'round' => $round, 'teamA' => $teamA] = $this->scenario();

        AuctionClosedBidEntry::create([
            'auction_id' => $auction->id,
            'auction_closed_bid_round_id' => $round->id,
            'actual_team_id' => $teamA->id,
            'state' => AuctionClosedBidEntry::STATE_SUBMITTED,
            'amount' => 500,
            'submitted_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.auction.organizer.api.undo', $auction))
            ->assertOk();

        // Discarding a submitted sealed bid to tidy up a floor is not a trade worth making.
        $this->assertSame(AuctionClosedBidRound::STATE_COLLECTING, $round->fresh()->state);
        $this->assertNotNull($ap->fresh()->closed_bid_round_id);
    }

    #[Test]
    public function an_acceptance_is_not_a_bid_and_does_not_hold_the_round_open(): void
    {
        ['auction' => $auction, 'user' => $user, 'round' => $round, 'teamA' => $teamA] = $this->scenario();

        // Accepted, but no amount — an invitation taken up, not a bid placed. Nothing is lost
        // by cancelling, because starting the round again re-invites.
        AuctionClosedBidEntry::create([
            'auction_id' => $auction->id,
            'auction_closed_bid_round_id' => $round->id,
            'actual_team_id' => $teamA->id,
            'state' => AuctionClosedBidEntry::STATE_ACCEPTED,
            'accepted_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.auction.organizer.api.undo', $auction))
            ->assertOk();

        $this->assertSame(AuctionClosedBidRound::STATE_ABANDONED, $round->fresh()->state);
    }

    #[Test]
    public function the_confirm_dialog_says_what_the_one_click_will_actually_do(): void
    {
        ['auction' => $auction, 'user' => $user] = $this->scenario();

        $notes = $this->actingAs($user)
            ->getJson(route('admin.auction.organizer.api.poll-state', $auction))
            ->assertOk()
            ->json('next_undo_notes');

        $this->assertIsArray($notes);

        $joined = implode(' | ', $notes);
        // Alpha 100 (the opening bid, which takes the base), Bravo 200, Alpha 300, Bravo 400 —
        // undoing the last leaves Alpha leading at 300.
        $this->assertStringContainsString('Price goes back to', $joined);
        $this->assertStringContainsString('Alpha', $joined, 'It should name the team the price falls back to.');
        $this->assertStringContainsString('sealed round', $joined);
    }

    #[Test]
    public function the_dialog_does_not_promise_to_cancel_a_round_the_undo_will_keep(): void
    {
        ['auction' => $auction, 'user' => $user, 'round' => $round, 'teamA' => $teamA] = $this->scenario();

        AuctionClosedBidEntry::create([
            'auction_id' => $auction->id,
            'auction_closed_bid_round_id' => $round->id,
            'actual_team_id' => $teamA->id,
            'state' => AuctionClosedBidEntry::STATE_SUBMITTED,
            'amount' => 500,
            'submitted_at' => now(),
        ]);

        $notes = $this->actingAs($user)
            ->getJson(route('admin.auction.organizer.api.poll-state', $auction))
            ->assertOk()
            ->json('next_undo_notes');

        // The preview and the undo read the same guard, so they cannot disagree.
        $this->assertStringNotContainsString('sealed round', implode(' | ', $notes));
    }
}

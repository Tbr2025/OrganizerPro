<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\AuctionClosedBidEntry;
use App\Models\AuctionClosedBidRound;
use App\Services\Auction\ClosedBidService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * A tie stopped and waited for somebody to press a button.
 *
 * Everything a tie-break needs was already here — `startRebid()` builds the child round, sets its
 * floor strictly above the tied amount, marks the tied teams MUST_REBID and everyone else
 * MAY_OPT_IN, and starts the clock. The only thing missing was the trigger, and in a hall that
 * missing trigger is a pause with nothing in it at the moment the room is most interested.
 */
class SealedTieAutoRebidTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    /**
     * Two teams tied at the same amount, on an auction that allows re-bids.
     *
     * @return array{auction: \App\Models\Auction, round: AuctionClosedBidRound, a: \App\Models\ActualTeam, b: \App\Models\ActualTeam}
     */
    private function tie(array $auctionAttrs = []): array
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);

        $auction = $this->makeAuction($org, array_merge([
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 100_000_000,
            'bid_type' => 'closed',
            'closed_bid_starts_at' => 100,
            'closed_bid_max_rebid_rounds' => 2,
            'closed_bid_timer_seconds' => 45,
        ], $auctionAttrs));

        $a = $this->makeTeam($org, 'Alpha', $tournament);
        $b = $this->makeTeam($org, 'Bravo', $tournament);

        $ap = $this->makeAuctionPlayer($auction, ['status' => 'on_auction', 'current_price' => 500]);

        $round = AuctionClosedBidRound::create([
            'auction_id' => $auction->id,
            'auction_player_id' => $ap->id,
            'attempt_no' => 1,
            'round_number' => 1,
            'state' => AuctionClosedBidRound::STATE_COLLECTING,
            'floor' => 500,
            'step' => 100,
            'max_pct_of_budget' => 100,
            'timer_seconds' => 45,
            'opened_at' => now(),
        ]);

        $ap->update(['closed_bid_round_id' => $round->id]);

        foreach ([$a, $b] as $team) {
            AuctionClosedBidEntry::create([
                'auction_id' => $auction->id,
                'auction_closed_bid_round_id' => $round->id,
                'actual_team_id' => $team->id,
                'state' => AuctionClosedBidEntry::STATE_SUBMITTED,
                // The same figure — the whole point.
                'amount' => 900,
                'submitted_at' => now(),
            ]);
        }

        return ['auction' => $auction, 'round' => $round, 'a' => $a, 'b' => $b];
    }

    #[Test]
    public function a_tie_opens_the_next_round_by_itself_when_the_auction_says_so(): void
    {
        ['auction' => $auction, 'round' => $round, 'a' => $a, 'b' => $b] =
            $this->tie(['closed_bid_auto_rebid' => true]);

        $result = app(ClosedBidService::class)->lockAndReveal($round);

        $this->assertTrue($result['handled']);
        $this->assertTrue($result['auto_rebid'] ?? false);

        $child = AuctionClosedBidRound::where('parent_round_id', $round->id)->sole();

        $this->assertSame(2, $child->round_number);
        $this->assertSame(AuctionClosedBidRound::STATE_COLLECTING, $child->state);

        // Strictly above the tied amount — enforced by the floor rather than asked for in copy.
        $this->assertGreaterThan(900, (float) $child->floor);

        // And the clock is running, which is the other half of "run the timer too".
        $this->assertNotNull($child->timer_started_at);
        $this->assertSame(45, (int) $child->timer_seconds);

        // Both tied teams must bid again; nobody else was in the round to opt in.
        $states = $child->entries()->pluck('state', 'actual_team_id');
        $this->assertSame(AuctionClosedBidEntry::STATE_MUST_REBID, $states[$a->id]);
        $this->assertSame(AuctionClosedBidEntry::STATE_MUST_REBID, $states[$b->id]);

        // The player follows the live round, or the panel would keep showing the tied one.
        $this->assertSame($child->id, $round->auctionPlayer->fresh()->closed_bid_round_id);
    }

    #[Test]
    public function without_the_setting_a_tie_waits_for_the_organizer(): void
    {
        ['round' => $round] = $this->tie(['closed_bid_auto_rebid' => false]);

        $result = app(ClosedBidService::class)->lockAndReveal($round);

        $this->assertTrue($result['handled']);
        $this->assertArrayNotHasKey('auto_rebid', $result);

        // Left at `tie`, exactly as every auction behaved before the setting existed. An
        // organizer may want the pause — to read the tied amount out, or to take a query.
        $this->assertSame(AuctionClosedBidRound::STATE_TIE, $round->fresh()->state);
        $this->assertSame(0, AuctionClosedBidRound::where('parent_round_id', $round->id)->count());
    }

    #[Test]
    public function a_tie_in_the_final_round_goes_to_a_draw_rather_than_another_round(): void
    {
        /*
         * The number of re-bids is decided by closed_bid_max_rebid_rounds, not by this setting.
         * With none allowed, the very first tie is already final — and auto-rebid must not open
         * a round the settings do not permit to exist.
         */
        ['round' => $round] = $this->tie([
            'closed_bid_auto_rebid' => true,
            'closed_bid_max_rebid_rounds' => 0,
        ]);

        app(ClosedBidService::class)->lockAndReveal($round);

        $this->assertSame(AuctionClosedBidRound::STATE_AWAITING_LOT, $round->fresh()->state);
        $this->assertSame(0, AuctionClosedBidRound::where('parent_round_id', $round->id)->count());
    }

    #[Test]
    public function a_clear_winner_is_not_sent_to_a_re_bid(): void
    {
        ['round' => $round, 'b' => $b] = $this->tie(['closed_bid_auto_rebid' => true]);

        // Break the tie: Bravo goes higher.
        AuctionClosedBidEntry::where('auction_closed_bid_round_id', $round->id)
            ->where('actual_team_id', $b->id)
            ->update(['amount' => 1_200]);

        app(ClosedBidService::class)->lockAndReveal($round);

        $round->refresh();

        $this->assertSame(AuctionClosedBidRound::STATE_REVEALED, $round->state);
        $this->assertSame($b->id, $round->winner_team_id);
        $this->assertSame(0, AuctionClosedBidRound::where('parent_round_id', $round->id)->count());
    }
}

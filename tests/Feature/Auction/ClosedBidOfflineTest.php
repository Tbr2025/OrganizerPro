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
 * A sealed round run offline.
 *
 * Online and offline are not two ends of one ladder — they decide *who types the bid*, and
 * the sealed round happens either way. Offline means the teams are in the room rather than on
 * their own screens, so the organizer enters each team's private amount for them. Without
 * that, an offline sealed round could only ever reach "no entries", and the price would
 * simply freeze at the threshold with nobody able to move it.
 */
class ClosedBidOfflineTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function closedBids(): ClosedBidService
    {
        return app(ClosedBidService::class);
    }

    private function offlineScenario(array $overrides = []): array
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, array_merge([
            'tournament_id' => $tournament->id,
            'status' => 'running',
            'max_budget_per_team' => 100_000_000,
            'closed_bid_starts_at' => 8_000_000,
            'closed_bid_step' => 100_000,
            'closed_bid_max_pct_of_budget' => 70,
            'bid_type' => 'closed',
            // The room is being run by hand.
            'open_bid_mode' => 'offline',
            'mode_manually_overridden' => true,
            // The gate teams would normally click through — unreachable offline.
            'closed_bid_requires_acceptance' => true,
            'bid_rules' => [['from' => 0, 'to' => 200_000_000, 'increment' => 100_000]],
        ], $overrides));

        return [
            $org,
            $auction,
            $this->makeTeam($org, 'Alpha', $tournament),
            $this->makeTeam($org, 'Bravo', $tournament),
        ];
    }

    #[Test]
    public function the_organizer_can_run_a_whole_sealed_round_offline(): void
    {
        [$org, $auction, $alpha, $bravo] = $this->offlineScenario();
        $operator = $this->makeAuctionOperator($org);
        $player = $this->makeAuctionPlayer($auction, [
            'status' => 'on_auction',
            'current_price' => 8_000_000,
        ]);

        $round = $this->closedBids()->openRoundFor($player, $auction);
        $this->assertNotNull($round);

        $this->actingAs($operator);

        // Entries are seeded for every eligible team, so the organizer has a row to type in.
        $this->postJson(route('admin.auction.organizer.api.closed-bid.open-entry', $auction), [
            'auction_player_id' => $player->id,
        ])->assertOk();

        $this->postJson(route('admin.auction.organizer.api.closed-bid.start', $auction), [
            'auction_player_id' => $player->id,
        ])->assertOk();

        $entries = AuctionClosedBidEntry::where('auction_closed_bid_round_id', $round->id)
            ->get()
            ->keyBy('actual_team_id');

        $this->assertCount(2, $entries, 'every eligible team needs a row to enter an amount into');

        // The organizer enters each team's sealed amount. Nobody ever clicked "accept" —
        // they could not have, offline — and that must not block this.
        $this->postJson(route('admin.auction.organizer.api.closed-bid.entries.adjust', [
            $auction, $entries[$alpha->id],
        ]), ['amount' => 9_000_000])->assertOk();

        $this->postJson(route('admin.auction.organizer.api.closed-bid.entries.adjust', [
            $auction, $entries[$bravo->id],
        ]), ['amount' => 9_500_000])->assertOk();

        $this->postJson(route('admin.auction.organizer.api.closed-bid.lock', $auction), [
            'auction_player_id' => $player->id,
        ])->assertOk();

        $round->refresh();
        $this->assertSame(AuctionClosedBidRound::STATE_REVEALED, $round->state);
        $this->assertSame($bravo->id, $round->winner_team_id, 'the highest sealed amount takes the player');
        $this->assertSame(9_500_000.0, (float) $round->winning_amount);

        $this->postJson(route('admin.auction.organizer.api.closed-bid.award', $auction), [
            'auction_player_id' => $player->id,
        ])->assertOk();

        $player->refresh();
        $this->assertSame('sold', $player->status);
        $this->assertSame($bravo->id, $player->sold_to_team_id);
        $this->assertSame(9_500_000.0, (float) $player->final_price);
    }

    #[Test]
    public function an_amount_the_organizer_enters_is_recorded_as_theirs_not_the_teams(): void
    {
        [$org, $auction, $alpha] = $this->offlineScenario();
        $player = $this->makeAuctionPlayer($auction, ['status' => 'on_auction', 'current_price' => 8_000_000]);
        $round = $this->closedBids()->openRoundFor($player, $auction);

        $this->actingAs($this->makeAuctionOperator($org));
        $this->postJson(route('admin.auction.organizer.api.closed-bid.open-entry', $auction), ['auction_player_id' => $player->id]);
        $this->postJson(route('admin.auction.organizer.api.closed-bid.start', $auction), ['auction_player_id' => $player->id]);

        $entry = AuctionClosedBidEntry::where('auction_closed_bid_round_id', $round->id)
            ->where('actual_team_id', $alpha->id)->firstOrFail();

        $this->postJson(route('admin.auction.organizer.api.closed-bid.entries.adjust', [$auction, $entry]), [
            'amount' => 9_100_000,
        ])->assertOk();

        $entry->refresh();

        // A disputed round has to be defensible: who typed the figure is the whole question.
        $this->assertSame(9_100_000.0, (float) $entry->amount);
        $this->assertSame(1, (int) $entry->adjusted_count);
        $this->assertNotEmpty($entry->adjustments, 'an admin-entered amount must leave a trail');
    }

    #[Test]
    public function the_step_rule_still_binds_when_the_organizer_types_the_amount(): void
    {
        [$org, $auction, $alpha] = $this->offlineScenario();
        $player = $this->makeAuctionPlayer($auction, ['status' => 'on_auction', 'current_price' => 8_000_000]);
        $round = $this->closedBids()->openRoundFor($player, $auction);

        $this->actingAs($this->makeAuctionOperator($org));
        $this->postJson(route('admin.auction.organizer.api.closed-bid.open-entry', $auction), ['auction_player_id' => $player->id]);
        $this->postJson(route('admin.auction.organizer.api.closed-bid.start', $auction), ['auction_player_id' => $player->id]);

        $entry = AuctionClosedBidEntry::where('auction_closed_bid_round_id', $round->id)
            ->where('actual_team_id', $alpha->id)->firstOrFail();

        // 9.05M is off the 0.1M grid. Rejected, not quietly rounded — the organizer is
        // typing on someone else's behalf, so a silent correction is worse here, not better.
        $this->postJson(route('admin.auction.organizer.api.closed-bid.entries.adjust', [$auction, $entry]), [
            'amount' => 9_050_000,
        ])->assertOk()->assertJsonPath('handled', false);

        $this->assertNull($entry->fresh()->amount, 'an illegal amount must not be stored at all');
    }

    #[Test]
    public function offline_mode_survives_the_sealed_round_and_the_next_player(): void
    {
        [$org, $auction, $alpha, $bravo] = $this->offlineScenario();
        $player = $this->makeAuctionPlayer($auction, ['status' => 'on_auction', 'current_price' => 8_000_000]);
        $this->closedBids()->openRoundFor($player, $auction);

        // The round is done with — putPlayerOnBid rightly refuses while someone is still on
        // the block, so this test is about what the NEXT player does to the two axes.
        $player->update(['status' => 'unsold']);
        $this->closedBids()->abandonRoundsFor($player->fresh());

        $next = $this->makeAuctionPlayer($auction, ['status' => 'waiting']);

        $this->actingAs($this->makeAuctionOperator($org))
            ->postJson(route('admin.auction.organizer.api.player.onbid', $auction), [
                'auction_player_id' => $next->id,
            ])->assertOk();

        $auction->refresh();

        // The room is still being run by hand; only the price-driven axis resets.
        $this->assertSame('offline', $auction->open_bid_mode);
        $this->assertSame('open', $auction->bid_type);
    }

    #[Test]
    public function locking_a_round_nobody_has_entered_is_refused_not_resolved(): void
    {
        [$org, $auction, $alpha, $bravo] = $this->offlineScenario();
        $player = $this->makeAuctionPlayer($auction, ['status' => 'on_auction', 'current_price' => 8_000_000]);
        $round = $this->closedBids()->openRoundFor($player, $auction);

        $this->actingAs($this->makeAuctionOperator($org));
        $this->postJson(route('admin.auction.organizer.api.closed-bid.start', $auction), ['auction_player_id' => $player->id]);

        /*
         * Locking an empty round resolved it to `no_entries` — a terminal state whose only
         * exits are awarding the open-bid leader or marking the player unsold. So pressing
         * Lock moments after Start threw the round away, and the only feedback was "No team
         * entered the sealed round", which reads as a fault rather than as "you have not
         * typed any amounts yet". Offline it is always premature: the teams are in the room
         * and cannot submit for themselves.
         */
        $this->postJson(route('admin.auction.organizer.api.closed-bid.lock', $auction), [
            'auction_player_id' => $player->id,
        ])->assertOk()->assertJsonPath('handled', false);

        $this->assertSame(
            AuctionClosedBidRound::STATE_COLLECTING,
            $round->fresh()->state,
            'the round must still be open to receive amounts'
        );
    }

    #[Test]
    public function an_expired_round_with_no_bids_still_resolves_to_no_entries(): void
    {
        [$org, $auction, $alpha] = $this->offlineScenario(['closed_bid_timer_seconds' => 30]);
        $player = $this->makeAuctionPlayer($auction, ['status' => 'on_auction', 'current_price' => 8_000_000]);
        $round = $this->closedBids()->openRoundFor($player, $auction);

        $this->closedBids()->start($round, null);

        // The clock genuinely ran out, so an empty round is a real result and must still
        // resolve — the refusal above is only for a premature manual lock.
        $this->travel(120)->seconds();

        $result = $this->closedBids()->lockAndReveal($round->fresh(), null, force: true);

        $this->assertTrue($result['handled']);
        $this->assertSame(AuctionClosedBidRound::STATE_NO_ENTRIES, $round->fresh()->state);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\AuctionClosedBidRound;
use App\Services\Auction\ClosedBidService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Opening a sealed round.
 *
 * The trigger itself is deliberately unchanged: a bid reaching `closed_bid_starts_at`
 * still flips the auction to closed bidding, exactly as before. What is new is that the
 * same moment now creates a round row, so the sealed phase has somewhere to keep its
 * state instead of being inferred from `auctions.bid_type` alone.
 */
class ClosedBidRoundTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function closedBids(): ClosedBidService
    {
        return app(ClosedBidService::class);
    }

    /** An auction that switches to sealed bidding at 8M. */
    private function sealedAuction(array $overrides = [])
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);

        return [$org, $tournament, $this->makeAuction($org, array_merge([
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 100_000_000,
            'closed_bid_starts_at' => 8_000_000,
            'closed_bid_step' => 100_000,
            'closed_bid_max_pct_of_budget' => 70,
            'bid_rules' => [['from' => 0, 'to' => 200_000_000, 'increment' => 100_000]],
        ], $overrides))];
    }

    #[Test]
    public function the_threshold_still_flips_the_auction_to_closed_bidding(): void
    {
        [$org, $tournament, $auction] = $this->sealedAuction();
        $team = $this->makeTeam($org, 'Strikers', $tournament);
        $operator = $this->makeAuctionOperator($org);
        $player = $this->makeAuctionPlayer($auction, [
            'status' => 'on_auction',
            'current_price' => 7_900_000,
        ]);

        // A raise that reaches 8M. The rule that decides this has moved onto the model,
        // but its behaviour must be identical.
        $this->actingAs($operator)
            ->postJson(route('admin.auctions.players.addBid'), [
                'auctionId' => $auction->id,
                'playerID' => $player->id,
                'teamId' => $team->id,
            ])
            ->assertOk();

        $this->assertSame('closed', $auction->fresh()->bid_type);
    }

    #[Test]
    public function reaching_the_threshold_opens_a_round_with_the_leader_snapshotted(): void
    {
        [$org, $tournament, $auction] = $this->sealedAuction();
        $team = $this->makeTeam($org, 'Strikers', $tournament);
        $operator = $this->makeAuctionOperator($org);
        $player = $this->makeAuctionPlayer($auction, [
            'status' => 'on_auction',
            'current_price' => 7_900_000,
        ]);

        $this->actingAs($operator)
            ->postJson(route('admin.auctions.players.addBid'), [
                'auctionId' => $auction->id,
                'playerID' => $player->id,
                'teamId' => $team->id,
            ])
            ->assertOk();

        $round = AuctionClosedBidRound::where('auction_player_id', $player->id)->first();

        $this->assertNotNull($round, 'crossing the threshold must open a sealed round');
        $this->assertSame(AuctionClosedBidRound::STATE_PENDING, $round->state);
        $this->assertSame(1, $round->round_number);
        $this->assertSame(1, $round->attempt_no);

        // Who was leading when open bidding stopped — this is who takes the player if
        // nobody enters the sealed round.
        $this->assertSame($team->id, $round->leader_team_id);
        $this->assertSame(8_000_000.0, (float) $round->leader_amount);

        // The rules in force are snapshotted, so the round stays defensible even if the
        // auction is reconfigured afterwards.
        $this->assertSame(8_000_000.0, (float) $round->floor);
        $this->assertSame(100_000.0, (float) $round->step);
        $this->assertSame(70.0, (float) $round->max_pct_of_budget);

        // The player stays on_auction; the sealed phase is the round's state.
        $this->assertSame('on_auction', $player->fresh()->status);
        $this->assertSame($round->id, $player->fresh()->closed_bid_round_id);
    }

    #[Test]
    public function opening_a_round_twice_returns_the_same_round(): void
    {
        [$org, $tournament, $auction] = $this->sealedAuction(['bid_type' => 'closed']);
        $player = $this->makeAuctionPlayer($auction, [
            'status' => 'on_auction',
            'current_price' => 8_000_000,
        ]);

        $first = $this->closedBids()->openRoundFor($player->fresh(), $auction);
        $second = $this->closedBids()->openRoundFor($player->fresh(), $auction);

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second?->id, 'a second call must not create a duplicate round');
        $this->assertSame(1, AuctionClosedBidRound::where('auction_player_id', $player->id)->count());
    }

    #[Test]
    public function no_round_opens_while_the_auction_is_still_in_open_bidding(): void
    {
        [$org, $tournament, $auction] = $this->sealedAuction();
        $player = $this->makeAuctionPlayer($auction, [
            'status' => 'on_auction',
            'current_price' => 1_000_000,
        ]);

        $this->assertNull($this->closedBids()->openRoundFor($player, $auction));
        $this->assertSame(0, AuctionClosedBidRound::count());
    }

    #[Test]
    public function the_round_floor_is_snapped_onto_the_step_grid(): void
    {
        // 8.05M is not a multiple of 0.1M. Published as-is it would invert the browser's
        // own validity check, which tests `(value - min) % step`.
        [$org, $tournament, $auction] = $this->sealedAuction([
            'bid_type' => 'closed',
            'closed_bid_starts_at' => 8_050_000,
        ]);
        $player = $this->makeAuctionPlayer($auction, [
            'status' => 'on_auction',
            'current_price' => 8_050_000,
        ]);

        $round = $this->closedBids()->openRoundFor($player, $auction);

        $this->assertSame(8_100_000.0, (float) $round->floor);
    }

    #[Test]
    public function a_manual_override_suppresses_the_automatic_transition(): void
    {
        [$org, $tournament, $auction] = $this->sealedAuction(['bid_type_manually_overridden' => true]);

        // The organizer has taken control of the phase; the price rule must stay out of
        // the way, exactly as it did before the rule moved onto the model.
        $phase = $auction->applyAutoPhase(9_000_000);

        $this->assertFalse($phase['bid_type_changed']);
        $this->assertSame('open', $auction->fresh()->bid_type);
    }

    #[Test]
    public function choosing_to_run_the_room_offline_does_not_silence_the_sealed_threshold(): void
    {
        // One flag used to serve both axes, so an organizer taking bids by hand also
        // switched off the sealed-bid rule without asking for that.
        [$org, $tournament, $auction] = $this->sealedAuction([
            'open_bid_mode' => 'offline',
            'mode_manually_overridden' => true,
        ]);

        $phase = $auction->applyAutoPhase(9_000_000);

        $this->assertTrue($phase['bid_type_changed']);
        $this->assertSame('closed', $auction->fresh()->bid_type);
        $this->assertSame('offline', $auction->fresh()->open_bid_mode, 'the room is still offline');
    }

    #[Test]
    public function a_chosen_offline_mode_survives_the_next_player(): void
    {
        // Bid TYPE is a fact about a price, so it resets per player. Bid MODE is a fact
        // about the room — if the organizer is calling bids by hand, that stays true for
        // the whole session. putPlayerOnBid() used to reset both, so offline never stuck.
        [$org, $tournament, $auction] = $this->sealedAuction([
            'status' => 'running',
            'bid_type' => 'closed',
            'open_bid_mode' => 'offline',
            'mode_manually_overridden' => true,
        ]);

        $next = $this->makeAuctionPlayer($auction, ['status' => 'waiting']);

        $this->actingAs($this->makeAuctionOperator($org))
            ->postJson(route('admin.auction.organizer.api.player.onbid', $auction), [
                'auction_player_id' => $next->id,
            ])
            ->assertOk();

        $auction->refresh();

        $this->assertSame('offline', $auction->open_bid_mode, 'the organizer chose this');
        $this->assertTrue($auction->mode_manually_overridden);
        // The price-driven axis DOES reset — the new player starts at their base price.
        $this->assertSame('open', $auction->bid_type);
    }

    #[Test]
    public function an_offline_mode_the_price_rule_set_is_reset_by_the_next_player(): void
    {
        [$org, $tournament, $auction] = $this->sealedAuction([
            'status' => 'running',
            'open_bid_mode' => 'offline',
            'mode_manually_overridden' => false,
        ]);

        $next = $this->makeAuctionPlayer($auction, ['status' => 'waiting']);

        $this->actingAs($this->makeAuctionOperator($org))
            ->postJson(route('admin.auction.organizer.api.player.onbid', $auction), [
                'auction_player_id' => $next->id,
            ])
            ->assertOk();

        // Nobody chose this one, so it is the rule's to undo.
        $this->assertSame('online', $auction->fresh()->open_bid_mode);
    }

    #[Test]
    public function a_closed_phase_never_reopens_when_the_price_falls(): void
    {
        [$org, $tournament, $auction] = $this->sealedAuction(['bid_type' => 'closed']);

        $phase = $auction->applyAutoPhase(1_000_000);

        $this->assertFalse($phase['bid_type_changed']);
        $this->assertSame('closed', $auction->fresh()->bid_type, 'the transition is one-way');
    }

    #[Test]
    public function abandoning_keeps_the_round_for_the_record(): void
    {
        [$org, $tournament, $auction] = $this->sealedAuction(['bid_type' => 'closed']);
        $player = $this->makeAuctionPlayer($auction, [
            'status' => 'on_auction',
            'current_price' => 8_000_000,
        ]);

        $round = $this->closedBids()->openRoundFor($player, $auction);
        $this->closedBids()->abandonRoundsFor($player->fresh());

        $round->refresh();

        // Never deleted: rebidPlayer() drops bids and action logs, so this row is the
        // only durable trail of a disputed round.
        $this->assertSame(AuctionClosedBidRound::STATE_ABANDONED, $round->state);
        $this->assertNotNull($round->abandoned_at);
        $this->assertNull($player->fresh()->closed_bid_round_id);
    }

    #[Test]
    public function the_organizer_state_endpoint_reports_the_round(): void
    {
        [$org, $tournament, $auction] = $this->sealedAuction(['bid_type' => 'closed']);
        $player = $this->makeAuctionPlayer($auction, [
            'status' => 'on_auction',
            'current_price' => 8_000_000,
        ]);
        $this->closedBids()->openRoundFor($player, $auction);

        $this->actingAs($this->makeAuctionOperator($org))
            ->getJson(route('admin.auction.organizer.api.closed-bid.state', $auction))
            ->assertOk()
            ->assertJsonPath('closed_bid.active', true)
            ->assertJsonPath('closed_bid.state', AuctionClosedBidRound::STATE_PENDING)
            ->assertJsonPath('closed_bid.round_number', 1)
            ->assertJsonPath('closed_bid.total_rounds', 3)
            ->assertJsonPath('closed_bid.revealed', false);
    }

    #[Test]
    public function the_state_endpoint_needs_permission(): void
    {
        [$org, $tournament, $auction] = $this->sealedAuction();

        $this->actingAs($this->makePlainUser($org))
            ->getJson(route('admin.auction.organizer.api.closed-bid.state', $auction))
            ->assertForbidden();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\AuctionBid;
use App\Models\AuctionClosedBidEntry;
use App\Models\AuctionClosedBidRound;
use App\Services\Auction\ClosedBidService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * A sealed bid must actually be sealed.
 *
 * Before this, a closed bid was an ordinary `auction_bids` row that raised
 * `current_price` and stamped `current_bid_team_id` — and both of those are served by the
 * unauthenticated `/auction/{id}/active-player` feed that every rival team's bidding page
 * polls. The top sealed amount and the team behind it were therefore public within one
 * poll, and the bidding page additionally embedded every team's bid rows in its HTML.
 */
class ClosedBidSealingTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function closedBids(): ClosedBidService
    {
        return app(ClosedBidService::class);
    }

    /** A running sealed round with two teams invited, ready to accept bids. */
    private function scenario(array $auctionOverrides = [], array $playerOverrides = []): array
    {
        $org = $this->makeOrganization();
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

        $player = $this->makeAuctionPlayer($auction, array_merge([
            'status' => 'on_auction',
            'current_price' => 8_000_000,
        ], $playerOverrides));

        $round = $this->closedBids()->openRoundFor($player, $auction);
        $this->closedBids()->start($round, null);

        return compact('org', 'tournament', 'auction', 'teamA', 'teamB', 'player', 'round');
    }

    /** A user who manages the given team. */
    private function manager($org, $team)
    {
        $user = $this->makePlainUser($org);
        $team->users()->syncWithoutDetaching([$user->id => ['role' => 'Owner']]);

        return $user;
    }

    #[Test]
    public function a_sealed_bid_does_not_move_the_public_price_or_name_a_leader(): void
    {
        ['org' => $org, 'auction' => $auction, 'teamA' => $teamA, 'player' => $player, 'round' => $round] = $this->scenario();

        $this->closedBids()->accept($round, $teamA);
        $this->closedBids()->submit($round, $teamA, 9_500_000, null);

        $player->refresh();

        // The price stays frozen at the round's floor and the leader field still holds
        // whoever led the OPEN round — both were already public before the seal.
        $this->assertSame(8_000_000.0, (float) $player->current_price);

        $feed = $this->getJson("/auction/{$auction->id}/active-player")->assertOk();

        $feed->assertJsonPath('auctionPlayer.current_price', '8000000.00');
        $this->assertStringNotContainsString('9500000', $feed->getContent(), 'the sealed amount must not appear anywhere in the public feed');
        // Only that a round is running, and how many have bid — never who or how much.
        $feed->assertJsonPath('closed_bid.state', AuctionClosedBidRound::STATE_COLLECTING);
        $feed->assertJsonPath('closed_bid.submitted_count', 1);
        $feed->assertJsonMissingPath('closed_bid.entries');
    }

    #[Test]
    public function a_sealed_bid_writes_no_row_to_the_public_bid_log(): void
    {
        ['teamA' => $teamA, 'round' => $round] = $this->scenario();

        $this->closedBids()->accept($round, $teamA);
        $this->closedBids()->submit($round, $teamA, 9_500_000, null);

        // Losing sealed amounts must never reach auction_bids, which is read by the
        // report, the ticker and every bid-derived total.
        $this->assertSame(0, AuctionBid::count());
        // One row per invited team; only the one that bid is standing.
        $this->assertSame(1, $round->entries()->standing()->count());
    }

    #[Test]
    public function the_organizer_may_read_an_amount_but_the_public_never_can(): void
    {
        ['org' => $org, 'auction' => $auction, 'teamA' => $teamA, 'teamB' => $teamB, 'round' => $round] = $this->scenario();

        $this->closedBids()->accept($round, $teamA);
        $this->closedBids()->submit($round, $teamA, 9_500_000, null);

        $operator = $this->makeAuctionOperator($org);

        /*
         * The organizer's own payload carries the amount before the reveal; the PANEL masks it by
         * default behind a Show amounts switch. That is a question of what to paint — the panel is
         * routinely on a projector — rather than of what the person running the auction may know.
         * Withholding it entirely left an organizer unable to check a bid a team had queried, or
         * to confirm that an amount they had entered on a team's behalf actually landed.
         */
        $before = $this->actingAs($operator)
            ->getJson(route('admin.auction.organizer.api.closed-bid.state', $auction))
            ->assertOk()
            ->assertJsonPath('closed_bid.revealed', false)
            ->assertJsonPath('closed_bid.counts.submitted', 1);

        $this->assertStringContainsString('9500000', $before->getContent());

        // The PUBLIC feed is the line that must not move: counts only, never an amount.
        $public = $this->getJson("/auction/{$auction->id}/active-player")->assertOk();
        $this->assertStringNotContainsString('9500000', $public->getContent());

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.closed-bid.lock', $auction))
            ->assertOk();

        $after = $this->actingAs($operator)
            ->getJson(route('admin.auction.organizer.api.closed-bid.state', $auction))
            ->assertOk()
            ->assertJsonPath('closed_bid.revealed', true);

        $this->assertStringContainsString('9500000', $after->getContent(), 'amounts appear once revealed');
    }

    #[Test]
    public function the_bidding_page_embeds_only_the_viewing_teams_own_bids(): void
    {
        ['org' => $org, 'auction' => $auction, 'teamA' => $teamA, 'teamB' => $teamB, 'player' => $player] = $this->scenario();

        $managerA = $this->manager($org, $teamA);

        // Two open-bid rows from an earlier phase, one per team.
        $this->makeBid($player, $teamA, 1_111_111, $managerA);
        $this->makeBid($player, $teamB, 2_222_222, $managerA);

        $this->actingAs($managerA)
            ->get(route('team.auction.bidding.show', $auction))
            ->assertOk()
            ->assertSee('1111111', false)
            ->assertDontSee('2222222', false);
    }

    #[Test]
    public function the_team_screen_renders_the_sealed_round_controls(): void
    {
        ['org' => $org, 'auction' => $auction, 'teamA' => $teamA] = $this->scenario();

        $this->actingAs($this->manager($org, $teamA))
            ->get(route('team.auction.bidding.show', $auction))
            ->assertOk()
            ->assertSee('Sealed Round')
            // The stepper binds x-model to a plain string. The previous input bound
            // :value to a converted number, which recomputed on every keystroke and
            // erased a typed decimal point — 9.1 could not be entered at all.
            ->assertSee('x-model="sealedInputM"', false)
            ->assertSee('sealedStepViolation', false)
            // Both ceilings are shown, so a capped team knows it is the rule rather than
            // thinking it is broke.
            ->assertSee('Per-player cap')
            ->assertSee('Squad-reserve maximum')
            // And the superseded path is gone rather than merely hidden.
            ->assertDontSee('placeCustomBid', false);
    }

    #[Test]
    public function the_team_sealed_state_endpoint_never_carries_a_rivals_amount(): void
    {
        ['org' => $org, 'auction' => $auction, 'teamA' => $teamA, 'teamB' => $teamB, 'round' => $round] = $this->scenario();

        $this->closedBids()->accept($round, $teamB);
        $this->closedBids()->submit($round, $teamB, 9_700_000, null);

        $response = $this->actingAs($this->manager($org, $teamA))
            ->getJson(route('team.auction.bidding.api.closed-bid.state', $auction))
            ->assertOk()
            ->assertJsonPath('sealed.active', true);

        $this->assertStringNotContainsString('9700000', $response->getContent());
    }

    #[Test]
    public function an_illegal_amount_is_rejected_and_nothing_is_stored(): void
    {
        ['round' => $round, 'teamA' => $teamA] = $this->scenario();

        $this->closedBids()->accept($round, $teamA);

        $result = $this->closedBids()->submit($round, $teamA, 9_050_000, null);

        $this->assertFalse($result['handled']);
        // Both neighbours are named, because "invalid amount" is no use under a clock.
        $this->assertStringContainsString('9M', $result['message']);
        $this->assertStringContainsString('9.1M', $result['message']);

        // Rejected, not snapped: a silent correction is indistinguishable from the
        // system choosing a bid on the team's behalf.
        $this->assertNull($round->entries()->where('actual_team_id', $teamA->id)->first()->amount);
    }

    #[Test]
    public function a_bid_below_the_round_floor_is_rejected(): void
    {
        ['round' => $round, 'teamA' => $teamA] = $this->scenario();

        $this->closedBids()->accept($round, $teamA);
        $result = $this->closedBids()->submit($round, $teamA, 7_900_000, null);

        $this->assertFalse($result['handled']);
        $this->assertStringContainsString('8M', $result['message']);
    }

    #[Test]
    public function a_bid_above_the_per_player_cap_is_rejected(): void
    {
        // 70% of a 10M budget is 7M, and the sealed round opens at 6M so entry is
        // possible — but 8M is over the cap.
        ['round' => $round, 'teamA' => $teamA] = $this->scenario(
            ['max_budget_per_team' => 10_000_000, 'closed_bid_starts_at' => 6_000_000],
            ['current_price' => 6_000_000]
        );

        $this->closedBids()->accept($round, $teamA);
        $result = $this->closedBids()->submit($round, $teamA, 8_000_000, null);

        $this->assertFalse($result['handled']);
        $this->assertStringContainsString('one player', $result['message'], 'the message must name which ceiling bound');
    }

    #[Test]
    /**
     * A team's sealed bid is final once it is in.
     *
     * Re-submitting used to be allowed, so a manager could sit on the clock and revise their
     * amount — which is precisely the behaviour a sealed round exists to prevent: one committed
     * decision, made without knowing what anyone else has done. The organizer can still correct
     * an amount, deliberately and undoably; the team cannot change its own mind.
     */
    public function a_teams_second_submission_is_refused(): void
    {
        ['round' => $round, 'teamA' => $teamA] = $this->scenario();

        $this->closedBids()->accept($round, $teamA);
        $this->assertTrue($this->closedBids()->submit($round, $teamA, 9_000_000, null)['handled']);

        $second = $this->closedBids()->submit($round, $teamA, 9_400_000, null);

        $this->assertFalse($second['handled']);
        $this->assertStringContainsString('already in', $second['message']);

        // One standing amount per team per round, and it is the first one.
        $entries = $round->entries()->where('actual_team_id', $teamA->id)->get();

        $this->assertCount(1, $entries);
        $this->assertSame(9_000_000.0, (float) $entries->first()->amount);
    }

    #[Test]
    public function a_submission_after_the_round_is_locked_is_refused(): void
    {
        ['round' => $round, 'teamA' => $teamA] = $this->scenario();

        $this->closedBids()->accept($round, $teamA);
        $this->closedBids()->submit($round, $teamA, 9_000_000, null);
        $this->closedBids()->lockAndReveal($round, null);

        $result = $this->closedBids()->submit($round->fresh(), $teamA, 9_900_000, null);

        $this->assertFalse($result['handled']);
        $this->assertSame(9_000_000.0, (float) $round->entries()->where('actual_team_id', $teamA->id)->first()->amount);
    }

    #[Test]
    public function the_highest_standing_bid_wins_and_the_request_cannot_name_a_winner(): void
    {
        ['org' => $org, 'auction' => $auction, 'teamA' => $teamA, 'teamB' => $teamB, 'round' => $round, 'player' => $player] = $this->scenario();

        $this->closedBids()->accept($round, $teamA);
        $this->closedBids()->accept($round, $teamB);
        $this->closedBids()->submit($round, $teamA, 9_000_000, null);
        $this->closedBids()->submit($round, $teamB, 9_500_000, null);

        $operator = $this->makeAuctionOperator($org);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.closed-bid.lock', $auction))
            ->assertOk();

        // Post the LOSER's id and a made-up amount. The endpoint takes neither: the old
        // sealed award trusted both and verified neither against a bid ever placed.
        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.closed-bid.award', $auction), [
                'team_id' => $teamA->id,
                'amount' => 1_000_000,
            ])
            ->assertOk()
            ->assertJsonPath('handled', true);

        $player->refresh();

        $this->assertSame('sold', $player->status);
        $this->assertSame($teamB->id, $player->sold_to_team_id);
        $this->assertSame(9_500_000.0, (float) $player->final_price);
    }

    #[Test]
    public function awarding_writes_the_roster_pivot_and_the_team_user_row(): void
    {
        ['org' => $org, 'tournament' => $tournament, 'auction' => $auction, 'teamA' => $teamA, 'round' => $round, 'player' => $auctionPlayer] = $this->scenario();

        $playerUser = $this->makePlainUser($org);
        $playerModel = $this->makePlayer($org, ['user_id' => $playerUser->id]);
        $auctionPlayer->update(['player_id' => $playerModel->id]);

        $this->closedBids()->accept($round, $teamA);
        $this->closedBids()->submit($round, $teamA, 9_000_000, null);
        $this->closedBids()->lockAndReveal($round, null);
        $this->closedBids()->award($round->fresh(), $this->makeAuctionOperator($org));

        // Proof the award went through AuctionSaleService rather than a bespoke copy —
        // the previous sealed award wrote only player_mode and left the roster empty.
        $this->assertDatabaseHas('player_actual_team_tournament', [
            'player_id' => $playerModel->id,
            'tournament_id' => $tournament->id,
            'actual_team_id' => $teamA->id,
        ]);
        $this->assertDatabaseHas('actual_team_users', [
            'actual_team_id' => $teamA->id,
            'user_id' => $playerUser->id,
        ]);
        // And one audit bid row, so undo-a-sale and bid-derived totals keep working.
        $this->assertSame(1, AuctionBid::where('auction_player_id', $auctionPlayer->id)->count());
    }

    #[Test]
    public function locking_twice_is_a_no_op_rather_than_an_error(): void
    {
        ['org' => $org, 'auction' => $auction, 'round' => $round, 'teamA' => $teamA] = $this->scenario();

        $this->closedBids()->accept($round, $teamA);
        $this->closedBids()->submit($round, $teamA, 9_000_000, null);

        $operator = $this->makeAuctionOperator($org);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.closed-bid.lock', $auction))
            ->assertOk()
            ->assertJsonPath('handled', true);

        // Two organizer panels both pressing Lock is ordinary operation, not a mistake
        // to raise a red toast about.
        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.closed-bid.lock', $auction))
            ->assertOk()
            ->assertJsonPath('handled', false);
    }

    #[Test]
    public function a_withdrawal_hands_the_player_to_the_next_highest(): void
    {
        ['org' => $org, 'round' => $round, 'teamA' => $teamA, 'teamB' => $teamB, 'player' => $player] = $this->scenario();

        $this->closedBids()->accept($round, $teamA);
        $this->closedBids()->accept($round, $teamB);
        $this->closedBids()->submit($round, $teamA, 9_000_000, null);
        $this->closedBids()->submit($round, $teamB, 9_500_000, null);

        // The leader pulls out. No re-award path exists or is needed — the winner is the
        // top STANDING entry, so the same query simply returns a different row.
        $topEntry = $round->entries()->where('actual_team_id', $teamB->id)->first();
        $this->closedBids()->withdraw($topEntry, null, AuctionClosedBidEntry::ROLE_TEAM);

        $this->closedBids()->lockAndReveal($round->fresh(), null);
        $this->closedBids()->award($round->fresh(), $this->makeAuctionOperator($org));

        $player->refresh();

        $this->assertSame($teamA->id, $player->sold_to_team_id);
        $this->assertSame(9_000_000.0, (float) $player->final_price);
    }

    #[Test]
    public function a_team_that_cannot_reach_the_floor_cannot_enter(): void
    {
        // 70% of a 10M budget is 7M, below the 8M opening amount.
        ['round' => $round, 'teamA' => $teamA] = $this->scenario(['max_budget_per_team' => 10_000_000]);

        $result = $this->closedBids()->accept($round, $teamA);

        $this->assertFalse($result['handled'], 'entering a round it can never legally bid in only produces a dead form');
    }

    #[Test]
    public function a_round_with_no_entrants_reports_no_entries(): void
    {
        ['round' => $round] = $this->scenario();

        // force: the clock is what ends an empty round now — a premature manual lock is
        // refused so an organizer cannot discard a round they have not typed into yet.
        $result = $this->closedBids()->lockAndReveal($round, null, force: true);

        $this->assertTrue($result['handled']);
        $this->assertSame(AuctionClosedBidRound::STATE_NO_ENTRIES, $round->fresh()->state);
    }
}

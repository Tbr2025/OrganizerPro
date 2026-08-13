<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\AuctionClosedBidEntry;
use App\Models\AuctionClosedBidRound;
use App\Services\Auction\ClosedBidLotService;
use App\Services\Auction\ClosedBidService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Ties: the re-bid ladder, and the drawn lot that ends it.
 *
 * Before this a tie was resolved by `ORDER BY amount DESC` with no secondary sort, so the
 * winner was whichever row the database happened to return first — undetected, unrecorded
 * and impossible to defend afterwards.
 */
class ClosedBidTieTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function closedBids(): ClosedBidService
    {
        return app(ClosedBidService::class);
    }

    /** A running round with three teams, all able to afford a big bid. */
    private function scenario(array $auctionOverrides = []): array
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

        $teams = [
            'a' => $this->makeTeam($org, 'Alpha', $tournament),
            'b' => $this->makeTeam($org, 'Bravo', $tournament),
            'c' => $this->makeTeam($org, 'Charlie', $tournament),
        ];

        $player = $this->makeAuctionPlayer($auction, [
            'status' => 'on_auction',
            'current_price' => 8_000_000,
        ]);

        $round = $this->closedBids()->openRoundFor($player, $auction);
        $this->closedBids()->start($round, null);

        return compact('org', 'tournament', 'auction', 'teams', 'player', 'round');
    }

    #[Test]
    public function a_shared_top_amount_is_a_tie_not_a_winner(): void
    {
        ['round' => $round, 'teams' => $teams] = $this->scenario();

        foreach ([$teams['a'], $teams['b']] as $team) {
            $this->closedBids()->accept($round, $team);
            $this->closedBids()->submit($round, $team, 9_000_000, null);
        }
        $this->closedBids()->accept($round, $teams['c']);
        $this->closedBids()->submit($round, $teams['c'], 8_500_000, null);

        $this->closedBids()->lockAndReveal($round, null);
        $round->refresh();

        $this->assertSame(AuctionClosedBidRound::STATE_TIE, $round->state);
        $this->assertSame(9_000_000.0, (float) $round->tie_amount);
        $this->assertNull($round->winner_team_id, 'a tie must not silently pick somebody');
        $this->assertEqualsCanonicalizing([$teams['a']->id, $teams['b']->id], $round->tied_team_ids);
    }

    #[Test]
    public function only_the_tied_teams_are_invited_to_the_rebid(): void
    {
        ['round' => $round, 'teams' => $teams] = $this->scenario();

        foreach ([$teams['a'], $teams['b']] as $team) {
            $this->closedBids()->accept($round, $team);
            $this->closedBids()->submit($round, $team, 9_000_000, null);
        }
        $this->closedBids()->accept($round, $teams['c']);
        $this->closedBids()->submit($round, $teams['c'], 8_500_000, null);

        $this->closedBids()->lockAndReveal($round, null);
        $result = $this->closedBids()->startRebid($round->fresh(), null);

        $this->assertTrue($result['handled']);
        $child = $result['round'];

        $this->assertSame(2, $child->round_number);
        // Strictly above the tied amount, enforced by the floor rather than asked for in
        // the copy.
        $this->assertSame(9_100_000.0, (float) $child->floor);

        $entryFor = fn ($team) => $child->entries()->where('actual_team_id', $team->id)->first();

        $this->assertSame(AuctionClosedBidEntry::STATE_MUST_REBID, $entryFor($teams['a'])->state);
        $this->assertTrue($entryFor($teams['a'])->required);

        /*
         * Team C bid 8.5M and lost. It used to be invited back as MAY_OPT_IN, which let a team
         * that had already been outbid re-enter above the tie and win — so a round whose only
         * purpose is to separate two equal top bids could be taken by a third party. A tie-break
         * is between the teams that tied; nobody else has anything to break.
         */
        $this->assertNull($entryFor($teams['c']));
    }

    #[Test]
    public function a_team_that_declined_is_not_invited_back(): void
    {
        ['round' => $round, 'teams' => $teams] = $this->scenario();

        foreach ([$teams['a'], $teams['b']] as $team) {
            $this->closedBids()->accept($round, $team);
            $this->closedBids()->submit($round, $team, 9_000_000, null);
        }
        // Charlie leaves of its own accord.
        $this->closedBids()->decline($round, $teams['c']);

        $this->closedBids()->lockAndReveal($round, null);
        $child = $this->closedBids()->startRebid($round->fresh(), null)['round'];

        $this->assertNull(
            $child->entries()->where('actual_team_id', $teams['c']->id)->first(),
            'a team that chose to leave is not dragged back into the next round'
        );
    }

    #[Test]
    public function a_tied_team_the_admin_withdrew_is_still_invited_back(): void
    {
        ['round' => $round, 'teams' => $teams] = $this->scenario();

        foreach ([$teams['a'], $teams['b']] as $team) {
            $this->closedBids()->accept($round, $team);
            $this->closedBids()->submit($round, $team, 9_000_000, null);
        }

        $this->closedBids()->lockAndReveal($round->fresh(), null);

        /*
         * An admin withdrawal is a correction, not the team's own decision to leave — so a TIED
         * team that was withdrawn by an organizer still belongs in the tie-break they tied for.
         *
         * The distinction only survives for tied teams now: a non-tied team is not in the re-bid
         * at all, however it left, because a tie-break is between the teams that tied.
         */
        $entry = $round->fresh()->entries()->where('actual_team_id', $teams['b']->id)->first();
        $this->closedBids()->withdraw($entry, null, AuctionClosedBidEntry::ROLE_ADMIN);

        $child = $this->closedBids()->startRebid($round->fresh(), null)['round'];

        $this->assertSame(
            AuctionClosedBidEntry::STATE_MUST_REBID,
            $child->entries()->where('actual_team_id', $teams['b']->id)->first()?->state
        );
    }

    #[Test]
    public function a_rebid_at_or_below_the_tied_amount_is_refused(): void
    {
        ['round' => $round, 'teams' => $teams] = $this->scenario();

        foreach ([$teams['a'], $teams['b']] as $team) {
            $this->closedBids()->accept($round, $team);
            $this->closedBids()->submit($round, $team, 9_000_000, null);
        }

        $this->closedBids()->lockAndReveal($round, null);
        $child = $this->closedBids()->startRebid($round->fresh(), null)['round'];

        $result = $this->closedBids()->submit($child, $teams['a'], 9_000_000, null);

        $this->assertFalse($result['handled'], 'matching the tied amount again would just recreate the tie');
    }

    #[Test]
    public function a_required_team_that_does_not_rebid_drops_out(): void
    {
        ['round' => $round, 'teams' => $teams] = $this->scenario();

        foreach ([$teams['a'], $teams['b']] as $team) {
            $this->closedBids()->accept($round, $team);
            $this->closedBids()->submit($round, $team, 9_000_000, null);
        }

        $this->closedBids()->lockAndReveal($round, null);
        $child = $this->closedBids()->startRebid($round->fresh(), null)['round'];

        // Only Alpha comes back. Bravo's previous amount is deliberately NOT carried
        // forward — doing so would recreate the same tie every round and the ladder
        // would never terminate.
        $this->closedBids()->submit($child, $teams['a'], 9_500_000, null);
        $this->closedBids()->lockAndReveal($child->fresh(), null);

        $child->refresh();

        $this->assertSame(AuctionClosedBidRound::STATE_REVEALED, $child->state);
        $this->assertSame($teams['a']->id, $child->winner_team_id);
        $this->assertSame(
            AuctionClosedBidEntry::STATE_NO_ENTRY,
            $child->entries()->where('actual_team_id', $teams['b']->id)->first()->state
        );
    }

    #[Test]
    public function three_tied_rounds_go_to_the_lot(): void
    {
        ['round' => $round, 'teams' => $teams] = $this->scenario();
        $tied = [$teams['a'], $teams['b']];

        // Round 1.
        foreach ($tied as $team) {
            $this->closedBids()->accept($round, $team);
            $this->closedBids()->submit($round, $team, 9_000_000, null);
        }
        $this->closedBids()->lockAndReveal($round, null);
        $r2 = $this->closedBids()->startRebid($round->fresh(), null)['round'];

        // Round 2, tied again.
        foreach ($tied as $team) {
            $this->closedBids()->submit($r2, $team, 9_500_000, null);
        }
        $this->closedBids()->lockAndReveal($r2->fresh(), null);
        $r3 = $this->closedBids()->startRebid($r2->fresh(), null)['round'];

        $this->assertSame(3, $r3->round_number);

        // Round 3 — the last the ladder allows — still tied.
        foreach ($tied as $team) {
            $this->closedBids()->submit($r3, $team, 10_000_000, null);
        }
        $this->closedBids()->lockAndReveal($r3->fresh(), null);

        $this->assertSame(AuctionClosedBidRound::STATE_AWAITING_LOT, $r3->fresh()->state);
    }

    #[Test]
    public function a_fourth_rebid_round_cannot_be_started(): void
    {
        ['round' => $round, 'teams' => $teams] = $this->scenario(['closed_bid_max_rebid_rounds' => 0]);

        foreach ([$teams['a'], $teams['b']] as $team) {
            $this->closedBids()->accept($round, $team);
            $this->closedBids()->submit($round, $team, 9_000_000, null);
        }
        $this->closedBids()->lockAndReveal($round, null);

        // With no re-bids configured, the first tie goes straight to the lot.
        $this->assertSame(AuctionClosedBidRound::STATE_AWAITING_LOT, $round->fresh()->state);
        $this->assertFalse($this->closedBids()->startRebid($round->fresh(), null)['handled']);
    }

    #[Test]
    public function a_lot_can_be_recomputed_from_what_was_recorded(): void
    {
        ['org' => $org, 'round' => $round, 'teams' => $teams, 'player' => $player] = $this->scenario([
            'closed_bid_max_rebid_rounds' => 0,
        ]);

        foreach ([$teams['a'], $teams['b']] as $team) {
            $this->closedBids()->accept($round, $team);
            $this->closedBids()->submit($round, $team, 9_000_000, null);
        }
        $this->closedBids()->lockAndReveal($round, null);

        $result = $this->closedBids()->drawLot($round->fresh(), $this->makeAuctionOperator($org));
        $this->assertTrue($result['handled']);

        $round->refresh();

        // This is what makes the draw defensible: anybody holding the recorded seed and
        // candidate list can re-derive the winner and confirm it.
        $recomputed = app(ClosedBidLotService::class)->verify($round, $round->lot_candidates, $round->lot_seed);

        $this->assertSame($round->lot_winner_team_id, $recomputed);
        $this->assertSame(AuctionClosedBidRound::LOT_ALGORITHM, $round->lot_algorithm);
        $this->assertNotNull($round->lot_drawn_at);
        $this->assertEqualsCanonicalizing([$teams['a']->id, $teams['b']->id], $round->lot_candidates);

        // And the draw actually hands the player over, at the tied amount.
        $player->refresh();
        $this->assertSame('sold', $player->status);
        $this->assertSame($round->lot_winner_team_id, $player->sold_to_team_id);
        $this->assertSame(9_000_000.0, (float) $player->final_price);
        $this->assertSame(AuctionClosedBidRound::RESOLUTION_LOT, $round->resolution);
    }

    #[Test]
    public function the_lot_ignores_anything_the_client_sends(): void
    {
        ['org' => $org, 'auction' => $auction, 'round' => $round, 'teams' => $teams] = $this->scenario([
            'closed_bid_max_rebid_rounds' => 0,
        ]);

        foreach ([$teams['a'], $teams['b']] as $team) {
            $this->closedBids()->accept($round, $team);
            $this->closedBids()->submit($round, $team, 9_000_000, null);
        }
        $this->closedBids()->lockAndReveal($round, null);

        // A client-supplied seed or winner would let the caller choose the outcome.
        $this->actingAs($this->makeAuctionOperator($org))
            ->postJson(route('admin.auction.organizer.api.closed-bid.lot', $auction), [
                'seed' => str_repeat('0', 64),
                'winner_team_id' => $teams['c']->id,
                'index' => 0,
            ])
            ->assertOk();

        $round->refresh();

        $this->assertNotSame(str_repeat('0', 64), $round->lot_seed);
        $this->assertContains($round->lot_winner_team_id, [$teams['a']->id, $teams['b']->id]);
        $this->assertNotSame($teams['c']->id, $round->lot_winner_team_id, 'an untied team can never win a draw');
    }

    #[Test]
    public function drawing_twice_is_refused(): void
    {
        ['org' => $org, 'round' => $round, 'teams' => $teams] = $this->scenario(['closed_bid_max_rebid_rounds' => 0]);

        foreach ([$teams['a'], $teams['b']] as $team) {
            $this->closedBids()->accept($round, $team);
            $this->closedBids()->submit($round, $team, 9_000_000, null);
        }
        $this->closedBids()->lockAndReveal($round, null);

        $operator = $this->makeAuctionOperator($org);
        $this->assertTrue($this->closedBids()->drawLot($round->fresh(), $operator)['handled']);
        $this->assertFalse($this->closedBids()->drawLot($round->fresh(), $operator)['handled']);
    }

    #[Test]
    public function a_manual_resolution_must_name_a_tied_team_and_give_a_reason(): void
    {
        ['org' => $org, 'auction' => $auction, 'round' => $round, 'teams' => $teams] = $this->scenario([
            'closed_bid_max_rebid_rounds' => 0,
        ]);

        foreach ([$teams['a'], $teams['b']] as $team) {
            $this->closedBids()->accept($round, $team);
            $this->closedBids()->submit($round, $team, 9_000_000, null);
        }
        $this->closedBids()->lockAndReveal($round, null);

        $operator = $this->makeAuctionOperator($org);

        // A reason is required — an unexplained override is indistinguishable from an
        // arbitrary one, and this is the moment somebody will later ask why.
        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.closed-bid.resolve-manual', $auction), [
                'team_id' => $teams['a']->id,
            ])
            ->assertStatus(422);

        // An untied team cannot be handed the player.
        $this->assertFalse(
            $this->closedBids()->resolveManual($round->fresh(), $teams['c']->id, 'Because I say so', $operator)['handled']
        );

        $ok = $this->closedBids()->resolveManual($round->fresh(), $teams['b']->id, 'Coin toss in the hall', $operator);

        $this->assertTrue($ok['handled']);
        $this->assertSame(AuctionClosedBidRound::RESOLUTION_MANUAL, $round->fresh()->resolution);
    }

    #[Test]
    public function a_rebid_round_nobody_returns_to_goes_to_the_lot(): void
    {
        ['round' => $round, 'teams' => $teams] = $this->scenario();

        foreach ([$teams['a'], $teams['b']] as $team) {
            $this->closedBids()->accept($round, $team);
            $this->closedBids()->submit($round, $team, 9_000_000, null);
        }
        $this->closedBids()->lockAndReveal($round, null);
        $child = $this->closedBids()->startRebid($round->fresh(), null)['round'];

        // Neither tied team comes back. They still want the player — they just failed to
        // re-bid — so this must not become "nobody wanted him".
        $this->closedBids()->lockAndReveal($child->fresh(), null);

        $child->refresh();

        $this->assertSame(AuctionClosedBidRound::STATE_AWAITING_LOT, $child->state);
        $this->assertEqualsCanonicalizing([$teams['a']->id, $teams['b']->id], $child->tied_team_ids);
    }
}

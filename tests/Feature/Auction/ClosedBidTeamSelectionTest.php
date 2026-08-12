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
 * Choosing which teams enter a sealed round, before Open Entry rather than after.
 *
 * Open Entry used to invite every participating team unconditionally. An expensive
 * player does not always need every team weighing in, and there was no way to leave one
 * out before the board was already built around it.
 */
class ClosedBidTeamSelectionTest extends TestCase
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
            'bid_type' => 'closed',
            'max_budget_per_team' => 100_000_000,
            'closed_bid_starts_at' => 8_000_000,
        ]);

        $alpha = $this->makeTeam($org, 'Alpha', $tournament);
        $bravo = $this->makeTeam($org, 'Bravo', $tournament);
        $charlie = $this->makeTeam($org, 'Charlie', $tournament);

        $player = $this->makeAuctionPlayer($auction, [
            'status' => 'on_auction',
            'current_price' => 8_000_000,
        ]);

        $round = app(ClosedBidService::class)->openRoundFor($player, $auction);

        return [$org, $auction, $round, $alpha, $bravo, $charlie];
    }

    #[Test]
    public function omitting_the_list_invites_everyone_as_before(): void
    {
        [$org, $auction, $round, $alpha, $bravo, $charlie] = $this->scenario();

        $result = app(ClosedBidService::class)->openEntry($round);

        $this->assertTrue($result['handled']);
        $this->assertSame(
            [$alpha->id, $bravo->id, $charlie->id],
            AuctionClosedBidEntry::where('auction_closed_bid_round_id', $round->id)
                ->orderBy('actual_team_id')->pluck('actual_team_id')->all()
        );
    }

    #[Test]
    public function a_chosen_subset_invites_only_those_teams(): void
    {
        [$org, $auction, $round, $alpha, $bravo, $charlie] = $this->scenario();

        $result = app(ClosedBidService::class)->openEntry($round, null, [$alpha->id, $charlie->id]);

        $this->assertTrue($result['handled']);
        $this->assertSame(
            [$alpha->id, $charlie->id],
            AuctionClosedBidEntry::where('auction_closed_bid_round_id', $round->id)
                ->orderBy('actual_team_id')->pluck('actual_team_id')->all()
        );

        // Bravo was left out on purpose, not lost — no entry exists for them at all,
        // rather than an entry in some "excluded" state that would need explaining.
        $this->assertFalse(
            AuctionClosedBidEntry::where('auction_closed_bid_round_id', $round->id)
                ->where('actual_team_id', $bravo->id)->exists()
        );
    }

    #[Test]
    public function an_empty_selection_is_refused_rather_than_opening_an_empty_round(): void
    {
        [$org, $auction, $round] = $this->scenario();

        $result = app(ClosedBidService::class)->openEntry($round, null, []);

        $this->assertFalse($result['handled']);
        $this->assertSame(AuctionClosedBidRound::STATE_PENDING, $round->fresh()->state);
    }

    #[Test]
    public function a_team_not_eligible_for_this_auction_cannot_be_invited_by_id(): void
    {
        [$org, $auction, $round, $alpha] = $this->scenario();

        // A team from a different tournament entirely -- the id could not have come from
        // this auction's own team list, but the server must not trust it anyway.
        $foreignOrg = $this->makeOrganization('Foreign Org');
        $foreignTournament = $this->makeTournament($foreignOrg);
        $foreign = $this->makeTeam($foreignOrg, 'Intruder', $foreignTournament);

        $result = app(ClosedBidService::class)->openEntry($round, null, [$alpha->id, $foreign->id]);

        $this->assertTrue($result['handled']);
        $this->assertSame(
            [$alpha->id],
            AuctionClosedBidEntry::where('auction_closed_bid_round_id', $round->id)
                ->pluck('actual_team_id')->all()
        );
    }

    #[Test]
    public function the_endpoint_accepts_and_scopes_the_selection(): void
    {
        [$org, $auction, $round, $alpha, $bravo, $charlie] = $this->scenario();
        $operator = $this->makeAuctionOperator($org);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.closed-bid.open-entry', $auction), [
                'auction_player_id' => $round->auctionPlayer->id,
                'team_ids' => [$bravo->id],
            ])
            ->assertOk()
            ->assertJsonPath('handled', true);

        $this->assertSame(
            [$bravo->id],
            AuctionClosedBidEntry::where('auction_closed_bid_round_id', $round->id)
                ->pluck('actual_team_id')->all()
        );
    }

    /*
     * Leaving a team out is only real if the team's own panel cannot undo it. Every
     * team-initiated action used to reach a firstOrCreate on the entry, so an excluded
     * team could have walked into the round by pressing its own buttons.
     */

    #[Test]
    public function an_excluded_team_cannot_accept_its_way_into_the_round(): void
    {
        [$org, $auction, $round, $alpha, $bravo] = $this->scenario();
        $service = app(ClosedBidService::class);

        $service->openEntry($round, null, [$alpha->id]);

        $result = $service->accept($round->fresh(), $bravo);

        $this->assertFalse($result['handled']);
        $this->assertFalse(
            AuctionClosedBidEntry::where('auction_closed_bid_round_id', $round->id)
                ->where('actual_team_id', $bravo->id)->exists()
        );
    }

    #[Test]
    public function an_excluded_team_cannot_submit_an_amount_even_with_acceptance_off(): void
    {
        [$org, $auction, $round, $alpha, $bravo] = $this->scenario();
        $auction->update(['closed_bid_requires_acceptance' => false]);
        $service = app(ClosedBidService::class);

        $service->openEntry($round, null, [$alpha->id]);
        $service->start($round->fresh());

        // Acceptance off means there is no "accept first" gate to stop them — being
        // outside the round has to be enough on its own.
        $result = $service->submit($round->fresh(), $bravo, 9_000_000);

        $this->assertFalse($result['handled']);
        $this->assertSame('Your team is not in this round.', $result['message']);
        $this->assertFalse(
            AuctionClosedBidEntry::where('auction_closed_bid_round_id', $round->id)
                ->where('actual_team_id', $bravo->id)->exists()
        );
    }

    #[Test]
    public function an_excluded_team_is_told_it_is_not_in_the_round(): void
    {
        [$org, $auction, $round, $alpha, $bravo] = $this->scenario();
        $service = app(ClosedBidService::class);

        $service->openEntry($round, null, [$alpha->id]);
        $player = $round->auctionPlayer->fresh();

        $this->assertTrue($service->stateForTeam($auction, $player, $alpha->id)['invited']);
        $this->assertFalse($service->stateForTeam($auction, $player, $bravo->id)['invited']);
    }

    #[Test]
    public function before_entry_opens_nobody_is_marked_as_left_out(): void
    {
        [$org, $auction, $round, $alpha] = $this->scenario();

        // Pending means "not invited yet", which the panel must not read as exclusion —
        // no entries exist for anyone at this point.
        $state = app(ClosedBidService::class)->stateForTeam($auction, $round->auctionPlayer, $alpha->id);

        $this->assertSame(AuctionClosedBidRound::STATE_PENDING, $state['state']);
        $this->assertTrue($state['invited']);
    }

    #[Test]
    public function a_rebid_round_keeps_the_selection_and_does_not_readmit_excluded_teams(): void
    {
        [$org, $auction, $round, $alpha, $bravo, $charlie] = $this->scenario();
        $service = app(ClosedBidService::class);

        $service->openEntry($round, null, [$alpha->id, $charlie->id]);
        $service->start($round->fresh());
        $service->accept($round->fresh(), $alpha);
        $service->accept($round->fresh(), $charlie);
        $service->submit($round->fresh(), $alpha, 9_000_000);
        $service->submit($round->fresh(), $charlie, 9_000_000);
        $service->lockAndReveal($round->fresh());

        $tied = $round->fresh();
        $this->assertSame(AuctionClosedBidRound::STATE_TIE, $tied->state);

        $result = $service->startRebid($tied);
        $this->assertTrue($result['handled']);

        // The re-bid is built from the parent's entries, so the team left out of the first
        // round stays out of the tie-break rather than reappearing in it.
        $child = AuctionClosedBidRound::where('parent_round_id', $tied->id)->firstOrFail();
        $this->assertSame(
            [$alpha->id, $charlie->id],
            AuctionClosedBidEntry::where('auction_closed_bid_round_id', $child->id)
                ->orderBy('actual_team_id')->pluck('actual_team_id')->all()
        );
    }

    #[Test]
    public function starting_without_opening_entry_first_still_invites_everyone(): void
    {
        [$org, $auction, $round, $alpha, $bravo, $charlie] = $this->scenario();

        // "Start" is allowed to skip Open Entry entirely -- the pre-existing shortcut this
        // change must not narrow. No selection was ever made, so it falls back to everyone.
        $result = app(ClosedBidService::class)->start($round->fresh());

        $this->assertTrue($result['handled']);
        $this->assertSame(
            3,
            AuctionClosedBidEntry::where('auction_closed_bid_round_id', $round->id)->count()
        );
    }

    #[Test]
    public function the_organizer_can_step_back_to_team_selection(): void
    {
        [$org, $auction, $round, $alpha] = $this->scenario();

        app(ClosedBidService::class)->openEntry($round, null, [$alpha->id]);
        $this->assertSame('entry_open', $round->fresh()->state);

        $result = app(ClosedBidService::class)->reopenSelection($round->fresh(), null);

        $this->assertTrue($result['handled'], $result['message'] ?? '');
        $this->assertSame('pending', $round->fresh()->state);
        // The untouched invitation goes with it, so the next selection starts clean.
        $this->assertSame(0, $round->fresh()->entries()->count());
        // And no stale clock is left for the next Start to inherit.
        $this->assertNull($round->fresh()->timer_started_at);
    }

    #[Test]
    public function stepping_back_is_refused_once_a_team_has_submitted_a_bid(): void
    {
        [$org, $auction, $round, $alpha] = $this->scenario();

        app(ClosedBidService::class)->openEntry($round, null, [$alpha->id]);
        app(ClosedBidService::class)->start($round->fresh());
        app(ClosedBidService::class)->accept($round->fresh(), $alpha);
        app(ClosedBidService::class)->submit($round->fresh(), $alpha, 8_100_000, null);

        /*
         * An amount is a team's own act and cannot be discarded silently — that has to be undone
         * deliberately. Acceptance is not the line: it is cheap to do again, and refusing on it
         * would block an ordinary correction for no benefit.
         */
        $result = app(ClosedBidService::class)->reopenSelection($round->fresh(), null);

        $this->assertFalse($result['handled']);
        $this->assertStringContainsString('already submitted', $result['message']);
        $this->assertSame(1, $round->fresh()->entries()->count());
    }

    #[Test]
    public function stepping_back_is_allowed_when_a_team_has_only_accepted(): void
    {
        [$org, $auction, $round, $alpha] = $this->scenario();

        app(ClosedBidService::class)->openEntry($round, null, [$alpha->id]);
        app(ClosedBidService::class)->start($round->fresh());
        app(ClosedBidService::class)->accept($round->fresh(), $alpha);

        $result = app(ClosedBidService::class)->reopenSelection($round->fresh(), null);

        $this->assertTrue($result['handled'], $result['message'] ?? '');
        $this->assertSame('pending', $round->fresh()->state);
        $this->assertSame(0, $round->fresh()->entries()->count());
    }

    #[Test]
    public function a_round_that_ran_out_with_nothing_in_it_can_still_go_back(): void
    {
        [$org, $auction, $round, $alpha] = $this->scenario();

        app(ClosedBidService::class)->openEntry($round, null, [$alpha->id]);
        app(ClosedBidService::class)->start($round->fresh());
        $round->fresh()->update(['state' => \App\Models\AuctionClosedBidRound::STATE_NO_ENTRIES]);

        /*
         * Awarding the open leader used to be the only way out of here, so a round that ran out
         * with the wrong teams in it had to be RESOLVED rather than corrected.
         */
        $result = app(ClosedBidService::class)->reopenSelection($round->fresh(), null);

        $this->assertTrue($result['handled'], $result['message'] ?? '');
        $this->assertSame('pending', $round->fresh()->state);
    }

    #[Test]
    public function open_entry_marks_the_round_as_the_teams_to_run(): void
    {
        [$org, $auction, $round, $alpha] = $this->scenario();

        // `opened_at` cannot answer this — starting without picking teams auto-invites everyone
        // and sets it too. The panel drops its amount fields on this flag, so that a board the
        // organizer is only reading has no stepper to fat-finger a bid into.
        $this->assertNull($round->entry_opened_at);

        app(ClosedBidService::class)->openEntry($round, null, [$alpha->id]);
        $this->assertNotNull($round->fresh()->entry_opened_at);

        $state = app(ClosedBidService::class)->stateForOrganizer($auction, $round->fresh()->auctionPlayer);
        $this->assertTrue($state['entry_opened']);
    }

    #[Test]
    public function starting_without_open_entry_leaves_the_organizer_entering(): void
    {
        [$org, $auction, $round] = $this->scenario();

        // Straight to Start: everyone is invited, but the organizer never handed the round over,
        // so the amount fields stay.
        app(ClosedBidService::class)->start($round, null);

        $this->assertNull($round->fresh()->entry_opened_at);
        $this->assertFalse(
            app(ClosedBidService::class)->stateForOrganizer($auction, $round->fresh()->auctionPlayer)['entry_opened']
        );
    }

    #[Test]
    public function stepping_back_un_hands_the_round(): void
    {
        [$org, $auction, $round, $alpha] = $this->scenario();

        app(ClosedBidService::class)->openEntry($round, null, [$alpha->id]);
        app(ClosedBidService::class)->reopenSelection($round->fresh(), null);

        // Otherwise a stepped-back round keeps claiming its entry was opened, and the fields stay
        // hidden for a round the organizer is about to run themselves.
        $this->assertNull($round->fresh()->entry_opened_at);
    }

    #[Test]
    public function starting_a_round_again_does_not_leave_it_looking_locked(): void
    {
        [$org, $auction, $round, $alpha] = $this->scenario();

        $svc = app(ClosedBidService::class);
        $svc->openEntry($round, null, [$alpha->id]);
        $svc->start($round->fresh());
        $svc->accept($round->fresh(), $alpha);
        $svc->submit($round->fresh(), $alpha, 8_100_000, null);
        $svc->lockAndReveal($round->fresh(), null);

        $this->assertNotNull($round->fresh()->locked_at);

        // Undo the reveal by hand the way an organizer does, then run the round again.
        $round->fresh()->update(['state' => \App\Models\AuctionClosedBidRound::STATE_PENDING]);
        $round->fresh()->entries()->delete();
        $svc->start($round->fresh(), null, [$alpha->id]);

        /*
         * The reveal stamps used to survive this, leaving a round that read `collecting` while
         * still carrying locked_at. Every guard that asks "is this locked?" tests locked_at rather
         * than the state — submit(), adjust(), extendTimer() — so the round refused bids AND
         * refused to be extended while presenting itself as open. From the room's side the sealed
         * box simply did nothing.
         */
        $fresh = $round->fresh();
        $this->assertSame('collecting', $fresh->state);
        $this->assertNull($fresh->locked_at);
        $this->assertNull($fresh->revealed_at);
        $this->assertNull($fresh->winner_team_id);

        // And the things that were blocked now work.
        $this->assertTrue($svc->extendTimer($fresh, null)['handled']);
        $svc->accept($round->fresh(), $alpha);
        $this->assertTrue($svc->submit($round->fresh(), $alpha, 8_200_000, null)['handled']);
    }
}

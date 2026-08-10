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
}

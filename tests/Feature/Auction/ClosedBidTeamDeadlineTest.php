<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Services\Auction\ClosedBidService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * The sealed round's deadline, as the team experiences it.
 *
 * The server has always refused a late submission, but the team could not SEE the clock —
 * so the first anyone knew of the deadline was a rejection, and a manager could spend the
 * last seconds of a round typing into a box that was never going to be accepted. The clock
 * is now on their screen, and this pins down both halves: the countdown is delivered and
 * rendered, and the refusal still holds however the client behaves.
 */
class ClosedBidTeamDeadlineTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function closedBids(): ClosedBidService
    {
        return app(ClosedBidService::class);
    }

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
            'closed_bid_timer_seconds' => 60,
            'bid_rules' => [['from' => 0, 'to' => 200_000_000, 'increment' => 100_000]],
        ], $auctionOverrides));

        $team = $this->makeTeam($org, 'Alpha', $tournament);
        $player = $this->makeAuctionPlayer($auction, ['status' => 'on_auction', 'current_price' => 8_000_000]);

        $round = $this->closedBids()->openRoundFor($player, $auction);
        $this->closedBids()->start($round, null);

        $user = $this->makePlainUser($org);
        $team->users()->syncWithoutDetaching([$user->id => ['role' => 'Owner']]);

        return compact('org', 'auction', 'team', 'player', 'round', 'user');
    }

    #[Test]
    public function the_team_is_told_how_long_is_left(): void
    {
        ['auction' => $auction, 'team' => $team, 'round' => $round, 'user' => $user] = $this->scenario();

        $this->closedBids()->accept($round, $team);

        $state = $this->actingAs($user)
            ->getJson(route('team.auction.bidding.api.closed-bid.state', $auction))
            ->assertOk()
            ->json('sealed');

        $this->assertTrue($state['active']);
        $this->assertSame(60, $state['timer']['limit']);
        $this->assertFalse($state['timer']['expired']);
        $this->assertNotNull($state['timer']['remaining']);
    }

    #[Test]
    public function the_countdown_is_on_the_teams_screen(): void
    {
        ['auction' => $auction, 'user' => $user] = $this->scenario();

        $html = $this->actingAs($user)
            ->get(route('team.auction.bidding.show', $auction))
            ->assertOk()
            ->getContent();

        // The clock ticks locally between polls but is re-seeded from the server each poll,
        // so a wrong client clock is corrected rather than trusted.
        $this->assertStringContainsString('syncSealedTimer', $html);
        $this->assertStringContainsString('sealedSecondsLeft', $html);
        $this->assertStringContainsString('sealedClockText', $html);
    }

    #[Test]
    public function the_expired_open_clock_is_not_shown_over_a_live_sealed_round(): void
    {
        ['auction' => $auction, 'user' => $user] = $this->scenario();

        $html = $this->actingAs($user)
            ->get(route('team.auction.bidding.show', $auction))
            ->assertOk()
            ->getContent();

        /*
         * The open round's clock has expired by definition once a sealed round exists — that
         * expiry is what opens it — so leaving it on screen put a red TIME UP directly above an
         * I ACCEPT button that was, correctly, still enabled. Read literally, the page said the
         * round was over while inviting entry to it.
         *
         * Only `collecting` was excluded, so `entry_open` — the stage where a team decides
         * whether to take part at all — showed exactly that contradiction. Both stages now
         * suppress it.
         */
        $condition = html_entity_decode($html);

        $this->assertMatchesRegularExpression(
            "/x-show=\"\(timerSeconds > 0 \|\| timerExpired\) && !\(sealed\.active && \['entry_open', 'collecting'\]\.includes\(sealed\.state\)\)\"/",
            $condition,
            'The open clock must be hidden for a sealed round that is entry_open as well as one collecting.'
        );

        /*
         * Acceptance is now a per-auction choice, and this auction has not turned it on — so the
         * accept step must be GATED, not merely present. The panel is inside `x-if` on
         * `sealed.requires_acceptance`, which the server sets from the auction, so asserting the
         * absence of the markup would only prove the buttons had been deleted again.
         *
         * What matters is that a team in an auction without the setting never sees it, and the
         * gate is the thing that guarantees that.
         */
        $this->assertStringContainsString('sealed.requires_acceptance', $html);
        $this->assertFalse($auction->closedBidRequiresAcceptance());

        // Both answers exist where it IS on: a team with only ACCEPT has no way to say it is out,
        // and the round then waits on a clock for somebody who has already decided.
        $this->assertStringContainsString('sealedAccept()', $html);
        $this->assertStringContainsString('sealedDecline()', $html);

        // The amount box still refuses a submission once the deadline has passed, which is the
        // guarantee this test is really about.
        $this->assertStringContainsString('sealedExpired', $condition);
    }

    #[Test]
    public function a_submission_after_the_deadline_is_refused_however_the_client_behaves(): void
    {
        ['auction' => $auction, 'team' => $team, 'round' => $round, 'user' => $user] = $this->scenario([
            'closed_bid_timer_seconds' => 30,
        ]);

        $this->closedBids()->accept($round, $team);

        // The round started 31 seconds ago: past the 30s limit, whatever the page shows.
        $round->forceFill(['timer_started_at' => now()->subSeconds(31)])->save();

        $response = $this->actingAs($user)->postJson(
            route('team.auction.bidding.api.closed-bid.submit', $auction),
            ['amount' => 9_000_000]
        );

        // 422, not a silent no-op: the page has to be able to tell the manager why, and a
        // 200 with a message in the body is too easy for a caller to ignore.
        $response->assertStatus(422);
        $this->assertStringContainsString('Time is up', (string) $response->json('error'));

        $this->assertDatabaseMissing('auction_closed_bid_entries', [
            'auction_closed_bid_round_id' => $round->id,
            'actual_team_id' => $team->id,
            'amount' => 9_000_000,
        ]);
    }

    #[Test]
    public function a_submission_before_the_deadline_is_accepted(): void
    {
        ['auction' => $auction, 'team' => $team, 'round' => $round, 'user' => $user] = $this->scenario();

        $this->closedBids()->accept($round, $team);
        $round->forceFill(['timer_started_at' => now()->subSeconds(5)])->save();

        $response = $this->actingAs($user)->postJson(
            route('team.auction.bidding.api.closed-bid.submit', $auction),
            ['amount' => 9_000_000]
        );

        // The success path answers `success`, the refusal path answers `handled: false` —
        // asserted on the shape each one actually returns rather than a shared key.
        $response->assertOk();
        $this->assertTrue($response->json('success'));
        $this->assertSame(9_000_000, $response->json('sealed.my_entry.amount'));
    }

    #[Test]
    public function the_amount_is_masked_on_screen_by_default(): void
    {
        ['auction' => $auction, 'user' => $user] = $this->scenario();

        $html = $this->actingAs($user)
            ->get(route('team.auction.bidding.show', $auction))
            ->assertOk()
            ->getContent();

        // Managers type this on a laptop at a shared table. Hidden by default is the only
        // setting that protects the first bid of a round, before anyone presses anything.
        $this->assertStringContainsString('sealedAmountHidden: true', $html);

        /*
         * Blurred, not `type=password`. A password field cannot carry step/min/max, so all three
         * had to be nulled out whenever the mask was on — the spinner stopped stepping and the
         * browser stopped enforcing the floor at exactly the moment somebody was typing a figure
         * they could not read. The blur clears on focus and hover, so it is legible to whoever is
         * typing and unreadable across a table.
         */
        $this->assertStringContainsString("sealedAmountHidden ? 'is-masked' : ''", $html);
        $this->assertStringContainsString('.sealed-amount.is-masked', $html);
        $this->assertStringNotContainsString("'password' : 'number'", $html);
    }

    #[Test]
    public function the_team_is_shown_what_it_can_afford_while_it_is_bidding(): void
    {
        ['auction' => $auction, 'user' => $user] = $this->scenario();

        $html = $this->actingAs($user)
            ->get(route('team.auction.bidding.show', $auction))
            ->assertOk()
            ->getContent();

        // These figures used to appear only on the accept step, so by the time a manager was
        // typing they could not tell whether they were short of money or holding places back.
        $this->assertStringContainsString('You can bid up to', $html);
        $this->assertStringContainsString('still to fill', $html);
    }

    #[Test]
    public function an_offline_auction_still_lets_a_team_enter_its_own_sealed_bid(): void
    {
        ['auction' => $auction, 'team' => $team, 'round' => $round, 'user' => $user] = $this->scenario([
            'open_bid_mode' => 'offline',
        ]);

        $this->closedBids()->accept($round, $team);

        /*
         * Offline describes OPEN bidding — the organizer calling raises across the room. A sealed
         * bid is a private number, and having the organizer collect six of them by hand defeats
         * the privacy the round exists for.
         */
        $this->actingAs($user)->postJson(
            route('team.auction.bidding.api.closed-bid.submit', $auction),
            ['amount' => 9_000_000]
        )->assertOk();

        $this->assertSame(
            9_000_000.0,
            (float) $round->entries()->where('actual_team_id', $team->id)->value('amount')
        );
    }

    #[Test]
    public function the_panel_says_who_is_entering_the_amounts(): void
    {
        ['org' => $org, 'auction' => $auction] = $this->scenario(['open_bid_mode' => 'offline']);

        $html = $this->actingAs($this->makeSuperadmin($org))
            ->get(route('admin.auction.organizer.panel', $auction))
            ->assertOk()
            ->getContent();

        // A full board of AWAITING is never left to be explained by an absence: the console says
        // who enters the amounts and what Awaiting is waiting for.
        $this->assertStringContainsString('Teams enter their own sealed amounts', $html);
        $this->assertStringContainsString('has not accepted yet', $html);
    }

    #[Test]
    public function a_team_cannot_revise_a_bid_it_has_already_submitted(): void
    {
        ['auction' => $auction, 'team' => $team, 'round' => $round, 'user' => $user] = $this->scenario();

        $this->closedBids()->accept($round, $team);

        $this->actingAs($user)->postJson(
            route('team.auction.bidding.api.closed-bid.submit', $auction),
            ['amount' => 9_000_000]
        )->assertOk();

        // Sitting on the clock and revising is the behaviour a sealed round exists to prevent.
        $second = $this->actingAs($user)->postJson(
            route('team.auction.bidding.api.closed-bid.submit', $auction),
            ['amount' => 9_500_000]
        );

        $second->assertStatus(422);
        $this->assertStringContainsString('already in', (string) $second->json('error'));

        $entry = $round->entries()->where('actual_team_id', $team->id)->first();
        $this->assertSame(9_000_000.0, (float) $entry->amount);
    }

    #[Test]
    public function the_entry_box_closes_once_a_bid_is_in(): void
    {
        ['auction' => $auction, 'user' => $user] = $this->scenario();

        $html = $this->actingAs($user)
            ->get(route('team.auction.bidding.show', $auction))
            ->assertOk()
            ->getContent();

        // The screen must not offer an edit the server will refuse — the button used to read
        // CHANGE SEALED BID and the change was accepted.
        $this->assertStringContainsString("this.sealedEntryState === 'submitted'", $html);
        $this->assertStringNotContainsString("'accepted', 'submitted', 'must_rebid', 'may_opt_in'", $html);
    }

    #[Test]
    public function an_admin_previewing_a_team_sees_that_teams_sealed_round(): void
    {
        ['org' => $org, 'auction' => $auction, 'team' => $team, 'round' => $round] = $this->scenario();

        $this->closedBids()->openEntry($round, null, [$team->id]);

        /*
         * The bidding page has always honoured ?team_id=, but the polls behind it did not — they
         * resolved the team from the logged-in user, and a superadmin belongs to none. So every
         * sealed poll came back with no entry and `invited: false`, and the page told an INVITED
         * team that the round was between the teams the organizer had selected. The sealed box
         * could never appear in preview at all.
         */
        $sealed = $this->actingAs($this->makeSuperadmin($org))
            ->getJson(route('team.auction.bidding.api.closed-bid.state', $auction) . '?team_id=' . $team->id)
            ->assertOk()
            ->json('sealed');

        $this->assertTrue($sealed['invited']);
        $this->assertNotNull($sealed['my_entry']);
    }

    #[Test]
    public function a_team_manager_cannot_read_another_teams_round_by_asking(): void
    {
        ['org' => $org, 'auction' => $auction, 'team' => $team, 'round' => $round, 'user' => $user] = $this->scenario();

        $other = $this->makeTeam($org, 'Bravo', $auction->tournament);

        // Bravo is in the round and has committed an amount; the manager of the other team has not.
        $this->closedBids()->accept($round, $other);
        $this->closedBids()->submit($round, $other, 9_400_000, null);

        // The preview path is gated on the admin roles, so a query parameter is not a way into
        // somebody else's round — the answer must still describe the caller's OWN team.
        $sealed = $this->actingAs($user)
            ->getJson(route('team.auction.bidding.api.closed-bid.state', $auction) . '?team_id=' . $other->id)
            ->assertOk()
            ->json('sealed');

        $this->assertNull($sealed['my_entry']['amount'] ?? null, "Bravo's amount must not leak to another team");
        $this->assertNotSame('submitted', $sealed['my_entry']['state'] ?? null);
    }

    #[Test]
    public function a_team_started_without_accepting_can_still_accept(): void
    {
        ['auction' => $auction, 'user' => $user] = $this->scenario();

        $html = $this->actingAs($user)
            ->get(route('team.auction.bidding.show', $auction))
            ->assertOk()
            ->getContent();

        // The organizer can press Start while a team is still `invited`. The accept panel only
        // rendered for entry_open, so that team had no way to accept and no bid box either — a
        // dead end while the clock ran down.
        $this->assertStringContainsString("['entry_open','collecting'].includes(sealed.state)", $html);
        $this->assertStringContainsString("['invited','may_opt_in'].includes(sealedEntryState)", $html);
    }

    #[Test]
    public function an_organizer_may_enter_from_a_teams_screen_and_it_is_attributed_to_them(): void
    {
        ['org' => $org, 'auction' => $auction, 'team' => $team, 'round' => $round] = $this->scenario();

        $this->closedBids()->openEntry($round, null, [$team->id]);
        $this->closedBids()->start($round->fresh());
        $this->closedBids()->accept($round->fresh(), $team);

        /*
         * Refusing this bought no integrity: the organizer could already enter for a team from the
         * panel, so the same act by the same person was blocked only on a different screen — and
         * in a room where a manager cannot reach their own device, that block lands mid-round.
         *
         * What matters is that it is attributed and reversible, so it is recorded as an ADMIN act
         * exactly as the panel's control is.
         */
        $this->actingAs($this->makeSuperadmin($org))->postJson(
            route('team.auction.bidding.api.closed-bid.submit', $auction) . '?team_id=' . $team->id,
            ['amount' => 9_000_000]
        )->assertOk();

        $entry = $round->fresh()->entries()->where('actual_team_id', $team->id)->first();

        $this->assertSame(9_000_000.0, (float) $entry->amount);
        // The trail is what makes this safe — an organizer's entry is visible as one, and undoable.
        $this->assertSame(1, (int) $entry->adjusted_count);
        $this->assertNotEmpty($entry->adjustments);
    }

    #[Test]
    public function a_team_manager_still_cannot_act_through_another_teams_screen(): void
    {
        ['org' => $org, 'auction' => $auction, 'team' => $team, 'round' => $round, 'user' => $user] = $this->scenario();

        $other = $this->makeTeam($org, 'Bravo', $auction->tournament);
        $this->closedBids()->openEntry($round, null, [$team->id, $other->id]);
        $this->closedBids()->start($round->fresh());

        // The organizer exemption is gated on the admin roles; a manager adding a query parameter
        // is still just themselves.
        $this->actingAs($user)->postJson(
            route('team.auction.bidding.api.closed-bid.accept', $auction) . '?team_id=' . $other->id
        );

        $this->assertNotSame(
            'accepted',
            $round->fresh()->entries()->where('actual_team_id', $other->id)->value('state'),
            'a manager must not act on another team behalf'
        );
    }

    #[Test]
    public function the_clock_is_visible_before_the_decision_it_should_inform(): void
    {
        ['auction' => $auction, 'user' => $user] = $this->scenario();

        $html = $this->actingAs($user)
            ->get(route('team.auction.bidding.show', $auction))
            ->assertOk()
            ->getContent();

        /*
         * The countdown lived inside the bid box, which only renders once the team CAN bid — so a
         * team had no idea how long it had. It is now shown to any invited team while the round
         * is collecting. (The accept step it originally informed has since been removed — a team
         * that wants the player simply enters an amount — but the clock still has to be visible
         * for the amount to be entered against.)
         */
        $this->assertStringContainsString("sealed.invited !== false && sealed.state === 'collecting'", $html);
        // The deadline still governs the amount box; it is the accept step that has gone.
        $this->assertStringContainsString('sealedExpired', $html);
        $this->assertStringContainsString('Sealed bid completed', $html);
    }

    #[Test]
    public function an_organizer_working_from_a_team_screen_can_accept_and_submit(): void
    {
        ['org' => $org, 'auction' => $auction, 'team' => $team, 'round' => $round] = $this->scenario();

        $this->closedBids()->openEntry($round, null, [$team->id]);
        $this->closedBids()->start($round->fresh());

        /*
         * The GET polls carried ?team_id= and the ACTIONS did not — so an organizer could see the
         * round from a team's screen and then be told to "open this from a team's screen" the
         * moment they pressed Accept, because the request arrived with no team on it at all.
         */
        $admin = $this->makeSuperadmin($org);
        $url = route('team.auction.bidding.api.closed-bid.accept', $auction) . '?team_id=' . $team->id;

        $this->actingAs($admin)->postJson($url)->assertOk();
        $this->assertSame('accepted', $round->fresh()->entries()->where('actual_team_id', $team->id)->value('state'));

        $this->actingAs($admin)->postJson(
            route('team.auction.bidding.api.closed-bid.submit', $auction) . '?team_id=' . $team->id,
            ['amount' => 9_100_000]
        )->assertOk();

        $this->assertSame(9_100_000.0, (float) $round->fresh()->entries()->where('actual_team_id', $team->id)->value('amount'));
    }

    #[Test]
    public function the_organizer_may_correct_an_amount_the_team_itself_cannot_change(): void
    {
        ['org' => $org, 'auction' => $auction, 'team' => $team, 'round' => $round, 'user' => $user] = $this->scenario();

        $this->closedBids()->accept($round, $team);
        $this->closedBids()->submit($round, $team, 9_000_000, null);

        // One bid per team per round, and the team cannot revise it.
        $this->assertFalse($this->closedBids()->submit($round->fresh(), $team, 9_500_000, null)['handled']);

        // The organizer still can — deliberately, attributed, and undoable.
        $this->actingAs($this->makeSuperadmin($org))->postJson(
            route('team.auction.bidding.api.closed-bid.submit', $auction) . '?team_id=' . $team->id,
            ['amount' => 9_500_000]
        )->assertOk();

        $this->assertSame(9_500_000.0, (float) $round->fresh()->entries()->where('actual_team_id', $team->id)->value('amount'));
    }
}

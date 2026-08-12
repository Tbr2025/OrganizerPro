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
        $this->assertStringContainsString("sealedAmountHidden ? 'password' : 'number'", $html);
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
}

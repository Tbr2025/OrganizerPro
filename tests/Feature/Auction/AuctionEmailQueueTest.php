<?php

namespace Tests\Feature\Auction;

use App\Jobs\FlushAuctionEmails;
use App\Models\Auction;
use App\Models\AuctionPendingEmail;
use App\Services\Auction\AuctionMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Auction mail used to be sent synchronously on the sell path, so every sale blocked the
 * room on SMTP and any rehearsal emailed real players. It now goes through an outbox that
 * is released when the auction finishes.
 */
class AuctionEmailQueueTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    /** Sell a player and return the auction player row. */
    private function sell(Auction $auction, $team, $operator, float $amount = 500)
    {
        $ap = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);
        $this->makeBid($ap, $team, $amount, $operator);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.player.sell', $auction), ['auction_player_id' => $ap->id])
            ->assertOk();

        return $ap;
    }

    #[Test]
    public function a_sale_holds_its_emails_instead_of_sending_them(): void
    {
        Mail::fake();

        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 100000,
            'email_dispatch' => Auction::EMAIL_DEFERRED,
        ]);
        $operator = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'Strikers', $tournament);

        $this->sell($auction, $team, $operator);

        // Nothing left the building during the auction.
        Mail::assertNothingSent();

        // Both the sold notice and the welcome card are waiting.
        $types = AuctionPendingEmail::where('auction_id', $auction->id)->pluck('type')->all();
        $this->assertContains(AuctionPendingEmail::TYPE_SOLD, $types);
        $this->assertContains(AuctionPendingEmail::TYPE_WELCOME_CARD, $types);
        $this->assertSame(
            count($types),
            AuctionPendingEmail::where('auction_id', $auction->id)->pending()->count()
        );
    }

    #[Test]
    public function ending_the_auction_queues_the_flush(): void
    {
        Mail::fake();
        Bus::fake();

        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 100000,
            'status' => 'running',
        ]);
        $operator = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'Strikers', $tournament);

        $this->sell($auction, $team, $operator);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.end', $auction))
            ->assertOk()
            ->assertJsonPath('queued_emails', 2);

        // Queued, so ending the auction returns without waiting on the mail server.
        Bus::assertDispatched(FlushAuctionEmails::class);
    }

    #[Test]
    public function the_flush_sends_everything_that_was_held(): void
    {
        Mail::fake();

        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 100000,
        ]);
        $operator = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'Strikers', $tournament);

        $playerUser = $this->makePlainUser($org);
        $player = $this->makePlayer($org, ['user_id' => $playerUser->id]);
        $ap = $this->makeAuctionPlayer($auction, ['player' => $player, 'status' => 'on_auction']);
        $this->makeBid($ap, $team, 500, $operator);
        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.player.sell', $auction), ['auction_player_id' => $ap->id])
            ->assertOk();

        $result = app(AuctionMailService::class)->flush($auction->fresh());

        // The sold notice goes out; the welcome card needs a registration, which this
        // player has none of, so it is recorded as failed rather than silently dropped.
        $this->assertSame(1, $result['sent']);
        Mail::assertSent(\App\Mail\PlayerSoldMail::class);

        $this->assertSame(0, AuctionPendingEmail::where('auction_id', $auction->id)->pending()->count());
        $this->assertNotNull($auction->fresh()->emails_flushed_at);
    }

    #[Test]
    public function test_mode_records_emails_but_never_sends_them(): void
    {
        Mail::fake();

        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 100000,
            'email_test_mode' => true,
        ]);
        $operator = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'Strikers', $tournament);

        $this->sell($auction, $team, $operator);

        Mail::assertNothingSent();

        // Recorded as skipped, so the organizer can still see what a real run would send.
        $rows = AuctionPendingEmail::where('auction_id', $auction->id)->get();
        $this->assertTrue($rows->isNotEmpty());
        $this->assertTrue($rows->every(fn ($r) => $r->status === AuctionPendingEmail::STATUS_SKIPPED));

        // And a flush still sends nothing.
        app(AuctionMailService::class)->flush($auction->fresh());
        Mail::assertNothingSent();
    }

    #[Test]
    public function disabling_notifications_raises_nothing_at_all(): void
    {
        Mail::fake();

        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 100000,
            'notifications_enabled' => false,
        ]);
        $operator = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'Strikers', $tournament);

        $this->sell($auction, $team, $operator);

        Mail::assertNothingSent();
        // Off means off — nothing is even recorded.
        $this->assertSame(0, AuctionPendingEmail::where('auction_id', $auction->id)->count());
    }

    #[Test]
    public function immediate_dispatch_sends_as_the_sale_happens(): void
    {
        Mail::fake();

        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 100000,
            'email_dispatch' => Auction::EMAIL_IMMEDIATE,
        ]);
        $operator = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'Strikers', $tournament);

        $playerUser = $this->makePlainUser($org);
        $player = $this->makePlayer($org, ['user_id' => $playerUser->id]);
        $ap = $this->makeAuctionPlayer($auction, ['player' => $player, 'status' => 'on_auction']);
        $this->makeBid($ap, $team, 500, $operator);
        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.player.sell', $auction), ['auction_player_id' => $ap->id])
            ->assertOk();

        Mail::assertSent(\App\Mail\PlayerSoldMail::class);
        $this->assertSame(0, AuctionPendingEmail::where('auction_id', $auction->id)->pending()->count());
    }

    #[Test]
    public function an_unsold_player_gets_a_held_notification(): void
    {
        Mail::fake();

        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $operator = $this->makeAuctionOperator($org);
        $ap = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.player.pass', $auction), ['auction_player_id' => $ap->id])
            ->assertOk();

        Mail::assertNothingSent();
        $this->assertSame(1, AuctionPendingEmail::where('auction_id', $auction->id)
            ->where('type', AuctionPendingEmail::TYPE_UNSOLD)->pending()->count());
    }

    #[Test]
    public function one_bad_address_does_not_strand_the_rest(): void
    {
        Mail::fake();

        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 1000000,
        ]);
        $operator = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'Strikers', $tournament);

        // One player has no user account, so their mail cannot be delivered.
        $orphan = $this->makePlayer($org, ['user_id' => null]);
        $apOrphan = $this->makeAuctionPlayer($auction, ['player' => $orphan, 'status' => 'on_auction']);
        $this->makeBid($apOrphan, $team, 500, $operator);
        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.player.sell', $auction), ['auction_player_id' => $apOrphan->id])
            ->assertOk();

        // The next one is fine.
        $good = $this->makePlayer($org, ['user_id' => $this->makePlainUser($org)->id]);
        $apGood = $this->makeAuctionPlayer($auction, ['player' => $good, 'status' => 'on_auction']);
        $this->makeBid($apGood, $team, 500, $operator);
        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.player.sell', $auction), ['auction_player_id' => $apGood->id])
            ->assertOk();

        $result = app(AuctionMailService::class)->flush($auction->fresh());

        // A failure is recorded against its own row and the batch carries on.
        $this->assertGreaterThanOrEqual(1, $result['sent']);
        $this->assertGreaterThanOrEqual(1, $result['failed']);
        $this->assertSame(0, AuctionPendingEmail::where('auction_id', $auction->id)->pending()->count());
        $this->assertNotNull(
            AuctionPendingEmail::where('auction_id', $auction->id)
                ->where('status', AuctionPendingEmail::STATUS_FAILED)->first()?->error
        );
    }

    #[Test]
    public function the_send_now_button_releases_held_mail(): void
    {
        Mail::fake();

        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 100000,
        ]);
        $operator = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'Strikers', $tournament);

        $player = $this->makePlayer($org, ['user_id' => $this->makePlainUser($org)->id]);
        $ap = $this->makeAuctionPlayer($auction, ['player' => $player, 'status' => 'on_auction']);
        $this->makeBid($ap, $team, 500, $operator);
        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.player.sell', $auction), ['auction_player_id' => $ap->id])
            ->assertOk();

        // The safety net for a stopped queue worker: release it by hand.
        $this->actingAs($operator)
            ->post(route('admin.auctions.emails.flush', $auction))
            ->assertRedirect()->assertSessionHas('success');

        Mail::assertSent(\App\Mail\PlayerSoldMail::class);
        $this->assertSame(0, AuctionPendingEmail::where('auction_id', $auction->id)->pending()->count());
    }

    #[Test]
    public function the_settings_are_saved_from_the_wizard(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $operator = $this->makeAuctionOperator($org);

        $this->actingAs($operator)->put(route('admin.auctions.update', $auction), [
            'name' => $auction->name,
            'organization_id' => $org->id,
            'tournament_id' => $tournament->id,
            'status' => 'scheduled',
            'max_budget_per_team' => 10000000,
            'base_price' => 100000,
            'bid_type' => 'open',
            'bid_timer_seconds' => 30,
            'bid_rules' => [['from' => 0, 'to' => 20000000, 'increment' => 100000]],
            'notifications_enabled' => '1',
            'email_test_mode' => '1',
            'email_dispatch' => 'immediate',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $auction->refresh();
        $this->assertTrue($auction->notifications_enabled);
        $this->assertTrue($auction->email_test_mode);
        $this->assertSame('immediate', $auction->email_dispatch);
    }

    #[Test]
    public function the_outbox_screen_lists_what_test_mode_suppressed(): void
    {
        Mail::fake();

        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 100000,
            'email_test_mode' => true,
        ]);
        $operator = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'Strikers', $tournament);

        $this->sell($auction, $team, $operator);

        // Test mode is only worth having if the organizer can read back what would have gone.
        $this->actingAs($operator)
            ->get(route('admin.auctions.emails.index', $auction))
            ->assertOk()
            ->assertSee('Test mode is on')
            ->assertSee('Not sent');

        Mail::assertNothingSent();
    }

    #[Test]
    public function the_outbox_screen_filters_by_status(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $operator = $this->makeAuctionOperator($org);

        AuctionPendingEmail::create([
            'auction_id' => $auction->id,
            'type' => AuctionPendingEmail::TYPE_SOLD,
            'status' => AuctionPendingEmail::STATUS_FAILED,
            'error' => 'Mailbox unavailable',
        ]);
        AuctionPendingEmail::create([
            'auction_id' => $auction->id,
            'type' => AuctionPendingEmail::TYPE_UNSOLD,
            'status' => AuctionPendingEmail::STATUS_SENT,
        ]);

        $this->actingAs($operator)
            ->get(route('admin.auctions.emails.index', $auction) . '?status=failed')
            ->assertOk()
            ->assertSee('Mailbox unavailable')
            ->assertDontSee('Unsold notification');
    }

    #[Test]
    public function failed_emails_can_be_requeued(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $operator = $this->makeAuctionOperator($org);

        $row = AuctionPendingEmail::create([
            'auction_id' => $auction->id,
            'type' => AuctionPendingEmail::TYPE_SOLD,
            'status' => AuctionPendingEmail::STATUS_FAILED,
            'error' => 'Connection timed out',
        ]);

        $this->actingAs($operator)
            ->post(route('admin.auctions.emails.retry', $auction), ['scope' => 'failed'])
            ->assertRedirect();

        $row->refresh();
        $this->assertSame(AuctionPendingEmail::STATUS_PENDING, $row->status);
        $this->assertNull($row->error, 'The stale failure reason must be cleared on requeue.');
    }

    #[Test]
    public function the_outbox_is_scoped_to_its_own_auction(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $mine = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $other = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $operator = $this->makeAuctionOperator($org);

        AuctionPendingEmail::create([
            'auction_id' => $other->id,
            'type' => AuctionPendingEmail::TYPE_SOLD,
            'status' => AuctionPendingEmail::STATUS_FAILED,
            'error' => 'Belongs to the other auction',
        ]);

        // Requeuing one auction must not touch another's rows.
        $this->actingAs($operator)
            ->post(route('admin.auctions.emails.retry', $mine), ['scope' => 'failed'])
            ->assertRedirect();

        $this->assertSame(
            AuctionPendingEmail::STATUS_FAILED,
            AuctionPendingEmail::where('auction_id', $other->id)->first()->status
        );

        $this->actingAs($operator)
            ->get(route('admin.auctions.emails.index', $mine))
            ->assertOk()
            ->assertDontSee('Belongs to the other auction');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\AuctionClosedBidEntry;
use App\Services\Auction\ClosedBidService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Online or offline: who enters the bids for the whole auction.
 *
 * Online, team managers bid from their own dashboards. Offline, everybody is in one room and
 * only the admin and organizer enter anything — including sealed amounts. Sealed bidding is a
 * sub-phase of either, not an alternative to them.
 *
 * The mode could not be stated at all before this: `open_bid_mode` appeared nowhere in either
 * wizard and the migration defaults it to `online`, so every auction was born online and an
 * offline room had to be set up by pressing the panel's Offline toggle each session.
 */
class AuctionBiddingModeTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    /** The smallest payload the update endpoint accepts. */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Auction',
            'status' => 'scheduled',
            'max_budget_per_team' => 100_000_000,
            'base_price' => 1_000_000,
            'bid_type' => 'open',
            'bid_timer_seconds' => 30,
            'bid_rules' => [['from' => 0, 'to' => 200_000_000, 'increment' => 100_000]],
        ], $overrides);
    }

    /** store() additionally requires a window; update() does not. */
    private function createPayload(array $overrides = []): array
    {
        return $this->payload(array_merge([
            'start_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'end_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
        ], $overrides));
    }

    /*
    |--------------------------------------------------------------------------
    | Declaring the mode
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function an_auction_can_be_created_offline(): void
    {
        $org = $this->makeOrganization();
        // store() refuses outright unless the organization's package includes auctions.
        $org->update(['auction_enabled' => true]);
        $tournament = $this->makeTournament($org);

        $this->actingAs($this->makeAuctionOperator($org, ['auction.create', 'auction.edit', 'auction.view']))
            ->post(route('admin.auctions.store'), $this->createPayload([
                'organization_id' => $org->id,
                'tournament_id' => $tournament->id,
                'open_bid_mode' => 'offline',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $auction = \App\Models\Auction::where('organization_id', $org->id)->latest('id')->first();

        $this->assertSame('offline', $auction->open_bid_mode);

        // Declaring it offline is the same statement the panel's Offline toggle makes, so
        // the price-band rule cannot quietly move the mode later.
        $this->assertTrue((bool) $auction->mode_manually_overridden);
    }

    #[Test]
    public function an_auction_created_online_keeps_the_price_band_rule(): void
    {
        $org = $this->makeOrganization();
        // store() refuses outright unless the organization's package includes auctions.
        $org->update(['auction_enabled' => true]);
        $tournament = $this->makeTournament($org);

        $this->actingAs($this->makeAuctionOperator($org, ['auction.create', 'auction.edit', 'auction.view']))
            ->post(route('admin.auctions.store'), $this->createPayload([
                'organization_id' => $org->id,
                'tournament_id' => $tournament->id,
                'open_bid_mode' => 'online',
                'online_bid_limit_from' => 100_000,
                'online_bid_limit_to' => 5_000_000,
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $auction = \App\Models\Auction::where('organization_id', $org->id)->latest('id')->first();

        $this->assertSame('online', $auction->open_bid_mode);

        /*
         * Choosing Online must NOT set the override flag. An online auction is allowed to
         * hand over to the organizer above a price, and setting the flag here would switch
         * that rule off for every auction saved through the wizard.
         */
        $this->assertFalse((bool) $auction->mode_manually_overridden);

        $phase = $auction->applyAutoPhase(6_000_000);
        $this->assertTrue($phase['open_bid_mode_changed']);
        $this->assertSame('offline', $auction->fresh()->open_bid_mode);
    }

    #[Test]
    public function an_edit_that_omits_the_mode_leaves_it_alone(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'open_bid_mode' => 'offline',
            'mode_manually_overridden' => true,
        ]);

        // The minimal-payload case AuctionUpdatePoolsTest protects: a save that never
        // mentions the mode must not reset it to the enum's default.
        $this->actingAs($this->makeAuctionOperator($org))
            ->put(route('admin.auctions.update', $auction), $this->payload([
                'organization_id' => $org->id,
                'tournament_id' => $tournament->id,
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('offline', $auction->fresh()->open_bid_mode);
        $this->assertTrue((bool) $auction->fresh()->mode_manually_overridden);
    }

    #[Test]
    public function the_mode_must_be_one_of_the_two(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);

        $this->actingAs($this->makeAuctionOperator($org))
            ->put(route('admin.auctions.update', $auction), $this->payload([
                'organization_id' => $org->id,
                'tournament_id' => $tournament->id,
                'open_bid_mode' => 'hybrid',
            ]))
            ->assertSessionHasErrors('open_bid_mode');
    }

    /*
    |--------------------------------------------------------------------------
    | Who may bid in each mode
    |--------------------------------------------------------------------------
    */

    /** An auction with a team manager attached, ready to bid. */
    private function biddableAuction(string $mode): array
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'status' => 'running',
            'open_bid_mode' => $mode,
            'max_budget_per_team' => 100_000_000,
            'closed_bid_starts_at' => 8_000_000,
            'closed_bid_step' => 100_000,
            'closed_bid_max_pct_of_budget' => 100,
            'bid_rules' => [['from' => 0, 'to' => 200_000_000, 'increment' => 100_000]],
        ]);

        $team = $this->makeTeam($org, 'Strikers', $tournament);
        $manager = $this->makePlainUser($org);
        $team->users()->attach($manager->id, ['role' => 'Owner']);

        $player = $this->makeAuctionPlayer($auction, [
            'status' => 'on_auction',
            'current_price' => 1_000_000,
        ]);

        return compact('org', 'tournament', 'auction', 'team', 'manager', 'player');
    }

    #[Test]
    public function a_team_cannot_place_an_open_bid_in_an_offline_auction(): void
    {
        ['auction' => $auction, 'manager' => $manager, 'player' => $player] = $this->biddableAuction('offline');

        $this->actingAs($manager)
            ->postJson(route('team.auction.bidding.api.place-bid', $auction), [
                'auction_player_id' => $player->id,
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function a_team_can_submit_a_sealed_amount_in_an_offline_auction(): void
    {
        $ctx = $this->biddableAuction('offline');
        $auction = $ctx['auction'];
        $player = $ctx['player'];

        $auction->update(['bid_type' => 'closed']);
        $player->update(['current_price' => 8_000_000]);

        $service = app(ClosedBidService::class);
        $round = $service->openRoundFor($player->fresh(), $auction->fresh());
        $service->openEntry($round);
        $service->start($round->fresh());

        /*
         * Offline does NOT lock a team out of a SEALED round, and this is the test that says so.
         *
         * Offline describes OPEN bidding — the organizer calls the room aloud, and placeBid()
         * still refuses a team's own open bid for exactly that reason. A sealed round is the
         * opposite kind of thing: one private number, entered without seeing anyone else's, which
         * is what a manager should type on their own device even in a room-called auction. Making
         * the organizer collect six sealed amounts by hand defeated the privacy the round is for.
         */
        $this->actingAs($ctx['manager'])
            ->postJson(route('team.auction.bidding.api.closed-bid.accept', $auction))
            ->assertOk();

        $this->actingAs($ctx['manager'])
            ->postJson(route('team.auction.bidding.api.closed-bid.submit', $auction), ['amount' => 9_000_000])
            ->assertOk();

        $this->assertSame(
            9_000_000.0,
            (float) AuctionClosedBidEntry::where('auction_closed_bid_round_id', $round->id)
                ->where('actual_team_id', $ctx['team']->id)
                ->value('amount'),
            'a sealed amount entered by the team is recorded in offline mode too'
        );

        // The OPEN path is unchanged: an offline auction still refuses a team's own raise.
        $this->actingAs($ctx['manager'])
            ->postJson(route('team.auction.bidding.api.place-bid', $auction), ['auction_player_id' => $player->id])
            ->assertStatus(422);
    }

    #[Test]
    public function a_team_can_submit_a_sealed_amount_in_an_online_auction(): void
    {
        $ctx = $this->biddableAuction('online');
        $auction = $ctx['auction'];
        $player = $ctx['player'];

        $auction->update(['bid_type' => 'closed']);
        $player->update(['current_price' => 8_000_000]);

        $service = app(ClosedBidService::class);
        $round = $service->openRoundFor($player->fresh(), $auction->fresh());
        $service->openEntry($round);
        $service->start($round->fresh());

        // The guard above must not close the door on the mode it exists for. A team accepts
        // the round's conditions before it may bid — that gate is the same in both modes and
        // is deliberately walked here rather than switched off, so this exercises the real
        // path a manager takes on their phone.
        $this->actingAs($ctx['manager'])
            ->postJson(route('team.auction.bidding.api.closed-bid.accept', $auction))
            ->assertOk();

        $this->actingAs($ctx['manager'])
            ->postJson(route('team.auction.bidding.api.closed-bid.submit', $auction), ['amount' => 9_000_000])
            ->assertOk();

        $this->assertSame(
            9_000_000.0,
            (float) AuctionClosedBidEntry::where('auction_closed_bid_round_id', $round->id)
                ->where('actual_team_id', $ctx['team']->id)
                ->value('amount')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | The organizer running an offline room
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function the_organizer_can_bid_for_a_team_in_an_offline_auction(): void
    {
        $ctx = $this->biddableAuction('offline');

        // The click-a-logo gesture's server side. The panel used to refuse this outright
        // when the mode was offline, which left every logo dead on the one screen driving
        // the room.
        $this->actingAs($this->makeAuctionOperator($ctx['org']))
            ->postJson(route('admin.auctions.players.addBid'), [
                'auctionId' => $ctx['auction']->id,
                'playerID' => $ctx['player']->id,
                'teamId' => $ctx['team']->id,
            ])
            ->assertOk();

        // The opening bid takes the 1M base itself; the ladder starts from the second bid.
        $this->assertSame(1_000_000.0, (float) $ctx['player']->fresh()->current_price);
        $this->assertSame($ctx['team']->id, $ctx['player']->fresh()->current_bid_team_id);
    }

    #[Test]
    public function the_organizer_bid_still_respects_the_squad_reserve_in_offline(): void
    {
        $ctx = $this->biddableAuction('offline');
        $auction = $ctx['auction'];

        // Ten slots still to fill at a million each: the team cannot commit its whole purse
        // to one player. Going live in offline must not bypass the rule that protects a squad.
        $auction->update([
            'max_budget_per_team' => 11_000_000,
            'min_squad_size' => 11,
            'min_price_per_player' => 1_000_000,
        ]);
        /*
         * Ceiling is exactly 11M - (10 x 1M) = 1M, and a bid landing ON the ceiling is legal —
         * so start where the next rung crosses it rather than reaches it.
         *
         * A standing bidder is needed for that to be a rung at all: the opening bid on a player
         * nobody has bid for takes the standing price rather than adding an increment, which
         * would land exactly ON the legal ceiling and prove nothing. It belongs to another team
         * because a team may not outbid itself.
         */
        $opener = $this->makeTeam($ctx['org'], 'Openers');
        $ctx['player']->update([
            'current_price' => 1_000_000,
            'current_bid_team_id' => $opener->id,
        ]);

        $this->actingAs($this->makeAuctionOperator($ctx['org']))
            ->postJson(route('admin.auctions.players.addBid'), [
                'auctionId' => $auction->id,
                'playerID' => $ctx['player']->id,
                'teamId' => $ctx['team']->id,
            ])
            ->assertStatus(422);

        $this->assertSame(1_000_000.0, (float) $ctx['player']->fresh()->current_price);
    }

    #[Test]
    public function the_organizer_can_enter_a_sealed_amount_in_an_offline_auction(): void
    {
        $ctx = $this->biddableAuction('offline');
        $auction = $ctx['auction'];
        $player = $ctx['player'];

        $auction->update(['bid_type' => 'closed']);
        $player->update(['current_price' => 8_000_000]);

        $service = app(ClosedBidService::class);
        $round = $service->openRoundFor($player->fresh(), $auction->fresh());
        $service->openEntry($round);
        $service->start($round->fresh());

        $entry = AuctionClosedBidEntry::where('auction_closed_bid_round_id', $round->id)
            ->where('actual_team_id', $ctx['team']->id)
            ->firstOrFail();

        /*
         * This is what "sealed bidding works in both modes" means for an offline room: the
         * team cannot type, so the organizer enters the amount it called out. The service
         * never consults open_bid_mode, which is why the guard on the team endpoints does
         * not reach it.
         */
        $service->adjust($entry, 9_000_000.0);

        $this->assertSame(9_000_000.0, (float) $entry->fresh()->amount);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Events\BidRaised;
use App\Models\AuctionPool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * The raise has to be announced, and only when there is one.
 *
 * Bidding reached other screens by a 2-second poll sitting behind a 1-second feed cache, so
 * a team could be looking at a price roughly three seconds old and bid against it. The two
 * events that were meant to carry a raise were dead code — mismatched constructors in
 * controllers with no routes — so nothing was ever published. These tests pin the event to
 * the paths that actually move the price, and pin it away from the ones that do not: a
 * broadcast for a bid the server refused would put a price on the wall that nobody owes.
 */
class BidRaisedBroadcastTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function scenario(array $auctionAttributes = []): array
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);

        $auction = $this->makeAuction($org, array_merge([
            'tournament_id' => $tournament->id,
            'status' => 'running',
            'max_budget_per_team' => 100_000_000,
            'bid_timer_seconds' => 0,
        ], $auctionAttributes));

        $pool = $this->makePool($auction, ['name' => 'Pool A', 'sequence' => 1, 'status' => AuctionPool::STATUS_ACTIVE]);

        $player = $this->makeAuctionPlayer($auction, [
            'auction_pool_id' => $pool->id,
            'lot_number' => 1,
            'status' => 'on_auction',
            'base_price' => 1_000_000,
            'current_price' => 1_000_000,
        ]);

        return [
            'org' => $org,
            'tournament' => $tournament,
            'auction' => $auction,
            'player' => $player,
        ];
    }

    #[Test]
    public function an_organizer_bid_announces_the_raise(): void
    {
        ['org' => $org, 'tournament' => $tournament, 'auction' => $auction, 'player' => $player] = $this->scenario();
        $team = $this->makeTeam($org, 'Alpha', $tournament);
        $operator = $this->makeAuctionOperator($org);

        Event::fake([BidRaised::class]);

        $this->actingAs($operator)
            ->postJson(route('admin.auctions.players.addBid'), [
                'auctionId' => $auction->id,
                'playerID' => $player->id,
                'teamId' => $team->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        Event::assertDispatched(BidRaised::class, function (BidRaised $e) use ($auction, $player, $team) {
            $payload = $e->broadcastWith();

            return $e->auctionId === $auction->id
                && $payload['auction_player_id'] === $player->id
                && $payload['current_bid_team_id'] === $team->id
                // The ordering token listeners rely on to drop stale frames.
                && $payload['bid_id'] > 0
                && $payload['current_price'] > 1_000_000;
        });
    }

    #[Test]
    public function a_refused_bid_announces_nothing(): void
    {
        ['org' => $org, 'tournament' => $tournament, 'auction' => $auction, 'player' => $player] = $this->scenario();
        $team = $this->makeTeam($org, 'Alpha', $tournament);
        $operator = $this->makeAuctionOperator($org);

        // Already the highest bidder — the check that resolves a simultaneous-tap race.
        $player->update(['current_bid_team_id' => $team->id]);

        Event::fake([BidRaised::class]);

        $this->actingAs($operator)
            ->postJson(route('admin.auctions.players.addBid'), [
                'auctionId' => $auction->id,
                'playerID' => $player->id,
                'teamId' => $team->id,
            ])
            ->assertStatus(422);

        // Announcing here would show the room a raise the server refused.
        Event::assertNotDispatched(BidRaised::class);
    }

    #[Test]
    public function nothing_is_announced_while_the_auction_is_paused(): void
    {
        ['org' => $org, 'tournament' => $tournament, 'auction' => $auction, 'player' => $player] = $this->scenario(['status' => 'paused']);
        $team = $this->makeTeam($org, 'Alpha', $tournament);
        $operator = $this->makeAuctionOperator($org);

        Event::fake([BidRaised::class]);

        $this->actingAs($operator)
            ->postJson(route('admin.auctions.players.addBid'), [
                'auctionId' => $auction->id,
                'playerID' => $player->id,
                'teamId' => $team->id,
            ])
            ->assertStatus(423);

        Event::assertNotDispatched(BidRaised::class);
    }

    #[Test]
    public function the_payload_carries_the_clock_so_a_listener_can_reseed_it(): void
    {
        ['org' => $org, 'tournament' => $tournament, 'auction' => $auction, 'player' => $player] = $this->scenario([
            'bid_timer_seconds' => 30,
            'bid_timer_reset_seconds' => 20,
        ]);
        $team = $this->makeTeam($org, 'Alpha', $tournament);
        $operator = $this->makeAuctionOperator($org);

        Event::fake([BidRaised::class]);

        $this->actingAs($operator)
            ->postJson(route('admin.auctions.players.addBid'), [
                'auctionId' => $auction->id,
                'playerID' => $player->id,
                'teamId' => $team->id,
            ])
            ->assertOk();

        /*
         * The timer keys are named exactly as the poll payload names them, because the
         * clients hand this frame straight to their existing syncTimerFromServer(). A frame
         * missing `timer_seconds_remaining` is read as "no clock running" and would zero the
         * countdown on every raise instead of restarting it.
         */
        Event::assertDispatched(BidRaised::class, function (BidRaised $e) {
            $payload = $e->broadcastWith();

            return array_key_exists('timer_seconds_remaining', $payload)
                && array_key_exists('bid_timer_seconds', $payload)
                && array_key_exists('timer_enabled', $payload)
                && $payload['timer_seconds_remaining'] !== null;
        });
    }

    #[Test]
    public function the_raise_goes_out_on_the_auctions_public_channel(): void
    {
        ['auction' => $auction, 'player' => $player] = $this->scenario();

        $event = new BidRaised(
            auctionId: $auction->id,
            auctionPlayerId: $player->id,
            currentPrice: 2_000_000,
            currentBidTeamId: null,
            teamName: null,
            bidId: 1,
        );

        // The same channel the wall and the panels already subscribe to. Sealed amounts must
        // never travel here — they are withheld even from the organizer's board until reveal.
        $this->assertSame('auction.' . $auction->id, $event->broadcastOn()->name);
        $this->assertSame('bid.raised', $event->broadcastAs());
    }
}

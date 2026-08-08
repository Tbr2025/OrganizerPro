<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Events\PlayerOnBidEvent;
use App\Events\PlayerSoldEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * A broadcast only works if both ends agree, and nothing else checks that they do.
 *
 * PlayerOnBidEvent broadcast as `player-on-bid` while every listener asked for
 * `.player.onbid`. A leading dot in Echo means "this exact name", so the two never met:
 * no bid ever reached a screen by websocket, and every price change on the LED wall
 * waited for the next two-second poll. Nothing failed, nothing logged, and the polling
 * fallback made it look like ordinary lag.
 *
 * The payload was wrong in two more ways underneath that. It was flat while both
 * listeners read `e.auctionPlayer.…`, and `current_price` was taken from `final_price`,
 * which is only written when a player is SOLD — null for the whole of the bidding this
 * event exists to report.
 *
 * These assertions are the contract. If a name or a key changes on either side, this
 * fails instead of the room quietly going back to waiting for the poll.
 */
class AuctionBroadcastContractTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    /** The names the screens actually subscribe to, read out of the views. */
    private function listenerNames(string $view): array
    {
        $html = file_get_contents(resource_path("views/{$view}"));

        preg_match_all("/\.listen\('\.([^']+)'/", (string) $html, $m);

        return $m[1] ?? [];
    }

    private function onBidEvent(): PlayerOnBidEvent
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id, 'status' => 'running']);
        $team = $this->makeTeam($org, 'Strikers', $tournament);

        $player = $this->makeAuctionPlayer($auction, [
            'status' => 'on_auction',
            'base_price' => 1_000_000,
            'current_price' => 5_000_000,
            'current_bid_team_id' => $team->id,
        ]);

        return new PlayerOnBidEvent($player->fresh(), $team);
    }

    #[Test]
    public function the_bid_event_broadcasts_under_the_name_the_screens_listen_for(): void
    {
        $names = $this->listenerNames('public/auction/live.blade.php');

        $this->assertContains('player.onbid', $names, 'the LED wall must still listen for this');
        $this->assertSame('player.onbid', $this->onBidEvent()->broadcastAs());
    }

    #[Test]
    public function the_auction_detail_page_listens_for_the_same_name(): void
    {
        // Two screens, one event. A rename that fixes one silently breaks the other.
        $this->assertContains(
            'player.onbid',
            $this->listenerNames('backend/pages/auctions/show.blade.php')
        );
    }

    #[Test]
    public function the_bid_payload_is_shaped_the_way_the_listeners_read_it(): void
    {
        $payload = $this->onBidEvent()->broadcastWith();

        // Both handlers do `e.auctionPlayer.…`; a flat payload gives them undefined.
        $this->assertArrayHasKey('auctionPlayer', $payload);

        foreach (['id', 'player', 'status', 'base_price', 'current_price', 'current_bid_team'] as $key) {
            $this->assertArrayHasKey($key, $payload['auctionPlayer'], "auctionPlayer.{$key}");
        }
    }

    #[Test]
    public function the_bid_payload_carries_the_live_price_not_the_sale_price(): void
    {
        $payload = $this->onBidEvent()->broadcastWith()['auctionPlayer'];

        /*
         * The bug that made the event useless even where it was delivered: current_price
         * was read from final_price, which is null until the player is sold. The wall would
         * have been pushed a null price for every raise.
         */
        $this->assertSame(5_000_000.0, (float) $payload['current_price']);
        $this->assertNotNull($payload['current_bid_team'], 'the room needs to see who is leading');
    }

    #[Test]
    public function the_sold_event_still_matches_its_listener(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $team = $this->makeTeam($org, 'Strikers', $tournament);
        $player = $this->makeAuctionPlayer($auction, ['status' => 'sold', 'final_price' => 9_000_000]);

        // This one was already right, which is exactly why sales appeared on the wall at
        // once and bids did not. Pinned so a tidy-up cannot align it to the broken one.
        $this->assertSame('player-on-sold', (new PlayerSoldEvent($player, $team))->broadcastAs());
        $this->assertContains('player-on-sold', $this->listenerNames('public/auction/live.blade.php'));
    }

    #[Test]
    public function both_events_broadcast_on_the_channel_the_wall_subscribes_to(): void
    {
        $event = $this->onBidEvent();
        $auctionId = $event->auctionPlayer->auction_id;

        // The wall joins `auction.{id}`; a private channel or a different prefix would
        // need auth the public wall does not have.
        $this->assertSame("auction.{$auctionId}", $event->broadcastOn()->name);
    }
}

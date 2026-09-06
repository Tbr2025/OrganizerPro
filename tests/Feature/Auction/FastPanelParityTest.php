<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Http\Controllers\Backend\FastAuctionScreenController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * What the Vue panel needs from the server, and the same-machine bus that feeds the wall.
 */
class FastPanelParityTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function scenario(): array
    {
        $org = $this->makeOrganization('Org ' . uniqid());
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'status' => 'running',
            'max_budget_per_team' => 100_000_000,
            'bid_rules' => [['from' => 0, 'to' => 200_000_000, 'increment' => 100_000]],
        ]);
        $team = $this->makeTeam($org, 'Alpha', $tournament);
        $player = $this->makeAuctionPlayer($auction, ['status' => 'on_auction', 'current_price' => 1_000_000]);

        return [$auction, $team, $player, $this->makeAuctionOperator($org)];
    }

    #[Test]
    public function the_panel_boot_carries_every_action_the_ui_offers(): void
    {
        [$auction, , , $operator] = $this->scenario();

        $boot = $this->actingAs($operator)
            ->get(route('admin.auction.organizer.fast-panel', $auction))
            ->assertOk()
            ->viewData('boot');

        /*
         * A button whose URL is missing is a button that silently does nothing. These are the
         * same endpoints the classic panel posts to — no second write path was introduced.
         */
        foreach ([
            'snapshot', 'sell', 'sellToTeam', 'pass', 'onBid', 'undo', 'reBid',
            'togglePause', 'toggleTimer', 'addBid', 'decreaseBid', 'clearBidTeam', 'classic',
        ] as $key) {
            $this->assertArrayHasKey($key, $boot['urls'], "The panel has no URL for {$key}.");
            $this->assertNotEmpty($boot['urls'][$key]);
        }
    }

    #[Test]
    public function the_state_carries_what_the_stage_draws(): void
    {
        [$auction, , , $operator] = $this->scenario();

        $state = $this->actingAs($operator)
            ->getJson(route('admin.auction.organizer.api.fast-state', $auction))
            ->assertOk()
            ->json();

        // bid_increment is what moves the figure on a press before the server answers. Without
        // it the "+" button appears to do nothing until the round trip completes.
        $this->assertArrayHasKey('bid_increment', $state);

        $this->assertArrayHasKey('teams', $state);
        $this->assertArrayHasKey('logo_url', $state['teams'][0] ?? ['logo_url' => null]);
        $this->assertArrayHasKey('remaining_budget', $state['teams'][0] ?? ['remaining_budget' => null]);

        // The stage prints more than a name beside the photo.
        foreach (['name', 'image_path', 'base_price', 'current_price', 'leader'] as $field) {
            $this->assertArrayHasKey($field, $state['current_player']);
        }
    }

    #[Test]
    public function next_player_is_chosen_by_the_server(): void
    {
        [$auction, , $onBlock, $operator] = $this->scenario();
        $waiting = $this->makeAuctionPlayer($auction, ['status' => 'waiting']);

        /*
         * `player-on-bid` NAMES a player, it does not choose one — posting an empty body got
         * "player id is required" back. The server picks, because the waiting queue is the
         * heaviest thing this screen could carry and a random pool must be drawn from the whole
         * candidate set, not from whatever slice a client happens to hold.
         */
        $candidate = $this->actingAs($operator)
            ->getJson(route('admin.auction.organizer.api.next-candidate', $auction))
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('id', $candidate);
        $this->assertSame($waiting->id, $candidate['id'], 'The waiting player should be next up.');
        $this->assertNotSame($onBlock->id, $candidate['id'], 'A player already on the block is not a candidate.');
    }

    #[Test]
    public function with_nobody_waiting_the_server_says_so_rather_than_erroring(): void
    {
        [$auction, , , $operator] = $this->scenario();

        // Only the on-block player exists, so there is no next candidate. A null id is an
        // answer the panel can show; an exception is not.
        $this->actingAs($operator)
            ->getJson(route('admin.auction.organizer.api.next-candidate', $auction))
            ->assertOk()
            ->assertJson(['id' => null]);
    }

    #[Test]
    public function the_heavy_lists_are_fetched_on_demand_and_never_polled(): void
    {
        [$auction, , , $operator] = $this->scenario();

        $boot = $this->actingAs($operator)
            ->get(route('admin.auction.organizer.fast-panel', $auction))
            ->assertOk()
            ->viewData('boot');

        // Templates, resolved in the client. Route changes stay in the routes file.
        $this->assertStringContainsString('__TEAM__', $boot['urls']['squad']);
        $this->assertStringContainsString('__POOL__', $boot['urls']['pools']['activate']);

        $state = $this->actingAs($operator)
            ->getJson(route('admin.auction.organizer.api.fast-state', $auction))
            ->assertOk()
            ->json();

        /*
         * The full player list and a squad are the heaviest things the panel can show and the
         * least often looked at. They must not ride the reconcile — a 400-player list on every
         * poll is exactly what made the classic panel expensive.
         */
        $this->assertArrayNotHasKey('available_players', $state);
        $this->assertArrayNotHasKey('all_players', $state);

        // Pools DO ride it: a handful of rows, and the toolbar shows progress continuously.
        $this->assertArrayHasKey('pools', $state);
        // Reported inside stats, not at the top level — the panel reads it from there.
        $this->assertArrayHasKey('unsold_count', $state['stats']);
    }

    #[Test]
    public function the_panel_publishes_to_the_local_bus_and_the_wall_listens(): void
    {
        $bus = file_get_contents(resource_path('js/fast-auction/lib/local-bus.js'));
        $panel = file_get_contents(resource_path('js/fast-auction/screens/Panel.vue'));
        $wall = file_get_contents(resource_path('js/fast-auction/screens/Wall.vue'));

        /*
         * The same-machine path: a price step reaches a wall in another window of this browser
         * without touching the network. It is an ADDITION to push — BroadcastChannel is
         * same-browser only, so a wall on a separate device still depends entirely on Pusher and
         * removing that would break the screens that matter most at a venue.
         */
        $this->assertStringContainsString('BroadcastChannel', $bus);
        $this->assertStringContainsString('publishLocal', $panel);
        $this->assertStringContainsString('subscribeLocal', $wall);

        // Both sides still run push, and the wall still reconciles.
        $this->assertStringContainsString("from '../lib/realtime'", $panel);
        $this->assertStringContainsString("from '../lib/realtime'", $wall);

        /*
         * The bus carries the PRICE and nothing else.
         *
         * The panel can see a winning team the room is not meant to have yet — a lot-reveal spin
         * withholds it deliberately — so publishing the leader here would put it on a
         * same-machine wall seconds early and spoil the reveal.
         */
        $this->assertStringNotContainsString('leader: cp.value.leader', $panel);
        $this->assertStringNotContainsString('data.leader', $wall);
    }
}

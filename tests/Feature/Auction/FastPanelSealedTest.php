<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Services\Auction\ClosedBidService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * The sealed-bid desk on the Vue panel.
 *
 * A sealed round is the one moment this panel is genuinely busy — a clock running, teams
 * submitting, an organizer watching counts — so the round rides the panel's own reconcile
 * rather than costing a second request each time round.
 */
class FastPanelSealedTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function closedBids(): ClosedBidService
    {
        return app(ClosedBidService::class);
    }

    private function scenario(): array
    {
        $org = $this->makeOrganization('Org ' . uniqid());
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'status' => 'running',
            'bid_type' => 'closed',
            'max_budget_per_team' => 100_000_000,
            'closed_bid_starts_at' => 8_000_000,
            'closed_bid_step' => 100_000,
            'closed_bid_timer_seconds' => 60,
            'bid_rules' => [['from' => 0, 'to' => 200_000_000, 'increment' => 100_000]],
        ]);

        $team = $this->makeTeam($org, 'Alpha', $tournament);
        $player = $this->makeAuctionPlayer($auction, ['status' => 'on_auction', 'current_price' => 8_000_000]);

        return [$auction, $team, $player, $this->makeAuctionOperator($org)];
    }

    #[Test]
    public function a_live_round_rides_the_panel_reconcile(): void
    {
        [$auction, , $player, $operator] = $this->scenario();

        $round = $this->closedBids()->openRoundFor($player, $auction);
        $this->closedBids()->start($round, null);

        $state = $this->actingAs($operator)
            ->getJson(route('admin.auction.organizer.api.fast-state', $auction))
            ->assertOk()
            ->json();

        $this->assertNotNull($state['sealed'], 'A live round must reach the panel.');
        $this->assertTrue($state['sealed']['active']);
        $this->assertSame($round->id, $state['sealed']['round_id']);

        // What the desk actually renders from.
        foreach (['state', 'entries', 'counts', 'revealed', 'timer'] as $key) {
            $this->assertArrayHasKey($key, $state['sealed']);
        }
    }

    #[Test]
    public function an_auction_with_no_round_carries_nothing_for_it(): void
    {
        $org = $this->makeOrganization('Org ' . uniqid());
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id, 'status' => 'running']);
        $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);

        $state = $this->actingAs($this->makeAuctionOperator($org))
            ->getJson(route('admin.auction.organizer.api.fast-state', $auction))
            ->assertOk()
            ->json();

        // An open auction pays nothing for a feature it is not using.
        $this->assertNull($state['sealed']);
    }

    #[Test]
    public function amounts_stay_sealed_until_the_server_says_revealed(): void
    {
        [$auction, $team, $player, $operator] = $this->scenario();

        $round = $this->closedBids()->openRoundFor($player, $auction);
        $this->closedBids()->start($round, null);
        $this->closedBids()->accept($round, $team);

        $sealed = $this->actingAs($operator)
            ->getJson(route('admin.auction.organizer.api.fast-state', $auction))
            ->assertOk()
            ->json('sealed');

        /*
         * The whole point of a sealed round is that nobody sees a bid early — including the
         * person running it. `revealed` is the server's word for it and the desk does not decide
         * that for itself.
         */
        $this->assertFalse($sealed['revealed']);
    }

    #[Test]
    public function the_desk_has_a_url_for_every_command_it_offers(): void
    {
        [$auction, , , $operator] = $this->scenario();

        $urls = $this->actingAs($operator)
            ->get(route('admin.auction.organizer.fast-panel', $auction))
            ->assertOk()
            ->viewData('boot')['urls'];

        // Built by Str::beforeLast, not rtrim — rtrim strips any of those CHARACTERS from the
        // end, so a URL finishing in r, a, t or s would lose them too.
        $this->assertStringEndsWith('/api/closed-bid', $urls['sealed']);
        $this->assertStringContainsString('__ENTRY__', $urls['sealedEntry']);
        $this->assertNotEmpty($urls['switchMode']);
    }

    #[Test]
    public function the_desk_ships_with_the_panel(): void
    {
        $desk = file_get_contents(resource_path('js/fast-auction/screens/SealedDesk.vue'));
        $panel = file_get_contents(resource_path('js/fast-auction/screens/Panel.vue'));

        $this->assertStringContainsString('SealedDesk', $panel);

        // Every round-level command the classic panel can send.
        foreach ([
            'start', 'open-entry', 'lock', 'award', 'lot', 'extend-timer',
            'reopen-selection', 'no-entries-decision', 'resolve-manual', 'start-rebid',
        ] as $command) {
            $this->assertStringContainsString("'{$command}'", $desk, "The desk cannot send {$command}.");
        }

        // And the per-entry ones.
        foreach (['adjust', 'withdraw', 'reinstate'] as $command) {
            $this->assertStringContainsString($command, $desk);
        }
    }
}

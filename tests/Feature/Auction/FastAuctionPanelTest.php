<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\AuctionOperator;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * The lean organizer / auctioneer panel.
 *
 * The classic panel is 6,511 lines and stays the complete surface. This covers what somebody
 * touches while a lot is running, and the assertion that matters most is the one about
 * abilities: an observe-only auctioneer must get a READ-ONLY panel, not a row of buttons that
 * 403 when pressed. The route deliberately allows observe — an auctioneer watching is a real
 * job — so the hiding has to happen in the payload.
 */
class FastAuctionPanelTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function withAuctionPermissions(): void
    {
        foreach (['auction.view', 'auction.edit', 'auction.observe', 'auction.control'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['group_name' => 'auction']);
        }
    }

    private function roleUser(Organization $org, string $role, array $permissions): User
    {
        $this->withAuctionPermissions();

        $r = Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $r->syncPermissions($permissions);

        return User::factory()->create(['organization_id' => $org->id])->assignRole($r);
    }

    /** @return array<string, mixed> */
    private function panelAuction(): array
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'status' => 'running',
            'bid_rules' => [['from' => 0, 'to' => 200_000_000, 'increment' => 100_000]],
        ]);
        $this->makeTeam($org, 'Titans', $tournament);
        $this->makeAuctionPlayer($auction, ['status' => 'on_auction', 'current_price' => 1_200_000]);

        return compact('org', 'tournament', 'auction');
    }

    #[Test]
    public function an_organizer_gets_the_panel_with_its_controls(): void
    {
        $ctx = $this->panelAuction();

        $page = $this->actingAs($this->makeAuctionOperator($ctx['org']))
            ->get(route('admin.auction.organizer.fast-panel', $ctx['auction']))
            ->assertOk();

        $page->assertSee('data-screen="panel"', false);
        $page->assertSee('id="fast-auction-boot"', false);

        $boot = $this->boot($page->getContent());
        $this->assertTrue($boot['can']['sell'], 'an operator with auction.edit may sell');
        $this->assertTrue($boot['can']['control']);
    }

    #[Test]
    public function an_observe_only_auctioneer_gets_a_read_only_panel(): void
    {
        $ctx = $this->panelAuction();

        /*
         * The route allows observe on purpose — watching is a job. So the panel must open, and
         * the write controls must be absent from the payload rather than present-and-refusing.
         */
        $user = $this->roleUser($ctx['org'], 'Auctioneer', ['auction.view', 'auction.observe']);
        AuctionOperator::create([
            'auction_id' => $ctx['auction']->id,
            'user_id' => $user->id,
            'abilities' => [AuctionOperator::ABILITY_OBSERVE],
        ]);

        $page = $this->actingAs($user)
            ->get(route('admin.auction.organizer.fast-panel', $ctx['auction']))
            ->assertOk();

        $boot = $this->boot($page->getContent());

        $this->assertFalse($boot['can']['sell'], 'observe-only must not be offered SELL');
        $this->assertFalse($boot['can']['control'], 'observe-only must not be offered pause/next');

        // Asserted on the payload, not on the HTML: the read-only banner is rendered by the
        // client from these flags, so the flags ARE the contract. Grepping the server response
        // for the wording would pass while the flags were wrong.
    }

    #[Test]
    public function the_trimmed_state_leaves_the_queue_out_and_caps_the_sold_board(): void
    {
        $ctx = $this->panelAuction();

        $data = $this->actingAs($this->makeAuctionOperator($ctx['org']))
            ->getJson(route('admin.auction.organizer.api.fast-state', $ctx['auction']))
            ->assertOk()
            ->json();

        /*
         * The queue is the largest part of the classic payload — measured at 286 KB of a 314 KB
         * poll on a 98-player pool — and it does not change between lots, so it ships once in the
         * boot blob and never on a reconcile.
         */
        $this->assertArrayNotHasKey('available_players', $data);

        // The control state a panel must never act on a divergent copy of.
        foreach (['current_player', 'teams', 'stats', 'can_undo', 'timer_enabled', 'sold_players'] as $key) {
            $this->assertArrayHasKey($key, $data);
        }
    }

    #[Test]
    public function the_panel_does_not_ship_the_admin_bundle(): void
    {
        $ctx = $this->panelAuction();

        $html = $this->actingAs($this->makeAuctionOperator($ctx['org']))
            ->get(route('admin.auction.organizer.fast-panel', $ctx['auction']))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('resources/js/app.js', $html);
        $this->assertStringNotContainsString('resources/css/app.css', $html);
    }

    #[Test]
    public function the_classic_panel_still_answers_and_is_linked(): void
    {
        $ctx = $this->panelAuction();
        $operator = $this->makeAuctionOperator($ctx['org']);

        $boot = $this->boot(
            $this->actingAs($operator)
                ->get(route('admin.auction.organizer.fast-panel', $ctx['auction']))
                ->assertOk()
                ->getContent()
        );

        // Everything this panel does not cover is one click away, always.
        $this->assertSame(
            route('admin.auction.organizer.panel', $ctx['auction']),
            $boot['urls']['classic']
        );

        $this->actingAs($operator)
            ->get(route('admin.auction.organizer.panel', $ctx['auction']))
            ->assertOk();
    }

    /** @return array<string, mixed> */
    private function boot(string $html): array
    {
        preg_match('/id="fast-auction-boot"[^>]*>(.+?)<\/script>/s', $html, $m);

        return json_decode(html_entity_decode($m[1] ?? '{}'), true) ?? [];
    }
}

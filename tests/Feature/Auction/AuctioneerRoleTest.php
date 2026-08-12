<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\ActualTeam;
use App\Models\Auction;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Watching an auction run is not the same job as running it.
 *
 * `auction.edit` used to be both: it gates the configuration wizard AND the live control
 * panel, so the only way to let the person calling the lots see the board was to let them
 * sell players and rewrite the auction's rules. The split is:
 *
 *   auction.observe — reach the panel and read it. Every GET.
 *   auction.control — change the auction. Every POST.
 *
 * The point of these tests is that the guard is on the ROUTES. Hiding a button is not a
 * permission, and an auctioneer's screen sits open in a room for three hours.
 */
class AuctioneerRoleTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string> */
    private const AUCTION_PERMISSIONS = [
        'auction.view', 'auction.create', 'auction.edit', 'auction.delete',
        'auction.closed-bids', 'auction.observe', 'auction.control',
    ];

    private function permissions(): void
    {
        foreach (self::AUCTION_PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['group_name' => 'auction']);
        }
    }

    private function user(Organization $org, string $roleName, array $permissions): User
    {
        $this->permissions();

        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $role->syncPermissions($permissions);

        $user = User::factory()->create(['organization_id' => $org->id]);
        $user->assignRole($role);

        return $user;
    }

    private function scenario(): array
    {
        $org = Organization::create(['name' => 'Org']);
        $tournament = Tournament::create([
            'name' => 'C', 'slug' => 'c', 'start_date' => '2026-01-01', 'organization_id' => $org->id,
        ]);
        $auction = Auction::create([
            'name' => 'A', 'status' => 'running', 'max_budget_per_team' => 100_000_000,
            'base_price' => 1_000_000, 'organization_id' => $org->id, 'tournament_id' => $tournament->id,
            'bid_type' => 'open', 'bid_timer_seconds' => 30,
            'bid_rules' => [['from' => 0, 'to' => 200_000_000, 'increment' => 100_000]],
        ]);
        $team = ActualTeam::create([
            'name' => 'T', 'organization_id' => $org->id, 'tournament_id' => $tournament->id,
        ]);

        return compact('org', 'tournament', 'auction', 'team');
    }

    private function auctioneer(Organization $org): User
    {
        return $this->user($org, 'Auctioneer', ['auction.view', 'auction.observe', 'auction.closed-bids']);
    }

    private function organizer(Organization $org): User
    {
        return $this->user($org, 'Superadmin', self::AUCTION_PERMISSIONS);
    }

    #[Test]
    public function an_auctioneer_can_open_the_control_panel(): void
    {
        ['org' => $org, 'auction' => $auction] = $this->scenario();

        $this->actingAs($this->auctioneer($org))
            ->get(route('admin.auction.organizer.panel', $auction))
            ->assertOk();
    }

    #[Test]
    public function an_auctioneer_sees_the_board_the_queue_and_every_teams_purse(): void
    {
        ['org' => $org, 'auction' => $auction] = $this->scenario();

        // The panel's whole live payload comes from one GET, so this is the test that the
        // auctioneer really can follow the auction rather than just load a shell.
        $this->actingAs($this->auctioneer($org))
            ->getJson(route('admin.auction.organizer.api.poll-state', $auction))
            ->assertOk()
            ->assertJsonStructure(['teams', 'stats']);
    }

    #[Test]
    public function an_auctioneer_cannot_sell_pass_skip_or_undo(): void
    {
        ['org' => $org, 'auction' => $auction] = $this->scenario();
        $user = $this->auctioneer($org);

        // Every mutating route, refused at the door. 403 from the permission middleware, not a
        // validation error from inside the controller — the request never reaches it.
        foreach ([
            'admin.auction.organizer.api.player.sell',
            'admin.auction.organizer.api.player.pass',
            'admin.auction.organizer.api.player.skip',
            'admin.auction.organizer.api.undo',
            'admin.auction.organizer.api.player.onbid',
            'admin.auction.organizer.api.start',
            'admin.auction.organizer.api.end',
            'admin.auction.organizer.api.toggle-pause',
            'admin.auction.organizer.api.closed-bid.start',
            'admin.auction.organizer.api.closed-bid.lock',
            'admin.auction.organizer.api.closed-bid.award',
        ] as $name) {
            $this->actingAs($user)
                ->postJson(route($name, $auction))
                ->assertForbidden();
        }
    }

    #[Test]
    public function an_auctioneer_is_kept_out_of_the_bid_entry_panel_and_the_config_wizard(): void
    {
        ['org' => $org, 'auction' => $auction] = $this->scenario();
        $user = $this->auctioneer($org);

        // The offline panel exists to enter bids by hand — nothing on it is observable.
        $this->actingAs($user)
            ->get(route('admin.auction.organizer.offline-panel', $auction))
            ->assertForbidden();

        // And an auctioneer has no business rewriting the auction's rules mid-auction.
        $this->actingAs($user)
            ->get(route('admin.auctions.edit', $auction))
            ->assertForbidden();
    }

    #[Test]
    public function the_read_only_seat_is_told_it_is_read_only(): void
    {
        ['org' => $org, 'auction' => $auction] = $this->scenario();

        $html = $this->actingAs($this->auctioneer($org))
            ->get(route('admin.auction.organizer.panel', $auction))
            ->assertOk()
            ->getContent();

        // A panel with its controls missing and nothing said reads as broken, not read-only.
        $this->assertStringContainsString('Auctioneer view', $html);
        $this->assertStringContainsString('canControl: false', $html);
    }

    #[Test]
    public function nothing_changes_for_whoever_was_already_running_auctions(): void
    {
        ['org' => $org, 'auction' => $auction] = $this->scenario();
        $user = $this->organizer($org);

        $this->actingAs($user)->get(route('admin.auction.organizer.panel', $auction))->assertOk();
        $this->actingAs($user)->get(route('admin.auction.organizer.offline-panel', $auction))->assertOk();
        $this->actingAs($user)->get(route('admin.auction.organizer.panel', $auction))
            ->assertSee('canControl: true', false);

        // Reaching a mutating route is the check here — whatever the controller then decides
        // about auction state is a different question and belongs to its own tests.
        $this->actingAs($user)
            ->postJson(route('admin.auction.organizer.api.toggle-pause', $auction))
            ->assertStatus(200);
    }

    #[Test]
    public function a_team_manager_still_cannot_reach_the_panel_at_all(): void
    {
        ['org' => $org, 'auction' => $auction] = $this->scenario();

        // `auction.view` is what a Team Manager holds, and it must never open the panel —
        // the whole reason observe is a separate permission rather than a widened view.
        $this->actingAs($this->user($org, 'Team Manager', ['auction.view']))
            ->get(route('admin.auction.organizer.panel', $auction))
            ->assertForbidden();
    }
}

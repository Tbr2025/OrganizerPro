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

    /**
     * An auctioneer NAMED ON THE AUCTION.
     *
     * The role alone used to be enough for every auction in the organization, because a
     * permission cannot say which one. It now has to be granted per auction — see
     * EnsureAuctionOperator — so the fixture grants it, and
     * an_auctioneer_not_named_on_the_auction_is_refused() covers the other side.
     *
     * @param  \App\Models\Auction|null  $auction  omit to make an auctioneer named on nothing
     */
    private function auctioneer(Organization $org, $auction = null): User
    {
        $user = $this->user($org, 'Auctioneer', ['auction.view', 'auction.observe', 'auction.closed-bids']);

        if ($auction) {
            \App\Models\AuctionOperator::create([
                'auction_id' => $auction->id,
                'user_id' => $user->id,
                'abilities' => [\App\Models\AuctionOperator::ABILITY_OBSERVE],
            ]);
        }

        return $user;
    }

    private function organizer(Organization $org): User
    {
        return $this->user($org, 'Superadmin', self::AUCTION_PERMISSIONS);
    }

    #[Test]
    public function an_auctioneer_can_open_the_control_panel(): void
    {
        ['org' => $org, 'auction' => $auction] = $this->scenario();

        $this->actingAs($this->auctioneer($org, $auction))
            ->get(route('admin.auction.organizer.panel', $auction))
            ->assertOk();
    }

    #[Test]
    public function an_auctioneer_sees_the_board_the_queue_and_every_teams_purse(): void
    {
        ['org' => $org, 'auction' => $auction] = $this->scenario();

        // The panel's whole live payload comes from one GET, so this is the test that the
        // auctioneer really can follow the auction rather than just load a shell.
        $this->actingAs($this->auctioneer($org, $auction))
            ->getJson(route('admin.auction.organizer.api.poll-state', $auction))
            ->assertOk()
            ->assertJsonStructure(['teams', 'stats']);
    }

    #[Test]
    public function an_auctioneer_not_named_on_the_auction_is_refused(): void
    {
        /*
         * The point of scoping. The role carries the permission, so the route's own guard is
         * satisfied — and that used to be the whole test, which meant somebody trusted to call
         * one evening's lots could open every auction in the organization.
         */
        ['org' => $org, 'auction' => $auction] = $this->scenario();

        $this->actingAs($this->auctioneer($org))
            ->get(route('admin.auction.organizer.panel', $auction))
            ->assertForbidden();
    }

    #[Test]
    public function an_auctioneer_named_on_one_auction_cannot_open_another(): void
    {
        ['org' => $org, 'tournament' => $tournament, 'auction' => $auction] = $this->scenario();

        $other = Auction::create([
            'name' => 'A different evening', 'status' => 'running',
            'max_budget_per_team' => 100_000_000, 'base_price' => 1_000_000,
            'organization_id' => $org->id, 'tournament_id' => $tournament->id,
            'bid_type' => 'open', 'bid_timer_seconds' => 30,
            'bid_rules' => [['from' => 0, 'to' => 200_000_000, 'increment' => 100_000]],
        ]);

        // Named on the first, and only the first.
        $user = $this->auctioneer($org, $auction);

        $this->actingAs($user)
            ->get(route('admin.auction.organizer.panel', $auction))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('admin.auction.organizer.panel', $other))
            ->assertForbidden();
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

        $html = $this->actingAs($this->auctioneer($org, $auction))
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

    /**
     * The list shows the auctions they were given, and nothing else.
     *
     * Being refused at the door of a room you were shown is worse than never being shown it: an
     * unfiltered index put every auction in the organization in front of an auctioneer — names,
     * dates, budgets — with every link on the page leading to a 403.
     */
    #[Test]
    public function an_auctioneer_only_sees_the_auction_they_were_given(): void
    {
        ['org' => $org, 'auction' => $auction] = $this->scenario();

        $other = Auction::create([
            'name' => 'Someone Elses Auction', 'status' => 'running',
            'max_budget_per_team' => 100_000_000, 'base_price' => 1_000_000,
            'organization_id' => $org->id, 'tournament_id' => $auction->tournament_id,
            'bid_type' => 'open', 'bid_timer_seconds' => 30,
            'bid_rules' => [['from' => 0, 'to' => 200_000_000, 'increment' => 100_000]],
        ]);

        $user = $this->auctioneer($org, $auction);

        $this->actingAs($user)->get(route('admin.auctions.index'))
            ->assertOk()
            ->assertSee($auction->name)
            ->assertDontSee('Someone Elses Auction');

        // And not reachable by typing the id in either — the list is a courtesy, not the guard.
        $this->actingAs($user)->get(route('admin.auctions.show', $other))->assertForbidden();
        $this->actingAs($user)->get(route('admin.auctions.show', $auction))->assertOk();
    }

    /**
     * The roles are additive; the auction access is not.
     *
     * Adding a person to an auction grants them the Auctioneer role ON TOP of what they hold —
     * assignRole, never syncRoles, so a Scorer stays a Scorer and keeps everything else that came
     * with it. What being named does NOT do is widen their auction access: naming somebody on one
     * auction is an explicit statement of which auction they run, so it scopes them there even if
     * another of their roles carries auction permissions of its own.
     *
     * This asserted the opposite for a day — that another auction-capable role kept its wider
     * access — which is defensible in the abstract and not what an auction desk wants: it let a
     * person given one evening's lots open every auction in the organization.
     */
    #[Test]
    public function the_new_role_is_added_to_the_old_one_but_does_not_widen_which_auctions_are_open(): void
    {
        ['org' => $org, 'auction' => $auction] = $this->scenario();

        // A second auction they are NOT named on.
        $other = Auction::create([
            'name' => 'B', 'status' => 'running', 'max_budget_per_team' => 100_000_000,
            'base_price' => 1_000_000, 'organization_id' => $org->id,
            'tournament_id' => $auction->tournament_id,
            'bid_type' => 'open', 'bid_timer_seconds' => 30,
            'bid_rules' => [['from' => 0, 'to' => 200_000_000, 'increment' => 100_000]],
        ]);

        $user = $this->user($org, 'Auction Desk', ['auction.observe', 'auction.control']);
        $this->assertTrue($user->fresh()->hasRole('Auction Desk'));

        // Named on one auction, exactly as the controller does it.
        Role::firstOrCreate(['name' => 'Auctioneer', 'guard_name' => 'web']);
        $user->assignRole('Auctioneer');
        \App\Models\AuctionOperator::create([
            'auction_id' => $auction->id, 'user_id' => $user->id, 'abilities' => ['control'],
        ]);

        $user = $user->fresh();
        $this->assertTrue($user->hasRole('Auction Desk'), 'the role they came with is still there');
        $this->assertTrue($user->hasRole('Auctioneer'), 'and the new one was added, not swapped in');

        // The auction they were given opens; the one they were not does not.
        $this->actingAs($user)->get(route('admin.auction.organizer.panel', $auction))->assertOk();
        $this->actingAs($user)->get(route('admin.auction.organizer.panel', $other))->assertForbidden();

        // Their own role still carries them everywhere it did before — this scopes auctions, and
        // auctions only. Nothing else on their account changed.
        $this->assertTrue($user->can('auction.observe'));
        $this->assertTrue($user->can('auction.control'));
    }

    /**
     * The sidebar is Dashboard, their auctions, Logout.
     *
     * A person at the desk to call one evening's lots was being offered players, tournaments,
     * teams, fixtures, posters and settings — a list of doors that either 403 or let them change
     * something nobody asked them to touch.
     */
    #[Test]
    public function the_auctioneers_sidebar_is_only_the_dashboard_their_auction_and_logout(): void
    {
        ['org' => $org, 'auction' => $auction] = $this->scenario();
        $user = $this->auctioneer($org, $auction);

        $page = $this->actingAs($user)->get(route('admin.auctions.index'));
        $page->assertOk();

        // Straight to the panel, named — they should not have to find their own auction in a list.
        $page->assertSee(route('admin.auction.organizer.panel', $auction), false);

        foreach (['admin.players.index', 'admin.tournaments.index', 'admin.roles.index',
                  'admin.users.index', 'admin.matches.index'] as $shouldBeAbsent) {
            $page->assertDontSee(route($shouldBeAbsent), false);
        }
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

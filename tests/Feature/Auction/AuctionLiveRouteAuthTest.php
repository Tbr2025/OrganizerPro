<?php

namespace Tests\Feature\Auction;

use App\Models\Tournament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * The organizer control panel and its 20-odd command endpoints used to carry only
 * the `auth` middleware, so ANY logged-in user — including a player or a team
 * manager from another organization — could start, sell in, or end any auction.
 */
class AuctionLiveRouteAuthTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    #[Test]
    public function a_user_without_auction_edit_cannot_open_the_organizer_panel(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org);
        $plain = $this->makePlainUser($org);

        $this->actingAs($plain)
            ->get(route('admin.auction.organizer.panel', $auction))
            ->assertForbidden();
    }

    #[Test]
    public function a_team_manager_cannot_reach_the_organizer_panel_or_sell(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org);

        // Team Manager holds `auction.view` for live bidding but must never be able
        // to operate the auction itself.
        $viewOnly = Permission::firstOrCreate(
            ['name' => 'auction.view', 'guard_name' => 'web'],
            ['group_name' => 'auction']
        );
        $role = Role::firstOrCreate(['name' => 'Team Manager', 'guard_name' => 'web']);
        $role->givePermissionTo($viewOnly);

        $manager = $this->makePlainUser($org)->assignRole($role);
        $ap = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);

        $this->actingAs($manager)
            ->get(route('admin.auction.organizer.panel', $auction))
            ->assertForbidden();

        $this->actingAs($manager)
            ->postJson(route('admin.auction.organizer.api.player.sell', $auction), ['auction_player_id' => $ap->id])
            ->assertForbidden();

        $this->assertSame('on_auction', $ap->fresh()->status);
    }

    #[Test]
    public function an_organizer_cannot_operate_another_organizations_auction(): void
    {
        $orgA = $this->makeOrganization('Org A');
        $orgB = $this->makeOrganization('Org B');

        $tournamentB = $this->makeTournament($orgB);
        $auctionB = $this->makeAuction($orgB, ['tournament_id' => $tournamentB->id]);

        // A pure Organizer in org A, with auction.edit but no assignment to org B's
        // tournament.
        $permission = Permission::firstOrCreate(
            ['name' => 'auction.edit', 'guard_name' => 'web'],
            ['group_name' => 'auction']
        );
        $role = Role::firstOrCreate(['name' => 'Organizer', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        $organizerA = $this->makePlainUser($orgA)->assignRole($role);

        $ap = $this->makeAuctionPlayer($auctionB, ['status' => 'on_auction']);

        // Two layers deny this, and either is acceptable: Auction's organization
        // global scope hides the row from route binding (404 — doesn't even leak
        // that it exists), and EnsureOrganizerCanAccess 403s a pure Organizer on a
        // tournament outside their assignments. What must never happen is a 2xx.
        foreach ([
            ['get', route('admin.auction.organizer.panel', $auctionB)],
            ['postJson', route('admin.auction.organizer.api.undo', $auctionB)],
            ['postJson', route('admin.auction.organizer.api.end', $auctionB)],
            ['postJson', route('admin.auction.organizer.api.player.sell', $auctionB)],
        ] as [$method, $url]) {
            $status = $this->actingAs($organizerA)->{$method}($url)->getStatusCode();

            $this->assertContains(
                $status,
                [403, 404],
                "Cross-org request to {$url} returned {$status}; expected it to be denied."
            );
        }

        // The other org's auction is untouched.
        $this->assertSame('running', $auctionB->fresh()->status);
        $this->assertSame('on_auction', $ap->fresh()->status);
    }

    #[Test]
    public function an_operator_with_auction_edit_can_open_the_panel(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $operator = $this->makeAuctionOperator($org);

        $this->actingAs($operator)
            ->get(route('admin.auction.organizer.panel', $auction))
            ->assertOk();
    }

    #[Test]
    public function a_manager_cannot_bid_as_a_team_from_another_tournament(): void
    {
        $org = $this->makeOrganization();
        $tournamentA = $this->makeTournament($org);
        $tournamentB = $this->makeTournament($org);

        // The auction belongs to tournament A; the user only manages a team in B.
        $auction = $this->makeAuction($org, ['tournament_id' => $tournamentA->id]);
        $teamB = $this->makeTeam($org, 'Team in B', $tournamentB);

        $manager = $this->makePlainUser($org);
        $teamB->users()->attach($manager->id, ['role' => 'Owner']);

        $ap = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);

        $this->actingAs($manager)
            ->postJson(route('team.auction.bidding.api.place-bid', $auction), ['auction_player_id' => $ap->id])
            ->assertForbidden();

        $this->assertNull($ap->fresh()->current_bid_team_id);
    }
}

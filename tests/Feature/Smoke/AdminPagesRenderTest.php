<?php

namespace Tests\Feature\Smoke;

use App\Models\Auction;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Player;
use App\Models\Role;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Guards the recurring "undefined variable / null relation → 500" class of bugs
 * (e.g. the auctions/edit $breadcrumbs regression) by rendering key admin pages.
 */
class AdminPagesRenderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function key_admin_pages_render_for_a_superadmin(): void
    {
        $org = Organization::create(['name' => 'Org']);
        $tournament = Tournament::create([
            'name' => 'Cup', 'slug' => 'cup-x', 'start_date' => '2026-01-01', 'organization_id' => $org->id,
        ]);
        $auction = Auction::create([
            'name' => 'A', 'status' => 'scheduled', 'max_budget_per_team' => 1000,
            'organization_id' => $org->id, 'tournament_id' => $tournament->id, 'bid_type' => 'closed',
        ]);
        $player = Player::create(['name' => 'P', 'email' => 'p@x.test', 'status' => 'approved', 'organization_id' => $org->id]);

        $role = Role::create(['name' => 'Superadmin']);
        foreach (['player.create', 'player.edit', 'tournament.edit', 'auction.view'] as $perm) {
            Permission::create(['name' => $perm, 'group_name' => 'smoke']);
            $role->givePermissionTo($perm);
        }
        $admin = User::factory()->create(['organization_id' => $org->id]);
        $admin->assignRole($role);
        $this->actingAs($admin);

        $urls = [
            route('admin.auctions.index'),
            route('admin.auctions.create'),
            route('admin.auctions.show', $auction),
            route('admin.auctions.edit', $auction),
            route('admin.auction.organizer.panel', $auction),
            route('admin.auction.organizer.offline-panel', $auction),
            route('admin.auctions.closed-bids'),
            route('admin.emails.preview'),
            route('admin.tournaments.settings.edit', $tournament),
            route('admin.players.create'),
            route('admin.players.edit', $player),
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }
}

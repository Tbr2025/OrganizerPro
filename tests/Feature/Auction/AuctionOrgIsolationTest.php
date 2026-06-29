<?php

namespace Tests\Feature\Auction;

use App\Models\Auction;
use App\Models\AuctionPlayer;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Player;
use App\Models\Role;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuctionOrgIsolationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function creating_an_auction_never_persists_players_from_another_organization(): void
    {
        $orgA = Organization::create(['name' => 'Org A']);
        $orgB = Organization::create(['name' => 'Org B']);

        $tournament = Tournament::create([
            'name' => 'Cup', 'slug' => 'cup-x', 'start_date' => '2026-01-01', 'organization_id' => $orgA->id,
        ]);

        $ourPlayer = Player::create(['organization_id' => $orgA->id, 'name' => 'Ours', 'email' => 'ours@x.test', 'status' => 'approved']);
        $foreignPlayer = Player::create(['organization_id' => $orgB->id, 'name' => 'Foreign', 'email' => 'foreign@x.test', 'status' => 'approved']);

        Permission::create(['name' => 'auction.create', 'group_name' => 'auction']);
        $role = Role::create(['name' => 'Superadmin']);
        $role->givePermissionTo('auction.create');
        $admin = User::factory()->create(['organization_id' => $orgA->id]);
        $admin->assignRole($role);

        // Wizard submits a pool that (maliciously or by bug) includes a foreign-org player.
        $pools = [[
            'name' => 'Pool A', 'capacity' => 50, 'order_mode' => 'sequential',
            'players' => [
                ['id' => $ourPlayer->id, 'base_price' => 100],
                ['id' => $foreignPlayer->id, 'base_price' => 100],
            ],
        ]];

        $this->actingAs($admin)->post(route('admin.auctions.store'), [
            'name' => 'Iso Auction', 'organization_id' => $orgA->id, 'tournament_id' => $tournament->id,
            'status' => 'scheduled', 'max_budget_per_team' => 100000, 'base_price' => 100,
            'start_at' => now()->addDay()->toDateTimeString(), 'end_at' => now()->addDays(2)->toDateTimeString(),
            'bid_rules' => [['from' => 0, 'to' => 100, 'increment' => 10]],
            'bid_type' => 'open', 'bid_timer_seconds' => 30,
            'pools' => json_encode($pools),
        ])->assertRedirect();

        $auction = Auction::where('name', 'Iso Auction')->first();
        $this->assertNotNull($auction);

        $playerIds = AuctionPlayer::where('auction_id', $auction->id)->pluck('player_id')->all();
        $this->assertContains($ourPlayer->id, $playerIds);
        $this->assertNotContains($foreignPlayer->id, $playerIds, 'A player from another organization must never be added to the auction.');
    }
}

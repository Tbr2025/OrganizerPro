<?php

namespace Tests\Feature\Auction;

use App\Models\ActualTeam;
use App\Models\Auction;
use App\Models\AuctionPlayer;
use App\Models\AuctionPool;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Player;
use App\Models\Role;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuctionUpdatePoolsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function updating_an_auction_rebuilds_waiting_pools_and_preserves_sold_players(): void
    {
        $org = Organization::create(['name' => 'Org']);
        $tournament = Tournament::create([
            'name' => 'Cup', 'slug' => 'cup-x', 'start_date' => '2026-01-01', 'organization_id' => $org->id,
        ]);
        $auction = Auction::create([
            'name' => 'A', 'status' => 'running', 'max_budget_per_team' => 100000,
            'base_price' => 100, 'organization_id' => $org->id, 'tournament_id' => $tournament->id, 'bid_type' => 'open',
        ]);
        $team = ActualTeam::create(['name' => 'T', 'organization_id' => $org->id]);

        $players = [];
        for ($i = 1; $i <= 4; $i++) {
            $players[] = Player::create(['organization_id' => $org->id, 'name' => "P{$i}", 'email' => "p{$i}@x.test", 'status' => 'approved']);
        }

        // One player already SOLD — must survive the rebuild untouched.
        $sold = AuctionPlayer::create([
            'auction_id' => $auction->id, 'player_id' => $players[0]->id, 'organization_id' => $org->id,
            'base_price' => 100, 'starting_price' => 100, 'status' => 'sold',
            'sold_to_team_id' => $team->id, 'final_price' => 5000,
        ]);

        Permission::create(['name' => 'auction.edit', 'group_name' => 'auction']);
        $role = Role::create(['name' => 'Superadmin']);
        $role->givePermissionTo('auction.edit');
        $admin = User::factory()->create(['organization_id' => $org->id]);
        $admin->assignRole($role);

        // Edit submits a pool of the 3 remaining (waiting) players, odd-even order.
        $pools = [[
            'name' => 'Pool A', 'capacity' => 50, 'order_mode' => 'odd_even',
            'players' => [
                ['id' => $players[1]->id, 'base_price' => 200],
                ['id' => $players[2]->id, 'base_price' => 200],
                ['id' => $players[3]->id, 'base_price' => 200],
            ],
        ]];

        $this->actingAs($admin)->put(route('admin.auctions.update', $auction), [
            'name' => 'A', 'organization_id' => $org->id, 'tournament_id' => $tournament->id,
            'status' => 'running', 'max_budget_per_team' => 100000, 'base_price' => 100,
            'bid_rules' => [['from' => 0, 'to' => 100, 'increment' => 10]],
            'bid_type' => 'open', 'bid_timer_seconds' => 30,
            'pools' => json_encode($pools),
        ])->assertRedirect();

        // Sold player untouched.
        $sold->refresh();
        $this->assertSame('sold', $sold->status);
        $this->assertSame($team->id, $sold->sold_to_team_id);
        $this->assertSame('5000.00', (string) $sold->final_price);

        // A pool was created with the 3 waiting players + lot numbers 1..3.
        $pool = AuctionPool::where('auction_id', $auction->id)->first();
        $this->assertNotNull($pool);
        $this->assertSame('odd_even', $pool->order_mode);

        $waiting = AuctionPlayer::where('auction_id', $auction->id)->where('status', 'waiting')->get();
        $this->assertCount(3, $waiting);
        $this->assertSame([1, 2, 3], $waiting->pluck('lot_number')->sort()->values()->all());
        $this->assertSame(200, (int) $waiting->first()->base_price);
    }

    #[Test]
    public function a_null_org_auction_keeps_its_players_on_pool_save(): void
    {
        // Legacy/global auction with NO organization — must not drop players.
        $org = Organization::create(['name' => 'Org']);
        $tournament = Tournament::create([
            'name' => 'Cup', 'slug' => 'cup-x', 'start_date' => '2026-01-01', 'organization_id' => $org->id,
        ]);
        $auction = Auction::create([
            'name' => 'NoOrg', 'status' => 'scheduled', 'max_budget_per_team' => 100000,
            'base_price' => 100, 'organization_id' => null, 'tournament_id' => $tournament->id, 'bid_type' => 'open',
        ]);

        $players = collect(range(1, 3))->map(fn ($i) => Player::create([
            'organization_id' => $org->id, 'name' => "P{$i}", 'email' => "p{$i}@x.test", 'status' => 'approved',
        ]));

        Permission::create(['name' => 'auction.edit', 'group_name' => 'auction']);
        $role = Role::create(['name' => 'Superadmin']);
        $role->givePermissionTo('auction.edit');
        $admin = User::factory()->create(['organization_id' => $org->id]);
        $admin->assignRole($role);

        $pools = [[
            'name' => 'Pool A', 'capacity' => 50, 'order_mode' => 'sequential',
            'players' => $players->map(fn ($p) => ['id' => $p->id, 'base_price' => 100])->all(),
        ]];

        $this->actingAs($admin)->put(route('admin.auctions.update', $auction), [
            'name' => 'NoOrg', 'organization_id' => $org->id, 'tournament_id' => $tournament->id,
            'status' => 'scheduled', 'max_budget_per_team' => 100000, 'base_price' => 100,
            'bid_rules' => [['from' => 0, 'to' => 100, 'increment' => 10]],
            'bid_type' => 'open', 'bid_timer_seconds' => 30,
            'pools' => json_encode($pools),
        ])->assertRedirect();

        $this->assertSame(3, AuctionPlayer::where('auction_id', $auction->id)->count());
    }
}

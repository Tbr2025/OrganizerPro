<?php

namespace Tests\Feature\Auction;

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

class AuctionPoolWizardTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function creating_an_auction_with_pools_persists_pools_and_lot_order(): void
    {
        $org = Organization::create(['name' => 'Org']);
        $tournament = Tournament::create([
            'name' => 'Cup', 'slug' => 'cup-x', 'start_date' => '2026-01-01', 'organization_id' => $org->id,
        ]);

        // Approved players to put in the pool.
        $players = [];
        for ($i = 1; $i <= 5; $i++) {
            $players[] = Player::create([
                'organization_id' => $org->id, 'name' => "P{$i}", 'email' => "p{$i}@x.test", 'status' => 'approved',
            ]);
        }

        Permission::create(['name' => 'auction.create', 'group_name' => 'auction']);
        $role = Role::create(['name' => 'Superadmin']);
        $role->givePermissionTo('auction.create');
        $admin = User::factory()->create(['organization_id' => $org->id]);
        $admin->assignRole($role);

        $pools = [[
            'name' => 'Pool A',
            'capacity' => 50,
            'order_mode' => 'odd_even',
            'players' => collect($players)->map(fn ($p) => ['id' => $p->id, 'base_price' => 100])->all(),
        ]];

        $this->actingAs($admin)->post(route('admin.auctions.store'), [
            'name' => 'Wizard Auction',
            'organization_id' => $org->id,
            'tournament_id' => $tournament->id,
            'status' => 'scheduled',
            'max_budget_per_team' => 100000,
            'base_price' => 100,
            'start_at' => now()->addDay()->toDateTimeString(),
            'end_at' => now()->addDays(2)->toDateTimeString(),
            'bid_rules' => [['from' => 0, 'to' => 100, 'increment' => 10]],
            'bid_type' => 'open',
            'bid_timer_seconds' => 30,
            'pools' => json_encode($pools),
        ])->assertRedirect();

        $auction = Auction::where('name', 'Wizard Auction')->first();
        $this->assertNotNull($auction);

        $pool = AuctionPool::where('auction_id', $auction->id)->first();
        $this->assertNotNull($pool);
        $this->assertSame('odd_even', $pool->order_mode);
        $this->assertSame(1, $pool->sequence);

        // 5 players, all in the pool, lot_numbers a full permutation 1..5.
        $aps = AuctionPlayer::where('auction_pool_id', $pool->id)->get();
        $this->assertCount(5, $aps);
        $this->assertSame([1, 2, 3, 4, 5], $aps->pluck('lot_number')->sort()->values()->all());

        // Odd-then-even: 1st player gets lot 1, 2nd player gets lot 4 (interleave).
        $this->assertSame(1, $aps->firstWhere('player_id', $players[0]->id)->lot_number);
        $this->assertSame(4, $aps->firstWhere('player_id', $players[1]->id)->lot_number);
    }
}

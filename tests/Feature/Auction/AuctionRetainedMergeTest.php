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
use App\Services\Auction\AuctionPoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuctionRetainedMergeTest extends TestCase
{
    use RefreshDatabase;

    private function setup_auction(): array
    {
        $org = Organization::create(['name' => 'Org']);
        $tournament = Tournament::create([
            'name' => 'Cup', 'slug' => 'cup-x', 'start_date' => '2026-01-01', 'organization_id' => $org->id,
        ]);
        $auction = Auction::create([
            'name' => 'A', 'status' => 'scheduled', 'max_budget_per_team' => 1000,
            'base_price' => 100, 'organization_id' => $org->id, 'tournament_id' => $tournament->id, 'bid_type' => 'open',
        ]);
        $pool = AuctionPool::create([
            'auction_id' => $auction->id, 'organization_id' => $org->id, 'name' => 'Pool A',
            'order_mode' => 'sequential', 'sequence' => 1,
        ]);

        $biddable = Player::create(['organization_id' => $org->id, 'name' => 'Bid', 'email' => 'b@x.test', 'status' => 'approved']);
        $retained = Player::create(['organization_id' => $org->id, 'name' => 'Ret', 'email' => 'r@x.test', 'status' => 'approved', 'player_mode' => 'retained']);

        $bidAp = AuctionPlayer::create(['auction_id' => $auction->id, 'auction_pool_id' => $pool->id, 'player_id' => $biddable->id, 'organization_id' => $org->id, 'base_price' => 100, 'starting_price' => 100, 'status' => 'waiting', 'is_retained' => false, 'lot_number' => 1]);
        $retAp = AuctionPlayer::create(['auction_id' => $auction->id, 'auction_pool_id' => $pool->id, 'player_id' => $retained->id, 'organization_id' => $org->id, 'base_price' => 100, 'starting_price' => 100, 'status' => 'waiting', 'is_retained' => true, 'lot_number' => null]);

        Permission::firstOrCreate(['name' => 'auction.edit', 'guard_name' => 'web'], ['group_name' => 'auction']);
        $role = Role::create(['name' => 'Superadmin']);
        $role->givePermissionTo('auction.edit');
        $admin = User::factory()->create(['organization_id' => $org->id]);
        $admin->assignRole($role);

        return compact('auction', 'pool', 'bidAp', 'retAp', 'admin');
    }

    #[Test]
    public function retained_pool_member_is_excluded_from_the_run_until_merged(): void
    {
        ['auction' => $auction, 'pool' => $pool, 'retAp' => $retAp, 'admin' => $admin] = $this->setup_auction();

        // Before merge: nextPlayer never returns the retained member.
        $next = app(AuctionPoolService::class)->nextPlayer($auction->fresh());
        $this->assertNotNull($next);
        $this->assertNotSame($retAp->id, $next->id);

        // Merge the pool's retained players into the auction.
        $this->actingAs($admin)
            ->postJson(route('admin.auctions.pools.merge-retained', [$auction, $pool]))
            ->assertOk()->assertJson(['success' => true]);

        $retAp->refresh();
        $this->assertFalse((bool) $retAp->is_retained);
        $this->assertSame('waiting', $retAp->status);
        $this->assertNotNull($retAp->lot_number); // now slotted into the draw
    }

    #[Test]
    public function reorder_persists_pool_sequence(): void
    {
        $org = Organization::create(['name' => 'Org']);
        $tournament = Tournament::create(['name' => 'C', 'slug' => 'c', 'start_date' => '2026-01-01', 'organization_id' => $org->id]);
        $auction = Auction::create(['name' => 'A', 'status' => 'scheduled', 'max_budget_per_team' => 1, 'base_price' => 1, 'organization_id' => $org->id, 'tournament_id' => $tournament->id, 'bid_type' => 'open']);
        $p1 = AuctionPool::create(['auction_id' => $auction->id, 'organization_id' => $org->id, 'name' => 'P1', 'order_mode' => 'sequential', 'sequence' => 1]);
        $p2 = AuctionPool::create(['auction_id' => $auction->id, 'organization_id' => $org->id, 'name' => 'P2', 'order_mode' => 'sequential', 'sequence' => 2]);

        Permission::firstOrCreate(['name' => 'auction.edit', 'guard_name' => 'web'], ['group_name' => 'auction']);
        $role = Role::create(['name' => 'Superadmin']);
        $role->givePermissionTo('auction.edit');
        $admin = User::factory()->create(['organization_id' => $org->id]);
        $admin->assignRole($role);

        // Reverse the order.
        $this->actingAs($admin)
            ->postJson(route('admin.auctions.pools.reorder', $auction), ['order' => [$p2->id, $p1->id]])
            ->assertOk();

        $this->assertSame(1, $p2->fresh()->sequence);
        $this->assertSame(2, $p1->fresh()->sequence);
    }
}

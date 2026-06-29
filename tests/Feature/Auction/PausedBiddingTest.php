<?php

namespace Tests\Feature\Auction;

use App\Models\ActualTeam;
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

class PausedBiddingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_add_bid_is_rejected_while_the_auction_is_paused(): void
    {
        $org = Organization::create(['name' => 'Org']);
        $tournament = Tournament::create(['name' => 'C', 'slug' => 'c', 'start_date' => '2026-01-01', 'organization_id' => $org->id]);
        $auction = Auction::create([
            'name' => 'A', 'status' => 'paused', 'max_budget_per_team' => 100000, 'base_price' => 100,
            'organization_id' => $org->id, 'tournament_id' => $tournament->id, 'bid_type' => 'open',
        ]);
        $team = ActualTeam::create(['name' => 'T', 'organization_id' => $org->id, 'tournament_id' => $tournament->id]);
        $player = Player::create(['organization_id' => $org->id, 'name' => 'P', 'email' => 'p@x.test', 'status' => 'approved']);
        $ap = AuctionPlayer::create([
            'auction_id' => $auction->id, 'player_id' => $player->id, 'organization_id' => $org->id,
            'base_price' => 100, 'starting_price' => 100, 'status' => 'on_auction', 'current_price' => 100,
        ]);

        Permission::firstOrCreate(['name' => 'auction.edit', 'guard_name' => 'web'], ['group_name' => 'auction']);
        $role = Role::create(['name' => 'Superadmin']);
        $role->givePermissionTo('auction.edit');
        $admin = User::factory()->create(['organization_id' => $org->id]);
        $admin->assignRole($role);

        $this->actingAs($admin)->postJson(route('admin.auctions.players.addBid'), [
            'auctionId' => $auction->id, 'playerID' => $ap->id, 'teamId' => $team->id,
        ])->assertStatus(423);
    }
}

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
use App\Models\TournamentRegistration;
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

        /*
         * Approved players to put in the pool.
         *
         * The REGISTRATION is what approval means here, not `players.status` — the wizard now
         * refuses anyone the tournament has not approved, the same rule the pools screen's
         * Assign and Auto-assign have always applied. This fixture used to create players with
         * no registration at all, which is not a state any live auction is in.
         */
        $players = [];
        for ($i = 1; $i <= 5; $i++) {
            $players[] = $this->approvedPlayer($org, $tournament, "P{$i}", "p{$i}@x.test");
        }

        Permission::create(['name' => 'auction.create', 'group_name' => 'auction']);
        // A bare Superadmin ROW is not a Superadmin: the auction routes are permission-gated,
        // and a real Superadmin holds every permission. Granted explicitly so this test stands
        // for the user it claims to.
        $role = Role::create(['name' => 'Superadmin']);
        foreach (['auction.view', 'auction.edit'] as $name) {
            $role->givePermissionTo(Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['group_name' => 'auction']
            ));
        }
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

    /** A player with an APPROVED registration for the tournament — what the wizard requires. */
    private function approvedPlayer(Organization $org, Tournament $tournament, string $name, string $email): Player
    {
        $player = Player::create([
            'organization_id' => $org->id, 'name' => $name, 'email' => $email, 'status' => 'approved',
        ]);

        TournamentRegistration::create([
            'tournament_id' => $tournament->id,
            'organization_id' => $org->id,
            'type' => 'player',
            'player_id' => $player->id,
            'status' => 'approved',
        ]);

        return $player;
    }

    /**
     * The wizard was the one door into a pool that asked nothing.
     *
     * Assign and Auto-assign on the pools screen both require an approved registration for this
     * tournament; the wizard's pool step wrote a row for every id in the submitted payload. That
     * is how a player whose registration was never approved ended up in Pool 2 of a live auction,
     * counted in its totals and queued for the block.
     *
     * Icon players stay exempt: they are kept by their team and may never have registered at all.
     */
    #[Test]
    public function the_wizard_refuses_a_player_the_tournament_has_not_approved(): void
    {
        $org = Organization::create(['name' => 'Org']);
        $tournament = Tournament::create([
            'name' => 'Cup', 'slug' => 'cup-gate', 'start_date' => '2026-01-01', 'organization_id' => $org->id,
        ]);

        $approved = $this->approvedPlayer($org, $tournament, 'Approved Ravi', 'ravi@x.test');

        // Registered, but still waiting on a decision.
        $pending = Player::create([
            'organization_id' => $org->id, 'name' => 'Pending Manoj', 'email' => 'manoj@x.test', 'status' => 'approved',
        ]);
        TournamentRegistration::create([
            'tournament_id' => $tournament->id,
            'organization_id' => $org->id,
            'type' => 'player',
            'player_id' => $pending->id,
            'status' => 'pending',
        ]);

        // An icon player, kept by their team, who never registered.
        $icon = Player::create([
            'organization_id' => $org->id, 'name' => 'Icon Sanju', 'email' => 'sanju@x.test',
            'status' => 'approved', 'player_mode' => 'retained',
        ]);

        $admin = $this->auctionAdmin($org);

        $pools = [[
            'name' => 'Pool A',
            'order_mode' => 'sequential',
            'players' => [
                ['id' => $approved->id, 'base_price' => 100],
                ['id' => $pending->id, 'base_price' => 100],
                ['id' => $icon->id, 'base_price' => 100],
            ],
        ]];

        $this->actingAs($admin)->post(route('admin.auctions.store'), [
            'name' => 'Gate Auction',
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

        $auction = Auction::where('name', 'Gate Auction')->first();
        $rows = AuctionPlayer::where('auction_id', $auction->id)->get()->keyBy('player_id');

        $this->assertTrue($rows->has($approved->id), 'an approved player must be pooled');
        $this->assertFalse(
            $rows->has($pending->id),
            'a player whose registration is still pending must never be written into a pool'
        );
        $this->assertTrue($rows->has($icon->id), 'an icon player is kept by their team and stays exempt');
        $this->assertTrue((bool) $rows[$icon->id]->is_retained);
    }

    /** The permission set the auction wizard needs. */
    private function auctionAdmin(Organization $org): User
    {
        $role = Role::firstOrCreate(['name' => 'Superadmin', 'guard_name' => 'web']);

        foreach (['auction.view', 'auction.edit', 'auction.create'] as $name) {
            $role->givePermissionTo(Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['group_name' => 'auction']
            ));
        }

        $admin = User::factory()->create(['organization_id' => $org->id]);
        $admin->assignRole($role);

        return $admin;
    }
}

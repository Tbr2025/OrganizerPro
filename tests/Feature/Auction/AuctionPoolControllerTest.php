<?php

namespace Tests\Feature\Auction;

use App\Models\ActualTeam;
use App\Models\Auction;
use App\Models\AuctionPlayer;
use App\Models\AuctionPool;
use App\Models\AuctionTeamBudget;
use App\Models\Organization;
use App\Models\Player;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuctionPoolControllerTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Auction $auction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create(['name' => 'Org']);
        $this->auction = Auction::create(['name' => 'A', 'status' => 'scheduled', 'max_budget_per_team' => 1000, 'organization_id' => $this->org->id]);

        $admin = User::factory()->create();
        $admin->assignRole(Role::create(['name' => 'Superadmin']));
        $this->actingAs($admin);
    }

    private function auctionPlayer(): AuctionPlayer
    {
        $player = Player::create(['name' => 'P', 'email' => 'p' . uniqid() . '@x.test', 'status' => 'approved', 'organization_id' => $this->org->id]);

        return AuctionPlayer::create([
            'auction_id' => $this->auction->id, 'player_id' => $player->id,
            'organization_id' => $this->org->id, 'starting_price' => 10, 'base_price' => 10, 'status' => 'waiting',
        ]);
    }

    #[Test]
    public function it_creates_a_pool_with_sequence(): void
    {
        $this->postJson(route('admin.auctions.pools.store', $this->auction), [
            'name' => 'Pool A', 'capacity' => 50, 'order_mode' => 'odd_even',
        ])->assertOk()->assertJson(['success' => true]);

        $pool = AuctionPool::where('auction_id', $this->auction->id)->first();
        $this->assertNotNull($pool);
        $this->assertSame('odd_even', $pool->order_mode);
        $this->assertSame(1, $pool->sequence);
        $this->assertSame($this->org->id, $pool->organization_id);
    }

    #[Test]
    public function it_assigns_players_and_draws_lots(): void
    {
        $pool = AuctionPool::create(['auction_id' => $this->auction->id, 'organization_id' => $this->org->id, 'name' => 'A', 'order_mode' => 'sequential', 'sequence' => 1]);
        $a = $this->auctionPlayer();
        $b = $this->auctionPlayer();

        $this->postJson(route('admin.auctions.pools.assign', [$this->auction, $pool]), [
            'auction_player_ids' => [$a->id, $b->id],
        ])->assertOk()->assertJson(['success' => true, 'assigned' => 2]);

        $this->assertSame($pool->id, $a->fresh()->auction_pool_id);

        $this->postJson(route('admin.auctions.pools.draw-lots', [$this->auction, $pool]))
            ->assertOk()->assertJson(['success' => true]);

        $this->assertSame(1, $a->fresh()->lot_number);
        $this->assertSame(2, $b->fresh()->lot_number);
    }

    #[Test]
    public function assign_respects_pool_capacity(): void
    {
        $pool = AuctionPool::create(['auction_id' => $this->auction->id, 'organization_id' => $this->org->id, 'name' => 'A', 'order_mode' => 'sequential', 'sequence' => 1, 'capacity' => 1]);
        $a = $this->auctionPlayer();
        $b = $this->auctionPlayer();

        $this->postJson(route('admin.auctions.pools.assign', [$this->auction, $pool]), [
            'auction_player_ids' => [$a->id, $b->id],
        ])->assertOk()->assertJson(['assigned' => 1]);

        $this->assertSame(1, $pool->players()->count());
    }

    #[Test]
    public function it_allocates_per_team_budgets(): void
    {
        $team = ActualTeam::create(['name' => 'T', 'organization_id' => $this->org->id]);

        $this->postJson(route('admin.auctions.budgets.allocate', $this->auction), [
            'budgets' => [['actual_team_id' => $team->id, 'budget' => 1500]],
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertSame('1500.00', AuctionTeamBudget::where('auction_id', $this->auction->id)
            ->where('actual_team_id', $team->id)->value('budget'));
    }
}

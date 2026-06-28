<?php

namespace Tests\Feature\Auction;

use App\Models\Auction;
use App\Models\Organization;
use App\Models\Player;
use App\Models\Role;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuctionEditPageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_auction_edit_page_renders_when_logged_in(): void
    {
        $org = Organization::create(['name' => 'Org']);
        $tournament = Tournament::create([
            'name' => 'Cup', 'slug' => 'cup-x', 'start_date' => '2026-01-01', 'organization_id' => $org->id,
        ]);

        // An approved player so the "available players" panel has data to serialize.
        Player::create([
            'organization_id' => $org->id, 'name' => 'P1', 'email' => 'p1@x.test', 'status' => 'approved',
        ]);

        $auction = Auction::create([
            'name' => 'Edit Me',
            'organization_id' => $org->id,
            'tournament_id' => $tournament->id,
            'status' => 'scheduled',
            'max_budget_per_team' => 100000,
            'base_price' => 100,
            'start_at' => now()->addDay(),
            'end_at' => now()->addDays(2),
            'bid_type' => 'open',
            'bid_timer_seconds' => 30,
        ]);

        $role = Role::create(['name' => 'Superadmin']);
        $admin = User::factory()->create(['organization_id' => $org->id]);
        $admin->assignRole($role);

        $this->actingAs($admin)
            ->get(route('admin.auctions.edit', $auction))
            ->assertOk()
            ->assertSee('Edit Auction')
            ->assertSee('Edit Me');
    }
}

<?php

namespace Tests\Feature\Tournament;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TournamentTypeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function type_defaults_to_open(): void
    {
        $org = Organization::create(['name' => 'Org']);
        $t = Tournament::create(['name' => 'T', 'slug' => 't-x', 'start_date' => '2026-01-01', 'organization_id' => $org->id]);

        $this->assertSame('open', $t->fresh()->type);
        $this->assertTrue($t->isOpen());
        $this->assertFalse($t->isAuction());
    }

    #[Test]
    public function is_auction_helper_reflects_type(): void
    {
        $org = Organization::create(['name' => 'Org']);
        $t = Tournament::create(['name' => 'A', 'slug' => 'a-x', 'start_date' => '2026-01-01', 'organization_id' => $org->id, 'type' => 'auction']);

        $this->assertTrue($t->isAuction());
        $this->assertFalse($t->isOpen());
    }

    #[Test]
    public function store_persists_the_submitted_type(): void
    {
        Permission::create(['name' => 'tournament.create', 'group_name' => 'tournament']);
        $org = Organization::create(['name' => 'Org']);
        $role = Role::create(['name' => 'Organizer']);
        $role->givePermissionTo('tournament.create');
        $user = User::factory()->create(['organization_id' => $org->id]);
        $user->assignRole($role);

        $this->actingAs($user)->post(route('admin.tournaments.store'), [
            'organization_id' => $org->id,
            'name' => 'Auction Cup',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'type' => 'auction',
        ])->assertRedirect();

        $this->assertSame('auction', Tournament::where('name', 'Auction Cup')->value('type'));
    }
}

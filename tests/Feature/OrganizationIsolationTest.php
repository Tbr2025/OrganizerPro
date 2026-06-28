<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Player;
use App\Models\Role;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Verifies the BelongsToOrganization global scope: an org user can only ever
 * read their own organization's rows, Superadmin sees all, and guest/console
 * contexts are not filtered.
 */
class OrganizationIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private Organization $orgB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orgA = Organization::create(['name' => 'Org A']);
        $this->orgB = Organization::create(['name' => 'Org B']);

        // One tournament + one player in each org.
        foreach ([$this->orgA, $this->orgB] as $i => $org) {
            Tournament::create([
                'name' => "T{$i}", 'slug' => "t-{$org->id}",
                'start_date' => '2026-01-01', 'organization_id' => $org->id,
            ]);
            Player::create([
                'organization_id' => $org->id,
                'name' => "P{$org->id}", 'email' => "p{$org->id}@x.test", 'status' => 'pending',
            ]);
        }
    }

    private function orgUser(Organization $org, string $roleName): User
    {
        $user = User::factory()->create(['organization_id' => $org->id]);
        $user->assignRole(Role::create(['name' => $roleName]));

        return $user;
    }

    #[Test]
    public function org_user_sees_only_their_own_org_rows(): void
    {
        $this->actingAs($this->orgUser($this->orgA, 'OrgA-role'));

        $this->assertSame(1, Tournament::count());
        $this->assertSame($this->orgA->id, Tournament::first()->organization_id);
        $this->assertSame(1, Player::count());
        $this->assertSame($this->orgA->id, Player::first()->organization_id);
    }

    #[Test]
    public function superadmin_sees_all_orgs(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::create(['name' => 'Superadmin']));
        $this->actingAs($admin);

        $this->assertSame(2, Tournament::count());
        $this->assertSame(2, Player::count());
    }

    #[Test]
    public function guest_context_is_not_scoped(): void
    {
        // No authenticated user (e.g. public registration, console) → no filtering.
        $this->assertSame(2, Tournament::count());
        $this->assertSame(2, Player::count());
    }

    #[Test]
    public function creating_autofills_organization_from_acting_user(): void
    {
        $this->actingAs($this->orgUser($this->orgA, 'OrgA-autofill'));

        $player = Player::create(['name' => 'New', 'email' => 'new@x.test', 'status' => 'pending']);

        $this->assertSame($this->orgA->id, $player->fresh()->organization_id);
    }

    #[Test]
    public function cross_org_record_is_not_resolvable(): void
    {
        $this->actingAs($this->orgUser($this->orgA, 'OrgA-cross'));

        // Org B's tournament must be invisible even by direct id.
        $orgBTournamentId = Tournament::withoutOrganizationScope()
            ->where('organization_id', $this->orgB->id)->value('id');

        $this->assertNotNull($orgBTournamentId);
        $this->assertNull(Tournament::find($orgBTournamentId));
    }
}

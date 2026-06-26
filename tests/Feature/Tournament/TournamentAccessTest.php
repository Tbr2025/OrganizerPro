<?php

namespace Tests\Feature\Tournament;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Verifies the role fix: tournament creation is gated by the `tournament.create`
 * permission (via AuthorizationChecker::checkAuthorization), NOT by a hardcoded
 * Superadmin check — so an Organizer granted the permission can create.
 */
class TournamentAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::create(['name' => 'tournament.create', 'group_name' => 'tournament']);
    }

    private function organizerWithPermission(): User
    {
        $role = Role::create(['name' => 'Organizer']);
        $role->givePermissionTo('tournament.create');

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    #[Test]
    public function user_without_permission_cannot_open_create_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.tournaments.create'))
            ->assertForbidden();
    }

    #[Test]
    public function user_without_permission_cannot_store(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.tournaments.store'), [])
            ->assertForbidden();
    }

    #[Test]
    public function organizer_with_permission_passes_the_authorization_gate(): void
    {
        // Empty payload: if the gate opens, we reach validation (302 + errors)
        // rather than a 403. This proves the permission — not a role name —
        // is what grants access.
        $this->actingAs($this->organizerWithPermission())
            ->post(route('admin.tournaments.store'), [])
            ->assertStatus(302)
            ->assertSessionHasErrors(['organization_id', 'name', 'start_date']);
    }
}

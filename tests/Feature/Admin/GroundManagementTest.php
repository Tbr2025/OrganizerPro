<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Ground;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Saving a ground as a Superadmin.
 *
 * `grounds.organization_id` is a NOT NULL foreign key, and the controller filled
 * it from `Auth::user()->organization_id` — which is NULL for a Superadmin,
 * because a Superadmin belongs to no single organization. So every Superadmin
 * save died on
 *   "SQLSTATE[23000]: Column 'organization_id' cannot be null"
 * and returned a bare 500. Three of those are in the production log.
 *
 * The other half of the bug: `create` and `edit` rendered
 * `backend.pages.grounds.create` / `.edit`, views that have never existed in this
 * repository — grounds are edited in a modal on the index page. Any stale link or
 * typed URL was a guaranteed 500 too.
 */
class GroundManagementTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'Superadmin', 'guard_name' => 'web']);
        $this->grantGroundPermissions($role);

        // The defining trait of the bug: no organization of their own.
        return User::factory()->create(['organization_id' => null])->assignRole($role);
    }

    private function orgAdmin(Organization $org): User
    {
        $role = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $this->grantGroundPermissions($role);

        return User::factory()->create(['organization_id' => $org->id])->assignRole($role);
    }

    private function grantGroundPermissions(Role $role): void
    {
        foreach (['ground.view', 'ground.create', 'ground.edit', 'ground.delete'] as $name) {
            $role->givePermissionTo(Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['group_name' => 'ground']
            ));
        }
    }

    #[Test]
    public function a_superadmin_can_create_a_ground_by_choosing_an_organization(): void
    {
        $org = Organization::create(['name' => 'You Selects']);
        Organization::create(['name' => 'NASS']);

        $response = $this->actingAs($this->superadmin())
            ->post(route('admin.grounds.store'), [
                'name' => 'DCS You Selects Arena 1',
                'city' => 'Sharjah',
                'address' => 'Rahmaniyah',
                'organization_id' => $org->id,
                'is_active' => '1',
            ]);

        $response->assertRedirect(route('admin.grounds.index'));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('grounds', [
            'name' => 'DCS You Selects Arena 1',
            'city' => 'Sharjah',
            'organization_id' => $org->id,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function a_superadmin_who_picks_no_organization_gets_a_validation_error_not_a_500(): void
    {
        Organization::create(['name' => 'Org One']);
        Organization::create(['name' => 'Org Two']);

        $response = $this->actingAs($this->superadmin())
            ->post(route('admin.grounds.store'), ['name' => 'Nameless Field']);

        $response->assertSessionHasErrors('organization_id');
        $this->assertDatabaseCount('grounds', 0);
    }

    #[Test]
    public function with_only_one_organization_a_superadmin_need_not_choose(): void
    {
        $org = Organization::create(['name' => 'Org 1']);

        $response = $this->actingAs($this->superadmin())
            ->post(route('admin.grounds.store'), ['name' => 'The Only Ground']);

        $response->assertRedirect(route('admin.grounds.index'));
        $this->assertDatabaseHas('grounds', ['name' => 'The Only Ground', 'organization_id' => $org->id]);
    }

    #[Test]
    public function an_org_admin_cannot_file_a_ground_under_another_organization(): void
    {
        $mine = Organization::create(['name' => 'Org 2']);
        $theirs = Organization::create(['name' => 'Org 3']);

        $this->actingAs($this->orgAdmin($mine))
            ->post(route('admin.grounds.store'), [
                'name' => 'Sneaky Ground',
                // Posted by hand; the picker is not shown to this user.
                'organization_id' => $theirs->id,
            ])
            ->assertRedirect(route('admin.grounds.index'));

        $this->assertDatabaseHas('grounds', ['name' => 'Sneaky Ground', 'organization_id' => $mine->id]);
    }

    #[Test]
    public function create_and_edit_redirect_to_the_modal_instead_of_rendering_a_missing_view(): void
    {
        $org = Organization::create(['name' => 'Org 4']);
        $ground = Ground::create(['name' => 'Existing', 'organization_id' => $org->id, 'is_active' => true]);
        $admin = $this->superadmin();

        $this->actingAs($admin)->get(route('admin.grounds.create'))
            ->assertRedirect(route('admin.grounds.index', ['action' => 'create']));

        $this->actingAs($admin)->get(route('admin.grounds.edit', $ground))
            ->assertRedirect(route('admin.grounds.index', ['action' => 'edit', 'ground' => $ground->id]));
    }

    #[Test]
    public function the_index_and_detail_pages_render(): void
    {
        $org = Organization::create(['name' => 'Org 5']);
        $ground = Ground::create(['name' => 'Rendered Ground', 'organization_id' => $org->id, 'is_active' => true]);
        $admin = $this->superadmin();

        $this->actingAs($admin)->get(route('admin.grounds.index'))
            ->assertOk()
            ->assertSee('Rendered Ground');

        // grounds.show had no view either.
        $this->actingAs($admin)->get(route('admin.grounds.show', $ground))
            ->assertOk()
            ->assertSee('Rendered Ground');
    }

    #[Test]
    public function search_and_status_filters_narrow_the_list(): void
    {
        $org = Organization::create(['name' => 'Org 6']);
        Ground::create(['name' => 'Sharjah Oval', 'city' => 'Sharjah', 'organization_id' => $org->id, 'is_active' => true]);
        Ground::create(['name' => 'Dubai Dome', 'city' => 'Dubai', 'organization_id' => $org->id, 'is_active' => false]);
        $admin = $this->superadmin();

        $this->actingAs($admin)->get(route('admin.grounds.index', ['search' => 'Dubai']))
            ->assertOk()->assertSee('Dubai Dome')->assertDontSee('Sharjah Oval');

        $this->actingAs($admin)->get(route('admin.grounds.index', ['status' => 'active']))
            ->assertOk()->assertSee('Sharjah Oval')->assertDontSee('Dubai Dome');
    }

    #[Test]
    public function a_scheme_less_maps_link_is_stored_as_a_usable_url(): void
    {
        $org = Organization::create(['name' => 'Org 7']);

        $this->actingAs($this->orgAdmin($org))->post(route('admin.grounds.store'), [
            'name' => 'Pasted Link Ground',
            // What the Google Maps share sheet actually hands you.
            'google_maps_link' => 'maps.app.goo.gl/abc123',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('grounds', [
            'name' => 'Pasted Link Ground',
            'google_maps_link' => 'https://maps.app.goo.gl/abc123',
        ]);
    }

    #[Test]
    public function a_non_http_scheme_in_the_maps_link_is_discarded(): void
    {
        $org = Organization::create(['name' => 'Org 8']);

        $this->actingAs($this->orgAdmin($org))->post(route('admin.grounds.store'), [
            'name' => 'Script Ground',
            'google_maps_link' => 'javascript:alert(1)',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('grounds', ['name' => 'Script Ground', 'google_maps_link' => null]);
    }

    #[Test]
    public function editing_can_deactivate_a_ground(): void
    {
        $org = Organization::create(['name' => 'Org 9']);
        $ground = Ground::create(['name' => 'Toggle Me', 'organization_id' => $org->id, 'is_active' => true]);

        // The form posts a hidden is_active=0 so an unchecked box still arrives.
        $this->actingAs($this->orgAdmin($org))
            ->put(route('admin.grounds.update', $ground), ['name' => 'Toggle Me', 'is_active' => '0'])
            ->assertRedirect(route('admin.grounds.index'));

        $this->assertFalse($ground->fresh()->is_active);
    }

    #[Test]
    public function an_org_admin_cannot_touch_another_organizations_ground(): void
    {
        $mine = Organization::create(['name' => 'Org 10']);
        $theirs = Organization::create(['name' => 'Org 11']);
        $ground = Ground::create(['name' => 'Not Mine', 'organization_id' => $theirs->id, 'is_active' => true]);

        $this->actingAs($this->orgAdmin($mine))
            ->put(route('admin.grounds.update', $ground), ['name' => 'Hijacked'])
            ->assertForbidden();

        $this->assertSame('Not Mine', $ground->fresh()->name);
    }
}

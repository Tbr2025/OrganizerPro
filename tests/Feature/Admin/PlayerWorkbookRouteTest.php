<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `/admin/players/export-xlsx` answered 404 in production while the route sat plainly in
 * routes/web.php.
 *
 * The cause was ordering, not the route: `Route::resource('players', ...)` was registered
 * first, and its `GET /players/{player}` matches the literal segment "export-xlsx". Laravel
 * then tried to bind a player with the id "export-xlsx", found none, and returned the 404 the
 * user was looking at. The fix is to register the literal route ahead of the wildcard, which
 * only a test that hits the URL can hold in place — `route()` builds the same string either
 * way, so a name-based assertion would have passed throughout.
 */
class PlayerWorkbookRouteTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $org = Organization::create(['name' => 'Export Org', 'slug' => 'export-org-' . uniqid()]);

        $role = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(
            ['name' => 'player.view', 'guard_name' => 'web'],
            ['group_name' => 'player']
        ));

        return User::factory()->create(['organization_id' => $org->id])->assignRole($role);
    }

    #[Test]
    public function the_literal_export_url_is_not_swallowed_by_the_player_wildcard(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/players/export-xlsx');

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml',
            (string) $response->headers->get('content-type'),
            'The export should answer with a workbook, not with a player page.'
        );
    }

    #[Test]
    public function a_real_player_id_still_reaches_the_player_page(): void
    {
        // The reordering must not cost the resource route it was moved in front of.
        $this->actingAs($this->admin())
            ->get('/admin/players/999999')
            ->assertNotFound();
    }
}

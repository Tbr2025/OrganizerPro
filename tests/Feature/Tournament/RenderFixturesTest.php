<?php

declare(strict_types=1);

namespace Tests\Feature\Tournament;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * The fixtures page on a phone.
 *
 * Both header menus were anchored with `right-0` alone. That pins a 224px menu's RIGHT edge to
 * its button — and once the action row wraps on a narrow screen those buttons sit at the left,
 * so the menu started at roughly x = -94px and its labels were cut off by the viewport.
 */
class RenderFixturesTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function admin(Organization $org): User
    {
        $role = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

        foreach (['tournament.view', 'tournament.edit'] as $name) {
            $role->givePermissionTo(Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['group_name' => 'tournament']
            ));
        }

        return User::factory()->create(['organization_id' => $org->id])->assignRole($role);
    }

    #[Test]
    public function both_header_menus_can_open_leftward_on_a_narrow_screen(): void
    {
        $org = $this->makeOrganization('Org ' . uniqid());
        $tournament = $this->makeTournament($org, 'open');

        $html = $this->actingAs($this->admin($org))
            ->get(route('admin.tournaments.fixtures.index', $tournament))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            2,
            substr_count($html, 'sm:left-auto sm:right-0'),
            'Generate Knockouts and Posters must both hang left until there is room to hang right.'
        );

        // A menu wider than the viewport overflows however it is anchored.
        $this->assertStringContainsString('max-w-[calc(100vw-2rem)]', $html);
    }
}

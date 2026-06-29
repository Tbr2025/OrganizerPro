<?php

namespace Tests\Feature\Admin;

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

class PlayersTournamentFilterTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(Organization $org): User
    {
        Permission::firstOrCreate(['name' => 'player.view', 'guard_name' => 'web'], ['group_name' => 'player']);
        $role = Role::firstOrCreate(['name' => 'Superadmin', 'guard_name' => 'web']);
        $role->givePermissionTo('player.view');
        $admin = User::factory()->create(['organization_id' => $org->id]);
        $admin->assignRole($role);

        return $admin;
    }

    private function playerRegisteredFor(Organization $org, Tournament $t, string $name): Player
    {
        $user = User::factory()->create(['organization_id' => $org->id]);
        $player = Player::create([
            'organization_id' => $org->id, 'user_id' => $user->id,
            'name' => $name, 'email' => strtolower($name) . '@x.test', 'status' => 'approved',
        ]);
        TournamentRegistration::create([
            'tournament_id' => $t->id, 'organization_id' => $org->id,
            'type' => 'player', 'player_id' => $player->id, 'status' => 'approved',
        ]);

        return $player;
    }

    #[Test]
    public function superadmin_can_filter_players_by_registered_tournament(): void
    {
        $org = Organization::create(['name' => 'Org']);
        $tA = Tournament::create(['name' => 'Alpha Cup', 'slug' => 'alpha', 'start_date' => '2026-01-01', 'organization_id' => $org->id]);
        $tB = Tournament::create(['name' => 'Beta Cup', 'slug' => 'beta', 'start_date' => '2026-01-01', 'organization_id' => $org->id]);

        $alpha = $this->playerRegisteredFor($org, $tA, 'AlphaPlayer');
        $beta = $this->playerRegisteredFor($org, $tB, 'BetaPlayer');

        $admin = $this->superadmin($org);

        // Filter by tournament A → only AlphaPlayer.
        $this->actingAs($admin)->get(route('admin.players.index', ['tournament' => $tA->id]))
            ->assertOk()
            ->assertSee('AlphaPlayer')
            ->assertDontSee('BetaPlayer');

        // Filter by tournament B → only BetaPlayer.
        $this->actingAs($admin)->get(route('admin.players.index', ['tournament' => $tB->id]))
            ->assertOk()
            ->assertSee('BetaPlayer')
            ->assertDontSee('AlphaPlayer');

        // No filter → both, and the Tournament dropdown is shown for Superadmin.
        $this->actingAs($admin)->get(route('admin.players.index'))
            ->assertOk()
            ->assertSee('AlphaPlayer')
            ->assertSee('BetaPlayer')
            ->assertSee('All Tournaments');
    }

    #[Test]
    public function non_superadmin_does_not_get_the_tournament_filter(): void
    {
        $org = Organization::create(['name' => 'Org']);
        Permission::firstOrCreate(['name' => 'player.view', 'guard_name' => 'web'], ['group_name' => 'player']);
        $role = Role::firstOrCreate(['name' => 'Organizer', 'guard_name' => 'web']);
        $role->givePermissionTo('player.view');
        $user = User::factory()->create(['organization_id' => $org->id]);
        $user->assignRole($role);

        // The tournament filter UI must not render for a non-Superadmin.
        $this->actingAs($user)->get(route('admin.players.index', ['tournament' => 999]))
            ->assertOk()
            ->assertDontSee('All Tournaments');
    }
}

<?php

declare(strict_types=1);

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

/**
 * Which players an organization's admin can see.
 *
 * The list was scoped with `whereIn('actual_team_id', $orgTeamIds)` alone. A player with no
 * team has `actual_team_id = NULL`, and `NULL IN (...)` is never true — so anyone who had
 * merely registered was invisible until somebody put them on a squad. Before an auction has
 * run that is nearly everyone, and they looked like they had never registered at all.
 */
class PlayersOrgScopeTest extends TestCase
{
    use RefreshDatabase;

    private function orgAdmin(Organization $org): User
    {
        $role = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

        foreach (['player.view', 'player.create', 'player.edit'] as $name) {
            $role->givePermissionTo(Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['group_name' => 'player']
            ));
        }

        return User::factory()->create(['organization_id' => $org->id])->assignRole($role);
    }

    /** A player with a user account holding the Player role — the list requires both. */
    private function playerWithUser(Organization $org, string $name, array $attrs = []): Player
    {
        $playerRole = Role::firstOrCreate(['name' => 'Player', 'guard_name' => 'web']);
        $user = User::factory()->create(['name' => $name, 'organization_id' => $org->id]);
        $user->assignRole($playerRole);

        return Player::create(array_merge([
            'name' => $name,
            'email' => $user->email,
            'status' => 'approved',
            'organization_id' => $org->id,
            'user_id' => $user->id,
        ], $attrs));
    }

    #[Test]
    public function a_registered_player_with_no_team_is_listed(): void
    {
        $org = Organization::create(['name' => 'Alpha Sports']);
        $tournament = Tournament::create([
            'name' => 'Alpha Cup',
            'slug' => 'alpha-cup-' . uniqid(),
            'organization_id' => $org->id,
            'type' => 'auction',
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(3),
        ]);

        // Registered, approved, but nobody has put them on a squad yet.
        $unassigned = $this->playerWithUser($org, 'Unassigned Ursula', ['actual_team_id' => null]);

        TournamentRegistration::create([
            'tournament_id' => $tournament->id,
            'player_id' => $unassigned->id,
            'organization_id' => $org->id,
            'registration_type' => 'player',
            'status' => 'approved',
        ]);

        $this->actingAs($this->orgAdmin($org))
            ->get(route('admin.players.index'))
            ->assertOk()
            ->assertSee('Unassigned Ursula');
    }

    #[Test]
    public function a_player_created_by_the_organizer_with_no_registration_is_listed(): void
    {
        $org = Organization::create(['name' => 'Alpha Sports']);

        // No registration row at all — created straight from the admin.
        $this->playerWithUser($org, 'Direct Dana', ['actual_team_id' => null]);

        $this->actingAs($this->orgAdmin($org))
            ->get(route('admin.players.index'))
            ->assertOk()
            ->assertSee('Direct Dana');
    }

    #[Test]
    public function another_organizations_players_are_still_hidden(): void
    {
        $mine = Organization::create(['name' => 'Alpha Sports']);
        $theirs = Organization::create(['name' => 'Beta Sports']);

        $this->playerWithUser($mine, 'Mine Mia', ['actual_team_id' => null]);
        $this->playerWithUser($theirs, 'Theirs Theo', ['actual_team_id' => null]);

        // Widening the scope must not turn into no scope at all.
        $this->actingAs($this->orgAdmin($mine))
            ->get(route('admin.players.index'))
            ->assertOk()
            ->assertSee('Mine Mia')
            ->assertDontSee('Theirs Theo');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Tournament;

use App\Models\ActualTeam;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The Groups screen must only offer teams that are actually in the tournament.
 *
 * An ActualTeam row is not proof of approval. On the live tournament seven teams existed
 * while only five registrations had been approved, and the page listed all seven — each one
 * labelled "Approved" — so groups were being built from teams nobody had let in yet.
 */
class GroupsApprovedTeamsTest extends TestCase
{
    use RefreshDatabase;

    private function tournament(Organization $org): Tournament
    {
        return Tournament::create([
            'name' => 'Alpha Cup',
            'slug' => 'alpha-cup-' . uniqid(),
            'organization_id' => $org->id,
            'type' => 'auction',
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(3),
        ]);
    }

    private function team(Organization $org, Tournament $t, string $name, ?string $status): ActualTeam
    {
        $team = ActualTeam::create([
            'name' => $name,
            'organization_id' => $org->id,
            'tournament_id' => $t->id,
        ]);

        // $status of null means a team created directly by an organizer, with no
        // registration at all — it was never part of the approval flow.
        if ($status !== null) {
            TournamentRegistration::create([
                'tournament_id' => $t->id,
                'organization_id' => $org->id,
                'actual_team_id' => $team->id,
                'type' => 'team',
                'status' => $status,
            ]);
        }

        return $team;
    }

    private function organizer(Organization $org): User
    {
        // Admin rather than Organizer: the tournament routes additionally scope a pure
        // Organizer to their own tournaments, which is not what this test is about.
        $role = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        foreach (['tournament.view', 'tournament.edit'] as $name) {
            $role->givePermissionTo(Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['group_name' => 'tournament']
            ));
        }

        // Spatie caches the permission map, so a grant made in the same request is invisible
        // to the check that follows unless the cache is dropped.
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return User::factory()->create(['organization_id' => $org->id])->assignRole($role);
    }

    #[Test]
    public function a_pending_team_is_not_offered_for_grouping(): void
    {
        $org = Organization::create(['name' => 'Alpha Sports']);
        $tournament = $this->tournament($org);

        $this->team($org, $tournament, 'Approved United', 'approved');
        $this->team($org, $tournament, 'Pending Rovers', 'pending');
        $this->team($org, $tournament, 'Rejected Athletic', 'rejected');
        // Created straight from the admin, so no registration exists to approve.
        $this->team($org, $tournament, 'Manual Wanderers', null);

        $this->actingAs($this->organizer($org))
            ->get(route('admin.tournaments.groups.index', $tournament))
            ->assertOk()
            ->assertSee('Approved United')
            // Kept: filtering on the absence of a row would hide legitimate teams.
            ->assertSee('Manual Wanderers')
            ->assertDontSee('Pending Rovers')
            ->assertDontSee('Rejected Athletic');
    }

    #[Test]
    public function a_superadmin_still_sees_every_team(): void
    {
        $org = Organization::create(['name' => 'Alpha Sports']);
        $tournament = $this->tournament($org);

        $this->team($org, $tournament, 'Approved United', 'approved');
        $this->team($org, $tournament, 'Pending Rovers', 'pending');

        // There is no Gate::before Superadmin bypass in this app, so the role still needs
        // the permission the controller checks.
        $superRole = Role::firstOrCreate(['name' => 'Superadmin', 'guard_name' => 'web']);
        foreach (['tournament.view', 'tournament.edit'] as $name) {
            $superRole->givePermissionTo(Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['group_name' => 'tournament']
            ));
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $superadmin = User::factory()->create(['organization_id' => null])->assignRole($superRole);

        $this->actingAs($superadmin)
            ->get(route('admin.tournaments.groups.index', $tournament))
            ->assertOk()
            ->assertSee('Approved United')
            ->assertSee('Pending Rovers');
    }
}

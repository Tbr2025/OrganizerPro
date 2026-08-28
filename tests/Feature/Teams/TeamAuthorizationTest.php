<?php

declare(strict_types=1);

namespace Tests\Feature\Teams;

use App\Models\ActualTeam;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Who may change a team, and who may reset its members' passwords.
 *
 * ActualTeamController had authorization on `update` and nowhere else. Team managers can reach
 * it — RedirectTeamManager allowlists `admin/actual-teams*` so their own team pages work, and
 * EnsureOrganizerCanAccess returns early for anyone who is not an Organizer — so being able to
 * load a team page was, in practice, permission to delete any team on the platform and to set
 * any team member's password to a value of the caller's choosing, reading it back from the
 * response.
 *
 * These test ROUTES rather than buttons, because the hole was reachable by URL regardless of
 * what the interface offered.
 */
class TeamAuthorizationTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function roleWith(string $roleName, array $permissions): Role
    {
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

        foreach ($permissions as $name) {
            $role->givePermissionTo(Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['group_name' => 'actual-team']
            ));
        }

        return $role;
    }

    /** A victim account sitting on someone else's team. */
    private function victimOn(ActualTeam $team): User
    {
        $victim = $this->makePlainUser(\App\Models\Organization::find($team->organization_id));
        $victim->update(['password' => Hash::make('the-original-password')]);
        $team->users()->attach($victim->id, ['role' => 'Owner']);

        return $victim->fresh();
    }

    #[Test]
    public function a_team_manager_cannot_take_over_another_teams_account(): void
    {
        $org = $this->makeOrganization();
        $victimTeam = $this->makeTeam($org, 'Someone Elses Team');
        $victim = $this->victimOn($victimTeam);

        // A perfectly ordinary team manager — exactly the permissions the role carries.
        $manager = $this->makeAuctionOperator($org);
        $manager->assignRole($this->roleWith('Team Manager', ['player.view', 'player.create', 'actual-team.view']));

        $response = $this->actingAs($manager->fresh())->post(
            route('admin.actual-teams.reset-team-manager-password', [$victimTeam, $victim]),
            ['password' => 'attacker-chosen-password']
        );

        $response->assertForbidden();

        // The password must be untouched, and must not have leaked into the response.
        $this->assertTrue(
            Hash::check('the-original-password', $victim->fresh()->password),
            'A team manager was able to reset another team member\'s password.'
        );
        $this->assertStringNotContainsString('attacker-chosen-password', $response->getContent());
    }

    #[Test]
    public function a_team_manager_cannot_delete_or_reshape_a_team(): void
    {
        $org = $this->makeOrganization();
        $team = $this->makeTeam($org, 'Victim Team');
        $outsider = $this->victimOn($team);

        $manager = $this->makeAuctionOperator($org);
        $manager->assignRole($this->roleWith('Team Manager', ['player.view', 'player.create', 'actual-team.view']));
        $manager = $manager->fresh();

        // `destroy` was `$actualTeam->delete()` with nothing in front of it.
        $this->actingAs($manager)->delete(route('admin.actual-teams.destroy', $team))->assertForbidden();
        $this->assertNotNull(ActualTeam::find($team->id), 'A team manager deleted a team.');

        $this->actingAs($manager)->post(route('admin.actual-teams.create-team-manager', $team), [
            'name' => 'Planted', 'email' => 'planted@example.test',
        ])->assertForbidden();
        $this->assertNull(User::where('email', 'planted@example.test')->first());

        $this->actingAs($manager)->delete(route('admin.actual-teams.delete-member', [$team, $outsider]))
            ->assertForbidden();
        $this->assertSame(1, $team->users()->count());
    }

    #[Test]
    public function staff_who_hold_the_permissions_are_not_locked_out(): void
    {
        $org = $this->makeOrganization();
        $team = $this->makeTeam($org, 'Managed Team');
        $member = $this->victimOn($team);

        $admin = $this->makeAuctionOperator($org);
        $admin->assignRole($this->roleWith('Admin', ['actual-team.view', 'actual-team.edit', 'actual-team.delete']));
        $admin = $admin->fresh();

        // The guard must not have shut out the people it exists to let through.
        $reset = $this->actingAs($admin)->post(
            route('admin.actual-teams.reset-team-manager-password', [$team, $member]),
            ['password' => 'a-new-password']
        );
        $reset->assertOk();
        $this->assertTrue(Hash::check('a-new-password', $member->fresh()->password));

        $this->actingAs($admin)->delete(route('admin.actual-teams.destroy', $team))->assertRedirect();
        $this->assertNull(ActualTeam::find($team->id));
    }

    #[Test]
    public function a_role_holding_actual_team_edit_keeps_the_workflow_and_stays_scoped(): void
    {
        $org = $this->makeOrganization();
        $team = $this->makeTeam($org, 'Organizer Team');
        $member = $this->victimOn($team);

        /*
         * Modelled on the Organizer role, but granted `actual-team.edit` explicitly — on live
         * Organizers do NOT hold it, which is exactly the caveat worth recording: `update()`
         * already required it before this change, so organizers could not edit a team anyway,
         * but they COULD previously add players and create managers through the unguarded
         * methods. Bringing those in line removes that. If organizers are meant to keep it, the
         * fix is to grant the role `actual-team.edit`, not to loosen the controller.
         */
        $organizer = $this->makeAuctionOperator($org);
        $organizer->assignRole($this->roleWith('Organizer', ['actual-team.view', 'actual-team.edit', 'actual-team.delete']));

        // An Organizer is additionally scoped by EnsureOrganizerCanAccess to the teams they are
        // assigned to — so assign them, which is the realistic case. An unassigned organizer being
        // refused is that middleware working, and is asserted below.
        $team->organizers()->attach($organizer->id);

        $this->actingAs($organizer->fresh())->post(
            route('admin.actual-teams.reset-team-manager-password', [$team, $member]),
            ['password' => 'organizer-set']
        )->assertOk();

        $this->assertTrue(Hash::check('organizer-set', $member->fresh()->password));

        // And the scoping still bites: a team they are not assigned to stays closed to them.
        $otherTeam = $this->makeTeam($org, 'Not Theirs');
        $otherMember = $this->victimOn($otherTeam);

        $this->actingAs($organizer->fresh())->post(
            route('admin.actual-teams.reset-team-manager-password', [$otherTeam, $otherMember]),
            ['password' => 'should-not-work']
        )->assertForbidden();

        $this->assertTrue(Hash::check('the-original-password', $otherMember->fresh()->password));
    }
}

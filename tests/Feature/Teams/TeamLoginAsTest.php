<?php

declare(strict_types=1);

namespace Tests\Feature\Teams;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Choosing the account behind "Login as Team".
 *
 * The teams list matched on the PIVOT role only — 'Owner', 'Manager' or 'Team Manager', spelled
 * exactly — which on live is true for 9 of 121 teams, because almost every pivot row reads
 * 'Player' (446) or 'captain' (53). The option was therefore invisible on nearly every team and
 * looked like a feature that had never been built.
 *
 * The account's real role is what matters, because it decides which dashboard the admin lands on:
 * logging in as a Player shows the player dashboard, not the team's.
 */
class TeamLoginAsTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private $org = null;

    /** One organization for the whole test — its name is unique, so it cannot be remade per user. */
    private function userWithRole(string $roleName)
    {
        $this->org ??= $this->makeOrganization('LoginAs Org ' . uniqid());

        $user = $this->makePlainUser($this->org);
        $user->assignRole(Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']));

        return $user->fresh();
    }

    #[Test]
    public function the_manager_account_is_preferred_over_the_pivot_label(): void
    {
        $org = $this->makeOrganization();
        $team = $this->makeTeam($org, 'Mixed Team');

        // The shape live actually has: everyone attached as 'Player', one of whom is the manager.
        $player = $this->userWithRole('Player');
        $manager = $this->userWithRole('Team Manager');
        $team->users()->attach($player->id, ['role' => 'Player']);
        $team->users()->attach($manager->id, ['role' => 'Player']);

        $this->assertSame(
            $manager->id,
            $team->fresh()->loginAsUser()?->id,
            'The account that can reach the team dashboard must win, whatever the pivot says.'
        );
    }

    #[Test]
    public function an_owner_outranks_a_manager_and_legacy_pivots_still_work(): void
    {
        $org = $this->makeOrganization();

        $team = $this->makeTeam($org, 'Owned Team');
        $manager = $this->userWithRole('Team Manager');
        $owner = $this->userWithRole('Team Owner');
        $team->users()->attach($manager->id, ['role' => 'Player']);
        $team->users()->attach($owner->id, ['role' => 'Player']);
        $this->assertSame($owner->id, $team->fresh()->loginAsUser()?->id);

        // A team set up before the roles existed: the pivot is the only signal there is.
        $legacy = $this->makeTeam($org, 'Legacy Team');
        $legacyUser = $this->userWithRole('Player');
        $legacy->users()->attach($legacyUser->id, ['role' => 'Owner']);
        $this->assertSame($legacyUser->id, $legacy->fresh()->loginAsUser()?->id);

        // Case should not decide whether an admin can log in.
        $lower = $this->makeTeam($org, 'Lowercase Team');
        $lowerUser = $this->userWithRole('Player');
        $lower->users()->attach($lowerUser->id, ['role' => 'owner']);
        $this->assertSame($lowerUser->id, $lower->fresh()->loginAsUser()?->id);
    }

    #[Test]
    public function a_team_of_players_alone_offers_nobody(): void
    {
        $org = $this->makeOrganization();
        $team = $this->makeTeam($org, 'Players Only');

        foreach (['Player', 'captain'] as $pivotRole) {
            $team->users()->attach($this->userWithRole('Player')->id, ['role' => $pivotRole]);
        }

        /*
         * Deliberately null rather than "any attached user": dropping an admin into a Player
         * account shows the player dashboard and looks broken. The list shows an "add one" link
         * instead, which is the actual fix.
         */
        $this->assertNull($team->fresh()->loginAsUser());
        $this->assertNull($this->makeTeam($org, 'Empty Team')->loginAsUser());
    }

    #[Test]
    public function the_teams_list_offers_the_option_to_an_admin(): void
    {
        $org = $this->makeOrganization();
        $team = $this->makeTeam($org, 'Listed Team');
        $manager = $this->userWithRole('Team Manager');
        $team->users()->attach($manager->id, ['role' => 'Player']);

        $role = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        foreach (['actual-team.view', 'actual-team.edit', 'user.login_as'] as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web'],
                ['group_name' => 'team']
            ));
        }

        $admin = $this->makeAuctionOperator($org);
        $admin->assignRole($role);

        $this->actingAs($admin->fresh())
            ->get(route('admin.actual-teams.index'))
            ->assertOk()
            ->assertSee('Login as Team')
            ->assertSee(route('admin.users.login-as', $manager->id), false);
    }
}

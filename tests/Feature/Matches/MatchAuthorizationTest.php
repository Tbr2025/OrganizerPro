<?php

declare(strict_types=1);

namespace Tests\Feature\Matches;

use App\Models\Matches;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Who may change a match.
 *
 * MatchesController had no authorization of any kind. Team managers reach it deliberately —
 * RedirectTeamManager allowlists `admin/matches*` so the Matches item in their menu works — and
 * the route group only checks that they are logged in. A manager could POST go-live or cancel a
 * match, or open the edit form, by knowing the URL.
 *
 * The permissions already existed and were already assigned correctly (a Team Manager holds
 * `match.view` and nothing else in the group). Only the check was missing, which is why this
 * tests the ROUTES rather than the buttons: hiding a button is not access control.
 */
class MatchAuthorizationTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function makeMatch(): Matches
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org, 'open');
        $teamA = $this->makeTeam($org, 'Alpha', $tournament);
        $teamB = $this->makeTeam($org, 'Bravo', $tournament);

        return Matches::create([
            'tournament_id' => $tournament->id,
            'name' => 'Alpha vs Bravo',
            'slug' => 'alpha-vs-bravo-' . uniqid(),
            'team_a_id' => $teamA->id,
            'team_b_id' => $teamB->id,
            'status' => 'upcoming',
        ]);
    }

    private function userWith(array $permissions, string $roleName)
    {
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

        foreach ($permissions as $name) {
            $role->givePermissionTo(Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['group_name' => 'match']
            ));
        }

        $user = $this->makeAuctionOperator($this->makeOrganization('Perm Org'));
        $user->assignRole($role);

        return $user->fresh();
    }

    #[Test]
    public function a_team_manager_may_look_at_matches_but_not_change_them(): void
    {
        $match = $this->makeMatch();

        // Exactly what a Team Manager holds in the match group today.
        $manager = $this->userWith(['match.view'], 'Team Manager');

        $this->actingAs($manager)->get(route('admin.matches.index'))->assertOk();

        /*
         * Each of these was reachable by URL. They must be refused by the SERVER — the buttons
         * being hidden in the view is a courtesy to the manager, not a control.
         */
        $this->actingAs($manager)->get(route('admin.matches.edit', $match))->assertForbidden();
        $this->actingAs($manager)->post(route('admin.matches.goLive', $match))->assertForbidden();
        $this->actingAs($manager)->post(route('admin.matches.cancel', $match), [
            'cancellation_reason' => 'trying it on',
        ])->assertForbidden();
        $this->actingAs($manager)->delete(route('admin.matches.destroy', $match))->assertForbidden();

        // And nothing actually changed.
        $match->refresh();
        $this->assertSame('upcoming', $match->status);
        $this->assertFalse((bool) $match->is_cancelled);
        $this->assertNotNull(Matches::find($match->id));
    }

    #[Test]
    public function a_team_manager_cannot_record_the_result_or_score_the_match(): void
    {
        $match = $this->makeMatch();
        $manager = $this->userWith(['match.view'], 'Team Manager');

        /*
         * MatchSummaryController, BallController and ScorecardController had no authorization at
         * all, and admin/matches* is allowlisted for team managers — so a manager could open the
         * summary editor, record a result, assign awards and enter ball-by-ball scores for a
         * match their own team was playing in.
         */
        $this->actingAs($manager)->get(route('admin.matches.summary.edit', $match))->assertForbidden();
        $this->actingAs($manager)->post(route('admin.balls.store', $match), [])->assertForbidden();
        $this->actingAs($manager)->get(route('admin.balls.create', $match))->assertForbidden();

        // Reading is still fine — following your own match is the point of the account.
        $this->actingAs($manager)->get(route('admin.matches.show', $match))->assertOk();
    }

    #[Test]
    public function the_match_page_offers_a_manager_nothing_that_changes_the_match(): void
    {
        $match = $this->makeMatch();
        $manager = $this->userWith(['match.view'], 'Team Manager');

        $html = $this->actingAs($manager)->get(route('admin.matches.show', $match))->assertOk()->getContent();

        // Hiding a control is a courtesy, not the control itself — but a page that offers buttons
        // which then 403 is its own bug, so the view has to agree with the controller.
        foreach (['Record Final Result', 'Quick Score Entry', 'Set Toss', 'Edit Match'] as $offered) {
            $this->assertStringNotContainsString($offered, $html, "\"{$offered}\" must not be offered to a team manager.");
        }

        // What they should still get.
        $this->assertStringContainsString('Actions', $html);
        $this->assertStringContainsString('View Public Fixture', $html);
    }

    #[Test]
    public function someone_with_match_edit_can_still_do_all_of_it(): void
    {
        $match = $this->makeMatch();
        $admin = $this->userWith(['match.view', 'match.edit'], 'Match Editor');

        // The guard must not have locked out the people it is there to let through.
        $this->actingAs($admin)->get(route('admin.matches.edit', $match))->assertOk();
        $this->actingAs($admin)->post(route('admin.matches.goLive', $match))->assertRedirect();

        $this->assertSame('live', $match->fresh()->status);
    }
}

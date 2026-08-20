<?php

declare(strict_types=1);

namespace Tests\Feature\Tournament;

use App\Models\ActualTeam;
use App\Models\Matches;
use App\Models\Organization;
use App\Models\Tournament;
use App\Models\TournamentGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Enabling, disabling and bulk-deleting fixtures.
 *
 * Two rules carry the weight here, and both are about not losing work by accident:
 *
 *   - **Disabled is not deleted, and not cancelled either.** A drafted schedule has to come off
 *     the public site without being destroyed, and without telling spectators a match was called
 *     off — which is what marking it cancelled says.
 *   - **A played fixture survives a bulk delete.** Deleting one takes its scorecard and silently
 *     moves the points table, and nobody watching a bulk action would connect the two.
 */
class FixtureBulkActionsTest extends TestCase
{
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

    /** @return array<string, mixed> */
    private function scenario(): array
    {
        // Unique: this scenario is built twice in the cross-tournament test.
        $org = Organization::create(['name' => 'Org ' . uniqid()]);
        $tournament = Tournament::create([
            'name' => 'Cup', 'slug' => 'cup-' . uniqid(),
            'start_date' => now()->addWeek(), 'organization_id' => $org->id, 'type' => 'open',
        ]);

        $poolA = TournamentGroup::create(['tournament_id' => $tournament->id, 'name' => 'Pool A']);
        $poolB = TournamentGroup::create(['tournament_id' => $tournament->id, 'name' => 'Pool B']);

        $teamA = ActualTeam::create(['name' => 'A', 'organization_id' => $org->id, 'tournament_id' => $tournament->id]);
        $teamB = ActualTeam::create(['name' => 'B', 'organization_id' => $org->id, 'tournament_id' => $tournament->id]);

        $make = fn (TournamentGroup $g, int $n, array $extra = []) => Matches::create(array_merge([
            'tournament_id' => $tournament->id,
            'tournament_group_id' => $g->id,
            'name' => 'Match ' . $n,
            'match_number' => $n,
            'stage' => 'group',
            'status' => 'upcoming',
            'match_date' => now()->addDays($n),
            'team_a_id' => $teamA->id,
            'team_b_id' => $teamB->id,
        ], $extra));

        return [
            'org' => $org, 'tournament' => $tournament,
            'poolA' => $poolA, 'poolB' => $poolB,
            'a1' => $make($poolA, 1), 'a2' => $make($poolA, 2),
            'b1' => $make($poolB, 3),
            // Played: this one must survive a bulk delete.
            'played' => $make($poolA, 4, ['status' => 'completed', 'winner_team_id' => $teamA->id]),
        ];
    }

    #[Test]
    public function fixtures_are_public_by_default(): void
    {
        $c = $this->scenario();

        // The column defaults to true so migrating does not empty a live tournament's page.
        $this->assertTrue($c['a1']->fresh()->is_published);
    }

    #[Test]
    public function disabling_a_pool_hides_only_that_pool_from_the_public_page(): void
    {
        $c = $this->scenario();

        $this->actingAs($this->admin($c['org']))
            ->post(route('admin.tournaments.fixtures.bulk-publish', $c['tournament']), [
                'published' => 0,
                'group_id' => $c['poolA']->id,
            ])
            ->assertRedirect();

        $this->assertFalse($c['a1']->fresh()->is_published);
        $this->assertFalse($c['a2']->fresh()->is_published);
        $this->assertTrue($c['b1']->fresh()->is_published, 'Pool B must be untouched');

        // Nothing was deleted, and nothing was marked cancelled.
        $this->assertDatabaseHas('matches', ['id' => $c['a1']->id, 'is_cancelled' => 0]);

        // Gone from the public fixtures page, still on the admin one.
        $public = $this->get(route('public.tournament.fixtures', $c['tournament']))->assertOk();
        $public->assertDontSee('Match 1');
        $public->assertSee('Match 3');

        $this->actingAs($this->admin($c['org']))
            ->get(route('admin.tournaments.fixtures.index', $c['tournament']))
            ->assertOk()
            ->assertSee('Match 1');
    }

    #[Test]
    public function disabling_a_stage_hides_every_pool_in_it(): void
    {
        $c = $this->scenario();

        $this->actingAs($this->admin($c['org']))
            ->post(route('admin.tournaments.fixtures.bulk-publish', $c['tournament']), [
                'published' => 0,
                'stage' => 'group',
            ])
            ->assertRedirect();

        foreach (['a1', 'a2', 'b1'] as $key) {
            $this->assertFalse($c[$key]->fresh()->is_published);
        }
    }

    #[Test]
    public function enabling_puts_them_back(): void
    {
        $c = $this->scenario();
        $admin = $this->admin($c['org']);

        $this->actingAs($admin)->post(route('admin.tournaments.fixtures.bulk-publish', $c['tournament']), [
            'published' => 0, 'stage' => 'group',
        ]);

        $this->actingAs($admin)->post(route('admin.tournaments.fixtures.bulk-publish', $c['tournament']), [
            'published' => 1, 'ids' => [$c['a1']->id, $c['b1']->id],
        ])->assertRedirect();

        $this->assertTrue($c['a1']->fresh()->is_published);
        $this->assertTrue($c['b1']->fresh()->is_published);
        $this->assertFalse($c['a2']->fresh()->is_published, 'only the two named come back');
    }

    #[Test]
    public function an_unscoped_publish_call_is_refused(): void
    {
        $c = $this->scenario();

        /*
         * With every filter absent this would publish the whole tournament, which no button asks
         * for — so it can only be a bug or a hand-made request, and both deserve a refusal rather
         * than a silent mass update.
         */
        $this->actingAs($this->admin($c['org']))
            ->post(route('admin.tournaments.fixtures.bulk-publish', $c['tournament']), ['published' => 0])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertTrue($c['a1']->fresh()->is_published);
    }

    #[Test]
    public function bulk_delete_removes_the_selected_fixtures(): void
    {
        $c = $this->scenario();

        $this->actingAs($this->admin($c['org']))
            ->delete(route('admin.tournaments.fixtures.bulk-delete', $c['tournament']), [
                'ids' => [$c['a1']->id, $c['b1']->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('matches', ['id' => $c['a1']->id]);
        $this->assertDatabaseMissing('matches', ['id' => $c['b1']->id]);
        $this->assertDatabaseHas('matches', ['id' => $c['a2']->id]);
    }

    #[Test]
    public function a_played_fixture_survives_a_bulk_delete_and_is_named(): void
    {
        $c = $this->scenario();

        $response = $this->actingAs($this->admin($c['org']))
            ->delete(route('admin.tournaments.fixtures.bulk-delete', $c['tournament']), [
                'ids' => [$c['a1']->id, $c['played']->id],
            ])
            ->assertRedirect();

        // The unplayed one goes, the played one stays.
        $this->assertDatabaseMissing('matches', ['id' => $c['a1']->id]);
        $this->assertDatabaseHas('matches', ['id' => $c['played']->id]);

        // And it is NAMED, not merely counted — "1 was kept" leaves someone hunting for which.
        $response->assertSessionHas('kept_fixtures');
        $this->assertStringContainsString('Match 4', session('kept_fixtures'));
    }

    #[Test]
    public function fixtures_from_another_tournament_cannot_be_deleted_through_this_route(): void
    {
        $c = $this->scenario();
        $other = $this->scenario();

        $this->actingAs($this->admin($c['org']))
            ->delete(route('admin.tournaments.fixtures.bulk-delete', $c['tournament']), [
                'ids' => [$other['a1']->id],
            ])
            ->assertRedirect();

        // Scoped to the tournament in the URL, not merely to the ids posted.
        $this->assertDatabaseHas('matches', ['id' => $other['a1']->id]);
    }

    #[Test]
    public function a_viewer_without_edit_permission_cannot_bulk_delete(): void
    {
        $c = $this->scenario();

        $role = Role::firstOrCreate(['name' => 'Viewer', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(
            ['name' => 'tournament.view', 'guard_name' => 'web'],
            ['group_name' => 'tournament']
        ));
        $viewer = User::factory()->create(['organization_id' => $c['org']->id])->assignRole($role);

        $this->actingAs($viewer)
            ->delete(route('admin.tournaments.fixtures.bulk-delete', $c['tournament']), [
                'ids' => [$c['a1']->id],
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('matches', ['id' => $c['a1']->id]);
    }

    #[Test]
    public function the_page_renders_its_sections_and_selection_controls(): void
    {
        $c = $this->scenario();

        $page = $this->actingAs($this->admin($c['org']))
            ->get(route('admin.tournaments.fixtures.index', $c['tournament']))
            ->assertOk();

        // Stage section, and a pool section inside it.
        $page->assertSee('Group Stage');
        $page->assertSee('Pool A');
        $page->assertSee('Pool B');

        // The section controls and the selection machinery.
        $page->assertSee('Enable stage', false);
        $page->assertSee('Disable pool', false);
        $page->assertSee('fxBulkDeleteForm', false);
        $page->assertSee('askDelete', false);
    }
}

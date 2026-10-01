<?php

declare(strict_types=1);

namespace Tests\Feature\Tournament;

use App\Models\GeneratedPoster;
use App\Models\TournamentGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * The admin point-table page generated a poster and then showed nothing but a flash message —
 * and its tables were empty, because it looked groups up by id in a collection keyed by name.
 */
class PointTablePosterPageTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    #[Test]
    public function the_generated_poster_is_shown_and_kept_in_the_gallery(): void
    {
        Storage::fake('public');

        $org = $this->makeOrganization('Org ' . uniqid());
        $tournament = $this->makeTournament($org, 'open');
        $group = TournamentGroup::create(['tournament_id' => $tournament->id, 'name' => 'Pool A']);
        $team = $this->makeTeam($org, 'Titans', $tournament);
        $group->teams()->attach($team->id);
        $role = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        foreach (['tournament.view', 'tournament.edit'] as $name) {
            $role->givePermissionTo(Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['group_name' => 'tournament']
            ));
        }
        $admin = User::factory()->create(['organization_id' => $org->id])->assignRole($role);

        $page = route('admin.tournaments.point-table.index', $tournament);

        $this->actingAs($admin)->from($page)
            ->post(route('admin.tournaments.point-table.generate-poster', $tournament), ['group_id' => $group->id])
            ->assertRedirect($page)
            ->assertSessionHas('generated_posters');

        $poster = GeneratedPoster::where('tournament_id', $tournament->id)->firstOrFail();
        $this->assertSame('Pool A', $poster->label);
        Storage::disk('public')->assertExists($poster->image_path);

        $this->actingAs($admin)->get($page)
            ->assertOk()
            ->assertSee('Generated posters')
            ->assertSee('storage/' . $poster->image_path, false)
            ->assertSee('Titans')
            // No team is marked qualified, so the column is not drawn at all.
            ->assertDontSee('tracking-wider">Qualified</th>', false);
    }

    #[Test]
    public function recalculating_keeps_the_organizers_qualified_marks_and_adds_none(): void
    {
        $org = $this->makeOrganization('Org ' . uniqid());
        $tournament = $this->makeTournament($org, 'open');
        $group = TournamentGroup::create(['tournament_id' => $tournament->id, 'name' => 'Pool A']);
        $teams = collect(['Titans', 'Spartans', 'Bulls'])->map(fn ($n) => $this->makeTeam($org, $n, $tournament));
        $group->teams()->attach($teams->pluck('id'));

        $service = app(\App\Services\Tournament\PointTableService::class);
        $service->recalculatePointTable($tournament->fresh());
        $this->assertSame(0, \App\Models\PointTableEntry::where('qualified', true)->count(),
            'A rebuild marked teams qualified on its own.');

        \App\Models\PointTableEntry::where('actual_team_id', $teams[2]->id)->update(['qualified' => true]);
        $service->recalculatePointTable($tournament->fresh());

        $this->assertSame([$teams[2]->id],
            \App\Models\PointTableEntry::where('qualified', true)->pluck('actual_team_id')->all());
    }

    #[Test]
    public function the_public_table_gives_phones_a_short_code_for_long_team_names(): void
    {
        $org = $this->makeOrganization('Org ' . uniqid());
        $tournament = $this->makeTournament($org, 'open');
        $group = TournamentGroup::create(['tournament_id' => $tournament->id, 'name' => 'Pool A']);
        $team = $this->makeTeam($org, 'Kerala Super Kings', $tournament);
        // short_name on live is usually the full name again, which saves no space.
        $team->update(['short_name' => 'Kerala Super Kings']);
        $group->teams()->attach($team->id);
        app(\App\Services\Tournament\PointTableService::class)->initializePointTable($tournament->fresh());

        $this->get(route('public.tournament.point-table', $tournament->slug))
            ->assertOk()
            ->assertSee('<span class="sm:hidden">KSK</span>', false)
            ->assertSee('<span class="hidden sm:inline">Kerala Super Kings</span>', false);
    }
}

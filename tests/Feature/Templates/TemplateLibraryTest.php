<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use App\Models\Organization;
use App\Models\Tournament;
use App\Models\TournamentTemplate;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\RolesService;
use App\Services\Tournament\SeasonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Template presets: templates with no tournament, global (Super Admin) or one
 * organization's (its admins), ticked when a tournament or season is created.
 */
class TemplateLibraryTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Organization $other;
    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        app(PermissionService::class)->createPermissions();
        app(RolesService::class)->createPredefinedRoles();

        $this->org = Organization::create(['name' => 'Club']);
        $this->other = Organization::create(['name' => 'Rival']);
        $this->super = User::factory()->create(['organization_id' => $this->org->id])->assignRole('Superadmin');
    }

    private function preset(string $type, string $name, ?Organization $org = null, bool $default = false, bool $ticked = true): TournamentTemplate
    {
        Storage::disk('public')->put($bg = 'tournament_templates/library/bg_' . uniqid() . '.png', 'png');

        return TournamentTemplate::create([
            'tournament_id' => null, 'organization_id' => $org?->id, 'type' => $type, 'name' => $name, 'background_image' => $bg,
            'layout_json' => [['type' => 'text', 'text' => $name, 'x' => 50, 'y' => 50]],
            'canvas_width' => 1080, 'canvas_height' => 1350, 'is_default' => $default, 'is_active' => $ticked,
        ]);
    }

    private function orgAdmin(?Organization $org = null): User
    {
        return User::factory()->create(['organization_id' => ($org ?? $this->org)->id])->assignRole('Organizer');
    }

    private function createTournament(User $as, string $name, array $presets = [], ?Organization $org = null): Tournament
    {
        $this->actingAs($as)->post(route('admin.tournaments.store'), [
            'organization_id' => ($org ?? $this->org)->id, 'name' => $name,
            'start_date' => now()->addDay()->toDateString(), 'end_date' => now()->addDays(9)->toDateString(),
            'presets' => array_map(fn ($p) => $p->id, $presets),
        ])->assertRedirect();

        return Tournament::where('name', $name)->firstOrFail();
    }

    #[Test]
    public function a_new_tournament_gets_exactly_the_presets_ticked(): void
    {
        $welcome = $this->preset('welcome_card', 'Welcome', default: true);
        $match = $this->preset('match_poster', 'Match');
        $this->preset('flyer', 'Flyer');

        $templates = $this->createTournament($this->super, 'Lib Cup', [$welcome, $match])->templates()->get();

        $this->assertEqualsCanonicalizing(['Welcome', 'Match'], $templates->pluck('name')->all());
        $copy = $templates->firstWhere('name', 'Welcome');
        $this->assertTrue($copy->is_default);
        $this->assertTrue($templates->firstWhere('name', 'Match')->is_default, 'The only one of its type becomes the default');
        $this->assertNull($copy->organization_id);
        $this->assertNotSame($welcome->background_image, $copy->background_image, 'Files are copied, never shared');
        Storage::disk('public')->assertExists($copy->background_image);

        $this->assertSame(0, $this->createTournament($this->super, 'Bare Cup')->templates()->count());
    }

    #[Test]
    public function the_organizations_default_wins_over_the_global_one(): void
    {
        $global = $this->preset('welcome_card', 'Global Welcome', default: true);
        $own = $this->preset('welcome_card', 'Club Welcome', $this->org, default: true);

        $templates = $this->createTournament($this->orgAdmin(), 'Club Cup', [$global, $own])->templates()->get();

        $this->assertTrue($templates->firstWhere('name', 'Club Welcome')->is_default);
        $this->assertFalse($templates->firstWhere('name', 'Global Welcome')->is_default);
    }

    #[Test]
    public function another_organizations_presets_are_never_offered_or_copied(): void
    {
        $theirs = $this->preset('welcome_card', 'Rival Welcome', $this->other);
        $global = $this->preset('match_poster', 'Global Match');
        $organizer = $this->orgAdmin();

        $this->actingAs($organizer)->get(route('admin.tournaments.create'))->assertOk()
            ->assertSee('Global Match')->assertDontSee('Rival Welcome');

        $cup = $this->createTournament($organizer, 'Club Cup', [$theirs, $global]);
        $this->assertSame(['Global Match'], $cup->templates()->pluck('name')->all());

        $this->actingAs($organizer)->get(route('admin.template-library.edit', $theirs))->assertForbidden();
        $this->actingAs($organizer)->get(route('admin.template-library.thumb', $theirs))->assertNotFound();
    }

    #[Test]
    public function an_org_admin_builds_their_own_presets_and_can_only_copy_a_global_one(): void
    {
        $organizer = $this->orgAdmin();
        $global = $this->preset('welcome_card', 'Global Welcome', default: true);

        $this->actingAs($organizer)->get(route('admin.template-library.index'))->assertOk()->assertSee('Club presets');

        $this->actingAs($organizer)->postJson(route('admin.template-library.store'), [
            'name' => 'Ours', 'type' => 'flyer', 'layout_json' => '[]', 'scope' => 'global',
        ])->assertOk();
        $ours = TournamentTemplate::where('name', 'Ours')->firstOrFail();
        $this->assertSame($this->org->id, $ours->organization_id, 'An org admin cannot post into the global scope');
        $this->assertTrue($ours->is_default, 'The first of its type in the scope');

        $this->actingAs($organizer)->putJson(route('admin.template-library.update', $global), ['name' => 'x', 'type' => 'welcome_card'])->assertForbidden();

        $this->actingAs($organizer)->post(route('admin.template-library.duplicate', $global))->assertRedirect();
        $copy = TournamentTemplate::whereNull('tournament_id')->where('organization_id', $this->org->id)->where('type', 'welcome_card')->firstOrFail();
        $this->assertSame('Global Welcome', $copy->name);
        $this->assertTrue($global->fresh()->is_default, 'A scope\'s defaults never touch another scope');
    }

    #[Test]
    public function editing_a_preset_reaches_the_next_tournament_but_not_one_that_exists(): void
    {
        $preset = $this->preset('welcome_card', 'Welcome', default: true);
        $first = $this->createTournament($this->super, 'First Cup', [$preset]);

        $this->actingAs($this->super)->putJson(route('admin.template-library.update', $preset), [
            'name' => 'Welcome v2', 'type' => 'welcome_card', 'layout_json' => json_encode([['type' => 'text', 'text' => 'NEW', 'x' => 50, 'y' => 50]]),
        ])->assertOk()->assertJson(['success' => true]);

        $second = $this->createTournament($this->super, 'Second Cup', [$preset]);

        $this->assertSame('Welcome', $first->templates()->value('name'));
        $this->assertSame('NEW', $second->templates()->first()->layout_json[0]['text']);
    }

    #[Test]
    public function a_ready_made_design_becomes_an_editable_preset_in_the_chosen_scope(): void
    {
        $this->actingAs($this->orgAdmin())->post(route('admin.template-library.add-ready-made'), ['preset' => 'flyer_bold', 'theme' => 'royal'])
            ->assertRedirect();
        $made = TournamentTemplate::where('preset_key', 'flyer_bold')->firstOrFail();
        $this->assertNull($made->tournament_id);
        $this->assertSame($this->org->id, $made->organization_id);
        $this->assertSame('royal', $made->preset_theme);

        $this->actingAs($this->super)->post(route('admin.template-library.add-ready-made', ['scope' => 'global']), ['preset' => 'flyer_bold'])->assertRedirect();
        $this->assertSame(1, TournamentTemplate::whereNull('tournament_id')->whereNull('organization_id')->count());
    }

    #[Test]
    public function the_picker_shows_a_rendered_picture_of_each_preset(): void
    {
        $preset = $this->preset('flyer', 'Flyer');

        $this->actingAs($this->orgAdmin())->get(route('admin.template-library.thumb', $preset))->assertOk();
        $this->assertCount(1, Storage::disk('public')->files('preset-thumbs'));
    }

    #[Test]
    public function presets_and_tournament_templates_never_cross(): void
    {
        $preset = $this->preset('welcome_card', 'Welcome');
        $cup = $this->createTournament($this->super, 'Cup', [$preset]);
        $own = $cup->templates()->first();

        $this->actingAs($this->super)->get(route('admin.tournaments.templates.edit', [$cup, $preset]))->assertNotFound();
        $this->actingAs($this->super)->get(route('admin.template-library.edit', $own))->assertNotFound();

        $organizer = $this->orgAdmin();
        $cup->organizers()->attach($organizer->id); // an Organizer reaches only their own tournaments
        $this->actingAs($organizer)->post(route('admin.template-library.save-from-tournament', [$cup, $own]), ['scope' => 'global'])->assertRedirect();
        $this->assertSame($this->org->id, TournamentTemplate::whereNull('tournament_id')->latest('id')->value('organization_id'));

        $rival = $this->createTournament($this->super, 'Rival Cup', [], $this->other);
        $rival->organizers()->attach($organizer->id);
        // Another organization's tournament is out of reach (hidden by its scope, or refused).
        $status = $this->actingAs($organizer)->post(route('admin.template-library.copy-to-tournament', $rival), ['presets' => [$preset->id]])->status();
        $this->assertContains($status, [403, 404]);
        $this->assertSame(0, $rival->templates()->count());
        $this->actingAs($this->super)->post(route('admin.template-library.copy-to-tournament', $rival), ['presets' => [$preset->id]])->assertRedirect();
        $this->assertSame(1, $rival->templates()->count());
    }

    #[Test]
    public function a_preset_can_start_from_a_previous_tournaments_template(): void
    {
        $organizer = $this->orgAdmin();
        $past = $this->createTournament($this->super, 'Past Cup');
        $past->organizers()->attach($organizer->id);
        Storage::disk('public')->put($bg = 'tournament_templates/past.png', 'png');
        $old = TournamentTemplate::create(['tournament_id' => $past->id, 'type' => 'match_poster', 'name' => 'Past Match', 'background_image' => $bg,
            'layout_json' => [['type' => 'text', 'text' => 'VS', 'x' => 50, 'y' => 50]], 'canvas_width' => 1080, 'canvas_height' => 1080]);

        // Offered on the presets page, listed with pictures.
        $this->actingAs($organizer)->get(route('admin.template-library.index'))->assertOk()->assertSee('Past Cup');
        $list = $this->actingAs($organizer)->getJson(route('admin.template-library.tournament-templates', ['tournament' => $past->id]))->assertOk()->json('templates');
        $this->assertSame(['Past Match'], array_column($list, 'name'));
        $this->actingAs($organizer)->get($list[0]['thumb'])->assertOk();

        // Copied into the organization's presets, files and all, and opened in the editor.
        $this->actingAs($organizer)->post(route('admin.template-library.import', ['template' => $old, 'scope' => 'global']))
            ->assertRedirectContains('/template-library/');
        $preset = TournamentTemplate::whereNull('tournament_id')->where('name', 'Past Match')->firstOrFail();
        $this->assertSame($this->org->id, $preset->organization_id);
        $this->assertSame('VS', $preset->layout_json[0]['text']);
        $this->assertNotSame($bg, $preset->background_image);
        $this->assertSame(1, $past->templates()->count(), 'The tournament keeps its own');

        // Super Admin picks the scope.
        $this->actingAs($this->super)->post(route('admin.template-library.import', ['template' => $old, 'scope' => 'global']))->assertRedirect();
        $this->assertSame(1, TournamentTemplate::whereNull('tournament_id')->whereNull('organization_id')->count());

        // Never another organization's tournament, nor one an Organizer is not on.
        $rival = $this->createTournament($this->super, 'Rival Cup', [], $this->other);
        $theirs = TournamentTemplate::create(['tournament_id' => $rival->id, 'type' => 'flyer', 'name' => 'Rival Flyer', 'layout_json' => []]);
        $this->actingAs($organizer)->get(route('admin.template-library.index'))->assertDontSee('Rival Cup');
        $this->actingAs($organizer)->getJson(route('admin.template-library.tournament-templates', ['tournament' => $rival->id]))->assertNotFound();
        $this->actingAs($organizer)->post(route('admin.template-library.import', $theirs))->assertNotFound();

        $unassigned = $this->createTournament($this->super, 'Other Club Cup');
        $this->actingAs($organizer)->getJson(route('admin.template-library.tournament-templates', ['tournament' => $unassigned->id]))->assertNotFound();
    }

    #[Test]
    public function only_roles_with_the_presets_permission_reach_them(): void
    {
        $viewer = User::factory()->create(['organization_id' => $this->org->id])->assignRole('Viewer');
        $this->actingAs($viewer)->get(route('admin.template-library.index'))->assertForbidden();
        $this->actingAs($this->super)->get(route('admin.template-library.index', ['scope' => $this->other->id]))->assertOk()->assertSee('Rival presets');
    }

    #[Test]
    public function a_new_season_can_start_from_ticked_presets_instead_of_last_season(): void
    {
        $preset = $this->preset('welcome_card', 'Preset Welcome', default: true);
        $this->preset('flyer', 'Unticked Flyer');
        $from = $this->createTournament($this->super, 'Season Cup');
        TournamentTemplate::create(['tournament_id' => $from->id, 'type' => 'flyer', 'name' => 'Old Flyer', 'layout_json' => []]);

        $seasons = app(SeasonService::class);
        $dates = ['start_date' => now()->addMonth()->toDateString(), 'end_date' => now()->addMonths(2)->toDateString()];

        $fromPresets = $seasons->startNextSeason($from, ['name' => 'S2', 'copy' => ['library'], 'presets' => [$preset->id]] + $dates, $this->super);
        $fromLast = $seasons->startNextSeason($from, ['name' => 'S3', 'copy' => ['templates']] + $dates, $this->super);

        $this->assertSame(['Preset Welcome'], $fromPresets->templates()->pluck('name')->all());
        $this->assertSame(['Old Flyer'], $fromLast->templates()->pluck('name')->all());
    }
}

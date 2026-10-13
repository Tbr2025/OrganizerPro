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
 * The template library: Super Admin's templates (no tournament) that every new
 * tournament — and, when chosen, a new season — starts with a copy of.
 */
class TemplateLibraryTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        app(PermissionService::class)->createPermissions();
        app(RolesService::class)->createPredefinedRoles();

        $this->org = Organization::create(['name' => 'Club']);
        $this->super = User::factory()->create(['organization_id' => $this->org->id])->assignRole('Superadmin');
    }

    private function libraryTemplate(string $type, string $name, bool $default = false): TournamentTemplate
    {
        Storage::disk('public')->put($bg = 'tournament_templates/library/bg_' . uniqid() . '.png', 'png');

        return TournamentTemplate::create([
            'tournament_id' => null, 'type' => $type, 'name' => $name, 'background_image' => $bg,
            'layout_json' => [['type' => 'text', 'text' => $name, 'x' => 50, 'y' => 50]],
            'canvas_width' => 1080, 'canvas_height' => 1350, 'is_default' => $default, 'is_active' => true,
        ]);
    }

    private function createTournament(string $name, array $extra = []): Tournament
    {
        $this->actingAs($this->super)->post(route('admin.tournaments.store'), [
            'organization_id' => $this->org->id, 'name' => $name,
            'start_date' => now()->addDay()->toDateString(), 'end_date' => now()->addDays(9)->toDateString(),
        ] + $extra)->assertRedirect();

        return Tournament::where('name', $name)->firstOrFail();
    }

    #[Test]
    public function a_new_tournament_starts_with_its_own_copy_of_the_library(): void
    {
        $welcome = $this->libraryTemplate(TournamentTemplate::TYPE_WELCOME_CARD, 'Welcome', default: true);
        $this->libraryTemplate(TournamentTemplate::TYPE_MATCH_POSTER, 'Match');
        $this->libraryTemplate(TournamentTemplate::TYPE_FLYER, 'Hidden')->update(['is_active' => false]);

        $templates = $this->createTournament('Lib Cup', ['starter_kit' => 1])->templates()->get();

        $this->assertEqualsCanonicalizing(['Welcome', 'Match'], $templates->pluck('name')->all());
        $copy = $templates->firstWhere('name', 'Welcome');
        $this->assertTrue($copy->is_default);
        $this->assertNotSame($welcome->background_image, $copy->background_image, 'Files are copied, never shared');
        Storage::disk('public')->assertExists($copy->background_image);
    }

    #[Test]
    public function an_empty_library_or_an_unticked_box_gives_no_templates(): void
    {
        $this->assertSame(0, $this->createTournament('Empty Cup', ['starter_kit' => 1])->templates()->count());

        $this->libraryTemplate(TournamentTemplate::TYPE_WELCOME_CARD, 'Welcome');
        $this->assertSame(0, $this->createTournament('Bare Cup')->templates()->count());
    }

    #[Test]
    public function editing_the_library_reaches_the_next_tournament_but_not_one_that_exists(): void
    {
        $lib = $this->libraryTemplate(TournamentTemplate::TYPE_WELCOME_CARD, 'Welcome', default: true);
        $first = $this->createTournament('First Cup', ['starter_kit' => 1]);

        $this->actingAs($this->super)->putJson(route('admin.template-library.update', $lib), [
            'name' => 'Welcome v2', 'type' => $lib->type, 'layout_json' => json_encode([['type' => 'text', 'text' => 'NEW', 'x' => 50, 'y' => 50]]),
        ])->assertOk()->assertJson(['success' => true]);

        $second = $this->createTournament('Second Cup', ['starter_kit' => 1]);

        $this->assertSame('Welcome', $first->templates()->value('name'));
        $this->assertSame('Welcome v2', $second->templates()->value('name'));
        $this->assertSame('NEW', $second->templates()->first()->layout_json[0]['text']);
    }

    #[Test]
    public function library_and_tournament_templates_never_cross(): void
    {
        $lib = $this->libraryTemplate(TournamentTemplate::TYPE_WELCOME_CARD, 'Welcome');
        $cup = $this->createTournament('Cup', ['starter_kit' => 1]);
        $own = $cup->templates()->first();

        $this->actingAs($this->super)->get(route('admin.tournaments.templates.edit', [$cup, $lib]))->assertNotFound();
        $this->actingAs($this->super)->get(route('admin.template-library.edit', $own))->assertNotFound();
        $this->actingAs($this->super)->get(route('admin.template-library.edit', $lib))->assertOk();

        $this->actingAs($this->super)->post(route('admin.template-library.save-from-tournament', [$cup, $own]))->assertRedirect();
        $this->assertSame(2, TournamentTemplate::whereNull('tournament_id')->count());
    }

    #[Test]
    public function only_super_admin_manages_the_library(): void
    {
        $organizer = User::factory()->create(['organization_id' => $this->org->id])->assignRole('Organizer');

        $this->actingAs($organizer)->get(route('admin.template-library.index'))->assertForbidden();
        $this->actingAs($this->super)->get(route('admin.template-library.index'))->assertOk();
    }

    #[Test]
    public function a_new_season_can_start_from_the_library_instead_of_last_season(): void
    {
        $this->libraryTemplate(TournamentTemplate::TYPE_WELCOME_CARD, 'Library Welcome', default: true);
        $from = $this->createTournament('Season Cup');
        TournamentTemplate::create(['tournament_id' => $from->id, 'type' => 'flyer', 'name' => 'Old Flyer', 'layout_json' => []]);

        $seasons = app(SeasonService::class);
        $dates = ['start_date' => now()->addMonth()->toDateString(), 'end_date' => now()->addMonths(2)->toDateString()];

        $fromLibrary = $seasons->startNextSeason($from, ['name' => 'S2', 'copy' => ['library']] + $dates, $this->super);
        $fromLast = $seasons->startNextSeason($from, ['name' => 'S3', 'copy' => ['templates']] + $dates, $this->super);

        $this->assertSame(['Library Welcome'], $fromLibrary->templates()->pluck('name')->all());
        $this->assertSame(['Old Flyer'], $fromLast->templates()->pluck('name')->all());
    }
}

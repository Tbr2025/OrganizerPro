<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use App\Models\Credits\CreditFeature;
use App\Models\Organization;
use App\Models\TemplatePreset;
use App\Models\Tournament;
use App\Models\TournamentTemplate;
use App\Models\User;
use App\Services\Credits\CreditService;
use App\Services\PermissionService;
use App\Services\Poster\Presets\PresetThemes;
use App\Services\Poster\TemplatePresetService;
use App\Services\RolesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The ready-made design library: a design for every poster type in every
 * theme, applied as an ordinary template that owns its background.
 */
class PresetLibraryTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Tournament $tournament;
    private TemplatePresetService $presets;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        app(PermissionService::class)->createPermissions();
        app(RolesService::class)->createPredefinedRoles();
        $this->presets = app(TemplatePresetService::class);

        $this->org = Organization::create(['name' => 'Club', 'billing_mode' => 'credits']);
        $this->tournament = Tournament::create([
            'name' => 'Cup', 'slug' => 'cup-' . uniqid(), 'start_date' => now()->addMonth(),
            'organization_id' => $this->org->id, 'type' => 'open',
        ]);
    }

    private function organizer(): User
    {
        $u = User::factory()->create(['organization_id' => $this->org->id])->assignRole('Organizer');
        $this->tournament->organizers()->attach($u->id);

        return $u;
    }

    #[Test]
    public function every_poster_type_has_at_least_two_designs_and_every_theme_ships_its_backgrounds(): void
    {
        foreach (TournamentTemplate::TYPES as $type => $label) {
            $type = is_string($type) ? $type : $label;
            $this->assertGreaterThanOrEqual(2, count($this->presets->available($type)), "{$type} needs at least two designs");
        }

        foreach (array_keys(PresetThemes::all()) as $theme) {
            foreach (array_keys(PresetThemes::ASPECTS) as $aspect) {
                $this->assertNotNull(PresetThemes::sourcePath($theme, $aspect), "Missing background {$theme}-{$aspect}");
            }
        }
    }

    #[Test]
    public function every_themed_design_renders_in_a_dark_and_the_light_theme(): void
    {
        $renderer = app(\App\Services\Poster\TemplateRenderService::class);

        foreach (['crimson', 'classic'] as $theme) {
            foreach ($this->presets->available(null, $theme) as $p) {
                if (! $p['themed']) {
                    continue;
                }
                $tpl = $this->presets->apply($this->tournament, $p['key'], $theme);
                $path = $renderer->renderTemplate($tpl, $renderer->getSampleData($p['type'], []), true);
                Storage::disk('public')->assertExists($path);
                [$w, $h] = getimagesize(Storage::disk('public')->path($path));
                $this->assertSame([$p['canvas_width'], $p['canvas_height']], [$w, $h], "{$p['key']} in {$theme}");
            }
        }
    }

    #[Test]
    public function applying_gives_the_template_its_own_background_and_the_themes_colours(): void
    {
        $a = $this->presets->apply($this->tournament, 'match_versus', 'midnight');
        $b = $this->presets->apply($this->tournament, 'match_versus', 'sunset');

        $this->assertNotSame($a->background_image, $b->background_image);
        Storage::disk('public')->assertExists($a->background_image);
        Storage::disk('public')->assertExists($b->background_image);
        $this->assertSame(['match_versus', 'midnight'], [$a->preset_key, $a->preset_theme]);
        $this->assertNotEmpty($a->preset_version);
        $this->assertFalse($a->is_default, 'Applying never steals the default');

        $vsColour = fn (TournamentTemplate $t) => collect($t->layout_json)->firstWhere('layerName', 'VS')['color'];
        $this->assertSame(PresetThemes::find('midnight')['accent'], $vsColour($a));
        $this->assertSame(PresetThemes::find('sunset')['accent'], $vsColour($b));
        $this->assertStringContainsString('Sunset Fire', $b->name);

        // Deleting one template's background can never blank the other.
        Storage::disk('public')->delete($a->background_image);
        Storage::disk('public')->assertExists($b->background_image);
    }

    #[Test]
    public function an_organizer_browses_and_applies_designs_from_the_generate_page(): void
    {
        $organizer = $this->organizer();

        $list = $this->actingAs($organizer)->getJson(route('admin.tournaments.templates.presets', [$this->tournament, 'type' => 'flyer', 'theme' => 'royal']))
            ->assertOk()->json();
        $this->assertCount(6, $list['themes']);
        $this->assertSame(['flyer_bold', 'flyer_classic'], collect($list['presets'])->pluck('key')->sort()->values()->all());
        $this->assertStringContainsString('/presets/thumb/flyer_bold/royal', $list['presets'][0]['thumb'] . $list['presets'][1]['thumb']);

        $this->actingAs($organizer)->post(route('admin.tournaments.templates.apply-preset', $this->tournament), ['preset' => 'flyer_bold', 'theme' => 'royal'])
            ->assertRedirect();
        $this->assertSame('royal', TournamentTemplate::where('preset_key', 'flyer_bold')->value('preset_theme'));

        $this->actingAs($organizer)->get(route('admin.tournaments.templates.generate', $this->tournament))->assertOk()
            ->assertSee('id="presetGrid"', false);
    }

    #[Test]
    public function thumbnails_render_once_and_are_never_charged(): void
    {
        CreditFeature::where('key', 'poster')->update(['cost' => 5]);
        $organizer = $this->organizer();

        $this->actingAs($organizer)->get(route('admin.presets.thumb', ['key' => 'champions_trophy', 'theme' => 'stadium']))
            ->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertCount(1, Storage::disk('public')->files('preset-thumbs'));
        $this->actingAs($organizer)->get(route('admin.presets.thumb', ['key' => 'champions_trophy', 'theme' => 'stadium']))->assertOk();
        $this->assertCount(1, Storage::disk('public')->files('preset-thumbs'), 'Cached, not re-rendered');

        $this->assertSame(0, \App\Models\Credits\CreditLedgerEntry::count());
        $this->actingAs($organizer)->get(route('admin.presets.thumb', ['key' => 'nope', 'theme' => 'stadium']))->assertNotFound();
    }

    #[Test]
    public function a_premium_design_is_charged_once_per_tournament(): void
    {
        TemplatePreset::create(['key' => 'award_hero', 'is_active' => true, 'is_premium' => true, 'credit_cost' => 4]);
        $credits = app(CreditService::class);
        $organizer = $this->organizer();
        $this->actingAs($organizer);

        $this->post(route('admin.tournaments.templates.apply-preset', $this->tournament), ['preset' => 'award_hero'])
            ->assertSessionHas('error');
        $this->assertSame(0, TournamentTemplate::count(), 'Nothing added when it cannot be paid for');

        $credits->grant($this->org, 10, 'promo', 'test');
        $this->post(route('admin.tournaments.templates.apply-preset', $this->tournament), ['preset' => 'award_hero'])->assertRedirect();
        $this->post(route('admin.tournaments.templates.apply-preset', $this->tournament), ['preset' => 'award_hero', 'theme' => 'classic'])->assertRedirect();

        $this->assertSame(6, $credits->balance($this->org->fresh()), 'Once for this tournament');
        $this->assertSame(2, TournamentTemplate::where('preset_key', 'award_hero')->count());
    }

    #[Test]
    public function a_switched_off_design_is_neither_listed_nor_applicable(): void
    {
        TemplatePreset::create(['key' => 'flyer_classic', 'is_active' => false]);
        $organizer = $this->organizer();

        $keys = collect($this->actingAs($organizer)->getJson(route('admin.tournaments.templates.presets', [$this->tournament, 'type' => 'flyer']))->json('presets'))->pluck('key');
        $this->assertSame(['flyer_bold'], $keys->all());

        $this->post(route('admin.tournaments.templates.apply-preset', $this->tournament), ['preset' => 'flyer_classic'])
            ->assertSessionHasErrors('preset');
    }

    #[Test]
    public function a_new_tournament_can_start_with_a_full_set_of_default_designs(): void
    {
        $admin = User::factory()->create(['organization_id' => $this->org->id])->assignRole('Admin');
        $this->actingAs($admin)->post(route('admin.tournaments.store'), [
            'organization_id' => $this->org->id, 'name' => 'Kit Cup',
            'start_date' => now()->addDay()->toDateString(), 'end_date' => now()->addDays(9)->toDateString(),
            'starter_kit' => 1, 'starter_theme' => 'stadium',
        ])->assertRedirect();

        $kit = Tournament::where('name', 'Kit Cup')->firstOrFail()->templates()->get();
        $themedTypes = collect($this->presets->available())->where('themed', true)->pluck('type')->unique();

        $this->assertSame($themedTypes->count(), $kit->count(), 'One per type');
        $this->assertTrue($kit->every(fn ($t) => $t->is_default && $t->preset_theme === 'stadium' && $t->background_image));
        $this->assertNotNull(Tournament::where('name', 'Kit Cup')->first()->getTemplate(TournamentTemplate::TYPE_WELCOME_CARD));
    }

    #[Test]
    public function only_super_admin_manages_the_designs(): void
    {
        $this->actingAs($this->organizer())->get(route('admin.template-presets.index'))->assertForbidden();

        $super = User::factory()->create()->assignRole('Superadmin');
        $this->actingAs($super)->get(route('admin.template-presets.index'))->assertOk()->assertSee('Spotlight');
        $this->actingAs($super)->post(route('admin.template-presets.update', 'welcome_split'), ['is_active' => 1, 'credit_cost' => 3])->assertRedirect();

        $p = $this->presets->find('welcome_split');
        $this->assertTrue($p['is_premium']);
        $this->assertSame(3, $p['credit_cost']);

        // A price of 0 makes it free again: there is no separate premium switch.
        $this->actingAs($super)->post(route('admin.template-presets.update', 'welcome_split'), ['is_active' => 1, 'credit_cost' => 0])->assertRedirect();
        $p = $this->presets->find('welcome_split');
        $this->assertFalse($p['is_premium']);
        $this->assertSame(0, $p['credit_cost']);
        $this->actingAs($super)->get(route('admin.template-presets.index'))->assertDontSee('>Premium<', false);
    }

    #[Test]
    public function the_led_wall_can_wear_a_design_theme(): void
    {
        $display = \App\Models\LedDisplay::create([
            'tournament_id' => $this->tournament->id, 'name' => 'Wall', 'token' => \App\Models\LedDisplay::newToken(),
            'settings' => ['theme' => 'royal'] + \App\Models\LedDisplay::DEFAULT_SETTINGS,
        ]);

        $vars = $this->getJson(route('public.led.state', $display->token))->assertOk()->json('display.theme_vars');
        $this->assertSame(PresetThemes::find('royal')['accent'], $vars['accent']);
        $this->assertStringContainsString('preset-backgrounds/royal-wide', $vars['image']);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use App\Models\Organization;
use App\Models\TemplatePreset;
use App\Models\Tournament;
use App\Models\TournamentTemplate;
use App\Models\User;
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

<?php

declare(strict_types=1);

namespace Tests\Feature\Matches;

use App\Models\LedDisplay;
use App\Models\LedPreset;
use App\Models\Matches;
use App\Services\LedWall\TickerDesign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesScoredMatch;
use Tests\TestCase;

/**
 * The live-video ticker's design: set per match from the LED console, applied to
 * a whole tournament, saved as templates, and served to the ticker page.
 */
class TickerDesignTest extends TestCase
{
    use CreatesScoredMatch;
    use RefreshDatabase;

    private LedDisplay $display;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpScoredMatch();
        Cache::flush();
        $this->actingAs($this->scorer)->get(route('admin.matches.led', $this->match))->assertOk();
        $this->display = LedDisplay::where('match_id', $this->match->id)->firstOrFail();
    }

    private function design(array $changes = []): array
    {
        return array_merge(TickerDesign::DEFAULTS, $changes);
    }

    private function served(?Matches $match = null): array
    {
        return $this->getJson(route('public.live-state', $match ?? $this->match))->assertOk()->json('design');
    }

    private function newMatch(): Matches
    {
        return Matches::create([
            'tournament_id' => $this->tournament->id, 'name' => 'Semi', 'slug' => 'semi-' . uniqid(),
            'team_a_id' => $this->lions->id, 'team_b_id' => $this->tigers->id, 'status' => 'upcoming', 'overs' => 1,
        ]);
    }

    #[Test]
    public function the_ticker_starts_in_the_standard_look_and_the_console_changes_it_live(): void
    {
        $this->assertSame(TickerDesign::DEFAULTS, $this->served());

        $this->actingAs($this->scorer)->get(route('admin.matches.led', $this->match))->assertOk()
            ->assertSee('Ticker design')->assertSee(route('public.live-ticker', $this->match) . '?preview=1', false);

        $mine = $this->design(['position' => 'top', 'align' => 'center', 'width' => 1200, 'scale' => 80, 'bg' => '#003300', 'show_players' => false, 'badge' => 'left']);
        $this->actingAs($this->scorer)->postJson(route('admin.led.ticker', $this->display), $mine)->assertOk()->assertJson(['others' => 0]);

        $this->assertSame($mine, $this->served());
        $this->get(route('public.live-ticker', $this->match))->assertOk()
            ->assertSee('"position":"top"', false)->assertSee('id="ticker-wrap"', false)->assertSee('id="ticker-players"', false);

        // The wall's own settings are untouched.
        $this->assertSame(LedDisplay::DEFAULT_SETTINGS['rotation_seconds'], $this->display->fresh()->setting('rotation_seconds'));
    }

    #[Test]
    public function a_design_is_validated_and_only_operators_change_it(): void
    {
        $this->actingAs($this->scorer)->postJson(route('admin.led.ticker', $this->display), $this->design(['bg' => 'red']))
            ->assertStatus(422)->assertJsonValidationErrors('bg');
        $this->actingAs($this->scorer)->postJson(route('admin.led.ticker', $this->display), $this->design(['scale' => 400, 'position' => 'middle']))
            ->assertStatus(422)->assertJsonValidationErrors(['scale', 'position']);

        // A team manager is turned away (redirected out of admin) and nothing changes.
        $status = $this->actingAs(\App\Models\User::factory()->create()->assignRole('Team Manager'))
            ->postJson(route('admin.led.ticker', $this->display), $this->design(['bg' => '#ff0000']))->status();
        $this->assertContains($status, [302, 403]);
        $this->assertSame(TickerDesign::DEFAULTS['bg'], $this->served()['bg']);
    }

    #[Test]
    public function one_design_can_go_on_every_match_in_the_tournament_and_new_matches_inherit_it(): void
    {
        $other = $this->newMatch();
        $this->actingAs($this->scorer)->get(route('admin.matches.led', $other))->assertOk();

        $mine = $this->design(['accent_from' => '#00ff00', 'scale' => 120]);
        $this->actingAs($this->scorer)->postJson(route('admin.led.ticker', $this->display), $mine + ['apply_to_tournament' => true])
            ->assertOk()->assertJson(['others' => 1]);
        $this->assertSame($mine, $this->served($other));

        // A match whose console has never been opened starts from the tournament's last setup.
        $third = $this->newMatch();
        $this->actingAs($this->scorer)->get(route('admin.matches.led', $third))->assertOk();
        $this->assertSame($mine, $this->served($third));
    }

    #[Test]
    public function templates_save_a_design_and_load_it_onto_another_match_without_touching_the_wall(): void
    {
        $mine = $this->design(['panel_from' => '#123456', 'show_moments' => false]);
        $this->actingAs($this->scorer)->postJson(route('admin.led.ticker', $this->display), $mine)->assertOk();
        $this->actingAs($this->scorer)->postJson(route('admin.led.presets.store', $this->display), ['name' => 'Night', 'kind' => 'ticker', 'is_default' => true])
            ->assertOk()->assertJson(['preset' => ['kind' => 'ticker', 'name' => 'Night']]);

        $template = LedPreset::ticker()->where('name', 'Night')->firstOrFail();
        $this->assertSame($mine, TickerDesign::normalize($template->settings));
        $this->assertSame(0, LedPreset::wall()->count(), 'A ticker template is not a wall preset');

        // A wall preset of the same name is separate, and loading it leaves the ticker alone.
        $this->actingAs($this->scorer)->postJson(route('admin.led.presets.store', $this->display), ['name' => 'Night'])->assertOk();
        $this->assertSame(1, LedPreset::wall()->count());
        $this->assertArrayNotHasKey('ticker', LedPreset::wall()->first()->settings);

        $other = $this->newMatch();
        $this->actingAs($this->scorer)->get(route('admin.matches.led', $other))->assertOk();
        $otherDisplay = LedDisplay::where('match_id', $other->id)->firstOrFail();
        $otherDisplay->update(['settings' => array_merge($otherDisplay->settings, ['rotation_seconds' => 45, 'ticker' => TickerDesign::DEFAULTS])]);

        $this->actingAs($this->scorer)->postJson(route('admin.led.presets.apply', [$otherDisplay, $template]))->assertOk()->assertJson(['design' => $mine]);
        $this->assertSame($mine, $this->served($other));
        $this->assertSame(45, $otherDisplay->fresh()->setting('rotation_seconds'), 'The wall keeps its own settings');

        $wallPreset = LedPreset::wall()->firstOrFail();
        $this->actingAs($this->scorer)->postJson(route('admin.led.presets.apply', [$otherDisplay, $wallPreset]))->assertOk();
        $this->assertSame($mine, $this->served($other), 'A wall preset leaves the ticker as it was');
    }

    #[Test]
    public function the_default_template_dresses_a_match_with_no_console_yet(): void
    {
        LedPreset::create(['organization_id' => $this->tournament->organization_id, 'kind' => 'ticker', 'name' => 'House',
            'settings' => $this->design(['text' => '#eeeeee']), 'is_default' => true]);

        $this->assertSame('#eeeeee', $this->served($this->newMatch())['text']);
    }

    #[Test]
    public function only_super_admin_saves_templates_for_everyone(): void
    {
        $this->actingAs($this->scorer)->postJson(route('admin.led.presets.store', $this->display), ['name' => 'All', 'kind' => 'ticker', 'platform' => true])
            ->assertForbidden();
        $this->actingAs($this->scorer)->postJson(route('admin.led.presets.store', $this->display), ['name' => 'X', 'kind' => 'banner'])
            ->assertStatus(422);
    }
}

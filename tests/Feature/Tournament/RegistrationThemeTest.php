<?php

namespace Tests\Feature\Tournament;

use App\Models\Organization;
use App\Models\Tournament;
use App\Models\TournamentSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RegistrationThemeTest extends TestCase
{
    use RefreshDatabase;

    private function settings(array $attrs): TournamentSetting
    {
        $org = Organization::create(['name' => 'Org']);
        $t = Tournament::create(['name' => 'Cup', 'slug' => 'cup-x', 'start_date' => '2026-01-01', 'organization_id' => $org->id]);

        return TournamentSetting::create(array_merge(['tournament_id' => $t->id], $attrs));
    }

    #[Test]
    public function theme_defaults_derive_from_tournament_colors(): void
    {
        $theme = $this->settings([
            'accent_color' => '#ff0000',
            'primary_color' => '#111111',
            'secondary_color' => '#222222',
        ])->registrationTheme();

        $this->assertSame('#ff0000', $theme['icon_color']);          // default = accent
        $this->assertSame('#ff0000', $theme['button_gradient_from']); // default = accent
        $this->assertSame('#111111', $theme['page_bg_from']);        // default = primary
        $this->assertSame('#222222', $theme['page_bg_to']);          // default = secondary
        $this->assertNull($theme['banner_image']);
    }

    #[Test]
    public function saved_theme_values_override_defaults(): void
    {
        $theme = $this->settings([
            'accent_color' => '#ff0000',
            'registration_theme' => [
                'icon_color' => '#00ff00',
                'banner_title' => 'Join the League',
                'label_color' => '',           // empty → ignored, falls back to default
            ],
        ])->registrationTheme();

        $this->assertSame('#00ff00', $theme['icon_color']);        // overridden
        $this->assertSame('Join the League', $theme['banner_title']);
        $this->assertSame('#cbd5e1', $theme['label_color']);       // empty saved → default kept
    }
}

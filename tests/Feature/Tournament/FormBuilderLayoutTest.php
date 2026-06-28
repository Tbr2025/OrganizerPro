<?php

namespace Tests\Feature\Tournament;

use App\Helpers\PlayerFormConfig;
use App\Models\Organization;
use App\Models\Tournament;
use App\Models\TournamentSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FormBuilderLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function settings(array $formFields): TournamentSetting
    {
        $org = Organization::create(['name' => 'Org']);
        $t = Tournament::create(['name' => 'Cup', 'slug' => 'cup-x', 'start_date' => '2026-01-01', 'organization_id' => $org->id]);

        return TournamentSetting::create(['tournament_id' => $t->id, 'registration_form_fields' => $formFields]);
    }

    #[Test]
    public function default_layout_uses_field_group_order(): void
    {
        $layout = PlayerFormConfig::getFormLayout(null, false);

        $this->assertSame('Basic Information', $layout[0]['key']);
        // first_name precedes last_name precedes email in Basic Information.
        $basic = $layout[0]['fields'];
        $this->assertLessThan(array_search('last_name', $basic), array_search('first_name', $basic));
        $this->assertLessThan(array_search('email', $basic), array_search('last_name', $basic));
    }

    #[Test]
    public function saved_field_order_is_honored_within_a_section(): void
    {
        // Push date_of_birth before first_name within Basic Information.
        $settings = $this->settings([
            'date_of_birth' => ['visible' => true, 'required' => true, 'order' => -5],
        ]);

        $basic = collect(PlayerFormConfig::getFormLayout($settings->fresh(), false))
            ->firstWhere('key', 'Basic Information')['fields'];

        $this->assertSame('date_of_birth', $basic[0]);
    }

    #[Test]
    public function saved_section_order_reorders_sections(): void
    {
        $settings = $this->settings([
            '_section_order' => ['Availability', 'Basic Information'],
        ]);

        $keys = array_column(PlayerFormConfig::getFormLayout($settings->fresh(), false), 'key');

        // Availability moved to the front; sections not listed still appended.
        $this->assertSame('Availability', $keys[0]);
        $this->assertContains('Player Profile', $keys);
    }

    #[Test]
    public function visible_only_excludes_hidden_fields(): void
    {
        $settings = $this->settings([
            'date_of_birth' => ['visible' => false, 'required' => false],
        ]);

        $basic = collect(PlayerFormConfig::getFormLayout($settings->fresh(), true))
            ->firstWhere('key', 'Basic Information')['fields'];

        $this->assertNotContains('date_of_birth', $basic);
        $this->assertContains('first_name', $basic); // forced-visible stays
    }
}

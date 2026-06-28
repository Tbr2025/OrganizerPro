<?php

namespace Tests\Feature\Tournament;

use App\Helpers\PlayerFormConfig;
use App\Models\Organization;
use App\Models\Tournament;
use App\Models\TournamentSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EditableFieldLabelsTest extends TestCase
{
    use RefreshDatabase;

    private function settings(array $formFields): TournamentSetting
    {
        $org = Organization::create(['name' => 'Org']);
        $t = Tournament::create(['name' => 'Cup', 'slug' => 'cup-x', 'start_date' => '2026-01-01', 'organization_id' => $org->id]);

        return TournamentSetting::create([
            'tournament_id' => $t->id,
            'registration_form_fields' => $formFields,
        ]);
    }

    #[Test]
    public function custom_field_labels_override_defaults_and_fall_back_when_unset(): void
    {
        $settings = $this->settings([
            'first_name' => ['visible' => true, 'required' => true, 'label' => 'Given Name'],
            'date_of_birth' => ['visible' => false, 'required' => false, 'label' => 'DOB'],
        ]);

        $cfg = PlayerFormConfig::getFieldConfig($settings->fresh());

        // Custom labels applied.
        $this->assertSame('Given Name', $cfg['first_name']['label']);
        $this->assertSame('DOB', $cfg['date_of_birth']['label']);
        // Visibility override honored.
        $this->assertFalse($cfg['date_of_birth']['visible']);
        // Fields without a custom label fall back to the default label.
        $this->assertSame('Nationality', $cfg['country']['label']);
    }

    #[Test]
    public function custom_section_titles_override_defaults(): void
    {
        $settings = $this->settings([
            '_sections' => ['Basic Information' => 'Personal Details'],
        ]);

        $sections = PlayerFormConfig::getSectionLabels($settings->fresh());

        $this->assertSame('Personal Details', $sections['Basic Information']);   // overridden
        $this->assertSame('Availability', $sections['Availability']);            // default
    }

    #[Test]
    public function first_and_last_name_stay_forced_visible_and_required(): void
    {
        // Even if someone saved them off, getFieldConfig forces them on.
        $settings = $this->settings([
            'first_name' => ['visible' => false, 'required' => false, 'label' => 'Given Name'],
        ]);

        $cfg = PlayerFormConfig::getFieldConfig($settings->fresh());

        $this->assertTrue($cfg['first_name']['visible']);
        $this->assertTrue($cfg['first_name']['required']);
        $this->assertSame('Given Name', $cfg['first_name']['label']); // custom label still respected
    }
}

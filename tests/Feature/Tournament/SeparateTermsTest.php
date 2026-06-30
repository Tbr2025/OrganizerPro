<?php

namespace Tests\Feature\Tournament;

use App\Helpers\PlayerFormConfig;
use App\Helpers\TeamFormConfig;
use App\Models\Organization;
use App\Models\Tournament;
use App\Models\TournamentSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SeparateTermsTest extends TestCase
{
    use RefreshDatabase;

    private function settings(array $attrs): TournamentSetting
    {
        $slug = 'c-' . uniqid();
        $org = Organization::create(['name' => 'Org ' . $slug]);
        $t = Tournament::create(['name' => 'C', 'slug' => $slug, 'start_date' => '2026-01-01', 'organization_id' => $org->id]);

        return $t->settings()->create($attrs);
    }

    #[Test]
    public function player_and_team_terms_are_independent(): void
    {
        // Only player T&C set → player form shows T&C, team form does not.
        $s = $this->settings(['terms_and_conditions_content' => 'PLAYER TERMS']);
        $this->assertTrue(PlayerFormConfig::getFieldConfig($s)['terms_and_conditions']['visible']);
        $this->assertFalse(TeamFormConfig::getFieldConfig($s)['terms_and_conditions']['visible']);

        // Only team T&C set → team form shows T&C, player form does not.
        $s2 = $this->settings(['team_terms_and_conditions_content' => 'TEAM TERMS']);
        $this->assertTrue(TeamFormConfig::getFieldConfig($s2)['terms_and_conditions']['visible']);
        $this->assertFalse(PlayerFormConfig::getFieldConfig($s2)['terms_and_conditions']['visible']);
    }
}

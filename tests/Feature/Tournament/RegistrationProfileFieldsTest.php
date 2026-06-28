<?php

namespace Tests\Feature\Tournament;

use App\Models\Organization;
use App\Models\Player;
use App\Models\Tournament;
use App\Services\Tournament\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RegistrationProfileFieldsTest extends TestCase
{
    use RefreshDatabase;

    private function tournament(): Tournament
    {
        $org = Organization::create(['name' => 'Org']);

        return Tournament::create([
            'name' => 'Cup', 'slug' => 'cup-x', 'start_date' => '2026-01-01', 'organization_id' => $org->id,
        ]);
    }

    #[Test]
    public function it_composes_name_from_first_and_last_and_stores_profile_fields(): void
    {
        Mail::fake();
        $tournament = $this->tournament();

        app(RegistrationService::class)->registerPlayer($tournament, [
            'name' => 'Asif Khan',          // composed by the controller; service receives it
            'first_name' => 'Asif',
            'last_name' => 'Khan',
            'email' => 'asif@x.test',
            'date_of_birth' => '1995-05-20',
            'state' => 'Kerala',
            'visa_status' => 'work_visa',
            'employer_name' => 'Acme LLC',
            'employer_address' => 'Dubai',
            'employer_position' => 'Engineer',
            'available_weekends' => true,
            'played_ys_ipl_s1' => true,
        ]);

        $player = Player::where('email', 'asif@x.test')->first();

        $this->assertNotNull($player);
        $this->assertSame('Asif Khan', $player->name);
        $this->assertSame('Asif', $player->first_name);
        $this->assertSame('Khan', $player->last_name);
        $this->assertSame('Kerala', $player->state);
        $this->assertSame('work_visa', $player->visa_status);
        $this->assertSame('Acme LLC', $player->employer_name);
        $this->assertSame('Engineer', $player->employer_position);
        $this->assertTrue((bool) $player->available_weekends);
        $this->assertTrue((bool) $player->played_ys_ipl_s1);
        $this->assertSame('1995-05-20', $player->date_of_birth->format('Y-m-d'));
    }

    #[Test]
    public function the_new_fields_are_configurable_in_player_form_config(): void
    {
        $defaults = \App\Helpers\PlayerFormConfig::defaultFormFields();

        foreach (['first_name', 'last_name', 'date_of_birth', 'visa_status', 'employer_name', 'available_weekends', 'played_ys_ipl_s1'] as $key) {
            $this->assertArrayHasKey($key, $defaults, "Field [{$key}] missing from PlayerFormConfig defaults.");
        }

        // first/last are forced on by getFieldConfig regardless of saved settings.
        $cfg = \App\Helpers\PlayerFormConfig::getFieldConfig(null);
        $this->assertTrue($cfg['first_name']['required']);
        $this->assertTrue($cfg['last_name']['required']);
    }
}

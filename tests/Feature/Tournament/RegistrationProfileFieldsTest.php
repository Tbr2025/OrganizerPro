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
            'available_saturday' => true,
            'available_sunday' => false,
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
        $this->assertTrue((bool) $player->available_saturday);
        $this->assertFalse((bool) $player->available_sunday);
        $this->assertTrue((bool) $player->available_weekends); // back-compat: sat || sun
        $this->assertTrue((bool) $player->played_ys_ipl_s1);
        $this->assertSame('1995-05-20', $player->date_of_birth->format('Y-m-d'));
    }

    #[Test]
    public function the_new_fields_are_configurable_in_player_form_config(): void
    {
        $defaults = \App\Helpers\PlayerFormConfig::defaultFormFields();

        foreach (['first_name', 'last_name', 'date_of_birth', 'visa_status', 'employer_name', 'available_saturday', 'available_sunday', 'played_ys_ipl_s1'] as $key) {
            $this->assertArrayHasKey($key, $defaults, "Field [{$key}] missing from PlayerFormConfig defaults.");
        }

        // first/last are forced on by getFieldConfig regardless of saved settings.
        $cfg = \App\Helpers\PlayerFormConfig::getFieldConfig(null);
        $this->assertTrue($cfg['first_name']['required']);
        $this->assertTrue($cfg['last_name']['required']);
    }

    #[Test]
    public function employer_fields_are_required_only_for_a_work_visa(): void
    {
        $fieldConfig = \App\Helpers\PlayerFormConfig::getFieldConfig(null);
        $rules = \App\Helpers\PlayerFormConfig::buildValidationRules($fieldConfig, 'public');

        // The rule is conditional on visa_status === work_visa.
        $this->assertStringContainsString('required_if:visa_status,work_visa', $rules['employer_name']);

        $validator = \Illuminate\Support\Facades\Validator::make(
            ['visa_status' => 'work_visa', 'employer_name' => '', 'employer_address' => '', 'employer_position' => ''],
            ['employer_name' => $rules['employer_name'], 'employer_address' => $rules['employer_address'], 'employer_position' => $rules['employer_position']]
        );
        $this->assertTrue($validator->fails(), 'Employer fields must be required for a work visa.');

        // Visit visa → employer fields can be blank.
        $ok = \Illuminate\Support\Facades\Validator::make(
            ['visa_status' => 'visit_visa', 'employer_name' => '', 'employer_address' => '', 'employer_position' => ''],
            ['employer_name' => $rules['employer_name'], 'employer_address' => $rules['employer_address'], 'employer_position' => $rules['employer_position']]
        );
        $this->assertFalse($ok->fails(), 'Employer fields must be optional for a non-work visa.');
    }

    #[Test]
    public function visa_validity_is_required_only_for_a_visit_visa(): void
    {
        $fieldConfig = \App\Helpers\PlayerFormConfig::getFieldConfig(null);
        $rules = \App\Helpers\PlayerFormConfig::buildValidationRules($fieldConfig, 'public');

        $this->assertStringContainsString('required_if:visa_status,visit_visa', $rules['visa_expiry']);

        // Visit visa with no expiry → fails.
        $fail = \Illuminate\Support\Facades\Validator::make(
            ['visa_status' => 'visit_visa', 'visa_expiry' => ''],
            ['visa_expiry' => $rules['visa_expiry']]
        );
        $this->assertTrue($fail->fails());

        // Work visa with no expiry → ok.
        $ok = \Illuminate\Support\Facades\Validator::make(
            ['visa_status' => 'work_visa', 'visa_expiry' => ''],
            ['visa_expiry' => $rules['visa_expiry']]
        );
        $this->assertFalse($ok->fails());
    }
}

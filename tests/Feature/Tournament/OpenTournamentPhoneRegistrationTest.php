<?php

declare(strict_types=1);

namespace Tests\Feature\Tournament;

use App\Helpers\PlayerFormConfig;
use App\Models\Organization;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\TournamentSetting;
use App\Models\User;
use App\Services\Tournament\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Open tournaments register players by phone: mobile required, email optional.
 * Auction tournaments keep email required.
 */
class OpenTournamentPhoneRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The public form verifies a Cloudflare Turnstile token when keys are set.
        config(['turnstile.secret_key' => null]);
    }

    private function tournament(string $type): Tournament
    {
        $org = Organization::create(['name' => 'Org']);
        $t = Tournament::create([
            'name' => 'Cup', 'slug' => 'cup-' . uniqid(), 'start_date' => now()->addMonth(),
            'organization_id' => $org->id, 'type' => $type,
        ]);

        // Only the locked identity/contact fields on the form, so a test post
        // need not fill thirty profile fields.
        $fields = [];
        foreach (array_keys(PlayerFormConfig::defaultFormFields()) as $key) {
            $fields[$key] = ['visible' => false, 'required' => false];
        }
        TournamentSetting::create([
            'tournament_id' => $t->id,
            'registration_form_fields' => $fields,
            'player_registration_status' => 'open',
        ]);

        return $t->fresh('settings');
    }

    private function register(Tournament $t, array $overrides = [])
    {
        return $this->post(route('public.tournament.registration.player.store', $t->slug), array_merge([
            'first_name' => 'Asif',
            'last_name' => 'Khan',
            'email' => '',
            'mobile_country_code' => '+91',
            'mobile_national_number' => '98765 43210',
        ], $overrides));
    }

    #[Test]
    public function open_tournament_makes_email_optional_and_phone_required(): void
    {
        $cfg = PlayerFormConfig::getFieldConfig($this->tournament('open')->settings);

        $this->assertFalse($cfg['email']['required']);
        $this->assertTrue($cfg['email']['visible']);
        $this->assertTrue($cfg['mobile_number']['required']);

        $rules = PlayerFormConfig::buildValidationRules($cfg);
        $this->assertStringStartsWith('nullable', $rules['email']);
        $this->assertStringStartsWith('required', $rules['mobile_national_number']);
    }

    #[Test]
    public function auction_tournament_keeps_email_required(): void
    {
        $cfg = PlayerFormConfig::getFieldConfig($this->tournament('auction')->settings);

        $this->assertTrue($cfg['email']['required']);
        $this->assertStringStartsWith('required', PlayerFormConfig::buildValidationRules($cfg)['email']);
    }

    #[Test]
    public function a_player_registers_on_an_open_tournament_without_an_email(): void
    {
        Mail::fake();
        $t = $this->tournament('open');

        $this->register($t)->assertRedirect(route('public.tournament.registration.player.success', $t->slug))
            ->assertSessionHas('registration_reference')
            ->assertSessionHas('registration_emailed', false);

        $player = Player::where('mobile_number_full', '919876543210')->firstOrFail();
        $this->assertNull($player->email);
        $this->assertNull(User::find($player->user_id)->email);
        $this->assertSame(1, TournamentRegistration::where('tournament_id', $t->id)->count());
    }

    #[Test]
    public function a_blank_phone_is_rejected_and_the_input_is_kept(): void
    {
        $t = $this->tournament('open');

        $this->from(route('public.tournament.registration.player', $t->slug));
        $this->register($t, ['mobile_national_number' => ''])
            ->assertSessionHasErrors('mobile_national_number')
            ->assertSessionHasInput('first_name', 'Asif');

        $this->assertSame(0, TournamentRegistration::count());
    }

    #[Test]
    public function the_same_phone_cannot_register_twice_for_a_tournament(): void
    {
        Mail::fake();
        $t = $this->tournament('open');

        $this->register($t);
        // Typed differently: no space, trunk zero.
        $this->register($t, ['first_name' => 'Other', 'mobile_national_number' => '09876543210'])
            ->assertSessionHas('error');

        $this->assertSame(1, TournamentRegistration::where('tournament_id', $t->id)->count());
    }

    #[Test]
    public function two_players_without_email_are_never_merged(): void
    {
        // where('email', null) matches every email-less player; the second
        // registration must create its own player, not reuse the first.
        Mail::fake();
        $t = $this->tournament('open');
        $service = app(RegistrationService::class);

        $service->registerPlayer($t, ['name' => 'A One', 'email' => null, 'mobile_number_full' => '911111111111']);
        $service->registerPlayer($t, ['name' => 'B Two', 'email' => '', 'mobile_number_full' => '912222222222']);

        $this->assertSame(2, Player::whereNull('email')->count());
        $this->assertSame(2, TournamentRegistration::where('tournament_id', $t->id)->count());
        $this->assertSame(['A One', 'B Two'], Player::orderBy('id')->pluck('name')->all());
    }

    #[Test]
    public function the_retired_registration_endpoint_hands_over_to_the_guarded_form(): void
    {
        $t = $this->tournament('open');

        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class)
            ->post("/tournament/{$t->slug}/register/player", ['name' => 'Bot', 'email' => 'bot@x.test'])
            ->assertStatus(303)
            ->assertRedirect(route('public.tournament.registration.player', $t->slug));

        $this->assertSame(0, TournamentRegistration::count());
    }
}

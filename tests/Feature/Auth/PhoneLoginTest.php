<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Organization;
use App\Models\Player;
use App\Models\User;
use App\Services\Auth\PlayerLogin;
use App\Services\PermissionService;
use App\Services\RolesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Players added directly with only a phone: their login id is
 * <digits>@sportzley.com, nothing is ever emailed to it, an admin sets the
 * password, and they sign in with the phone number.
 */
class PhoneLoginTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $admin;
    private ?int $tournamentId = null;

    protected function setUp(): void
    {
        parent::setUp();
        config(['turnstile.secret_key' => null]);
        app(PermissionService::class)->createPermissions();
        app(RolesService::class)->createPredefinedRoles();

        $this->org = Organization::create(['name' => 'Club']);
        $this->admin = User::factory()->create(['organization_id' => $this->org->id])->assignRole('Admin');
    }

    /** A tournament whose form asks only for the identity fields. */
    private function minimalTournament(): \App\Models\Tournament
    {
        $t = \App\Models\Tournament::create(['name' => 'Cup', 'slug' => 'cup-' . uniqid(), 'start_date' => now(), 'organization_id' => $this->org->id, 'type' => 'open']);
        $fields = [];
        foreach (array_keys(\App\Helpers\PlayerFormConfig::defaultFormFields()) as $key) {
            $fields[$key] = ['visible' => false, 'required' => false];
        }
        \App\Models\TournamentSetting::create(['tournament_id' => $t->id, 'registration_form_fields' => $fields]);

        return $t;
    }

    private function addByPhone(array $extra = [])
    {
        $this->tournamentId ??= $this->minimalTournament()->id;

        return $this->actingAs($this->admin)->post(route('admin.players.store'), array_merge([
            'tournament_id' => $this->tournamentId,
            'first_name' => 'Ravi', 'last_name' => 'Kumar',
            'mobile_country_code' => '+91', 'mobile_national_number' => '9876543210',
        ], $extra));
    }

    private function login(string $id, string $password)
    {
        auth()->logout();

        return $this->post(route('admin.login.submit'), ['email' => $id, 'password' => $password]);
    }

    #[Test]
    public function an_admin_adds_a_player_with_only_a_phone_and_gets_a_phone_login_id(): void
    {
        Notification::fake();
        Mail::fake();

        $this->addByPhone()->assertRedirect(route('admin.players.index'));

        $player = Player::withoutGlobalScopes()->where('mobile_number_full', '919876543210')->firstOrFail();
        $this->assertNull($player->email, 'The player record keeps no fake address');
        $user = User::findOrFail($player->user_id);
        $this->assertSame('919876543210@sportzley.com', $user->email);
        $this->assertTrue(PlayerLogin::isPlaceholder($user->email));

        Notification::assertNothingSent();
        Mail::assertNothingOutgoing();
    }

    #[Test]
    public function a_direct_add_needs_a_phone_or_an_email(): void
    {
        $this->addByPhone(['mobile_national_number' => ''])->assertSessionHasErrors(['email', 'mobile_national_number']);
        $this->assertSame(0, Player::withoutGlobalScopes()->count());
    }

    #[Test]
    public function nothing_is_ever_mailed_to_a_placeholder(): void
    {
        $user = User::factory()->create(['email' => '919876543210@sportzley.com']);
        $real = User::factory()->create(['email' => 'real@example.test']);

        $this->assertNull($user->routeNotificationForMail());
        $this->assertSame('real@example.test', $real->routeNotificationForMail());
    }

    #[Test]
    public function the_admin_sets_a_password_and_the_player_signs_in_with_the_phone(): void
    {
        $this->addByPhone();
        $player = Player::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($this->admin)->post(route('admin.players.login-password', $player->id), [
            'password' => 'cricket123', 'password_confirmation' => 'cricket123',
        ])->assertSessionHas('success');

        // With or without the country code, spaces, or the placeholder itself.
        foreach (['9876543210', '+91 98765 43210', '919876543210', '919876543210@sportzley.com'] as $id) {
            $this->login($id, 'cricket123')->assertRedirect();
            $this->assertAuthenticatedAs(User::find($player->fresh()->user_id));
        }

        $this->login('9876543210', 'wrong-pass');
        $this->assertGuest();
    }

    #[Test]
    public function a_player_added_by_a_team_manager_without_an_account_gets_one_when_the_admin_sets_a_password(): void
    {
        $player = Player::withoutGlobalScopes()->create([
            'organization_id' => $this->org->id, 'name' => 'No Account', 'mobile_number_full' => '971501234567', 'status' => 'approved',
        ]);

        $this->actingAs($this->admin)->post(route('admin.players.login-password', $player->id), [
            'password' => 'secret12', 'password_confirmation' => 'secret12',
        ])->assertSessionHas('success');

        $this->assertSame('971501234567@sportzley.com', User::find($player->fresh()->user_id)->email);
        $this->login('0501234567', 'secret12')->assertRedirect();
        $this->assertAuthenticated();
    }

    #[Test]
    public function a_number_shared_by_two_accounts_signs_in_neither(): void
    {
        foreach (['A One', 'B Two'] as $name) {
            $p = Player::withoutGlobalScopes()->create(['organization_id' => $this->org->id, 'name' => $name, 'mobile_number_full' => '919000000001', 'status' => 'approved']);
            app(PlayerLogin::class)->setPassword($p, 'samepass1');
        }
        $this->assertSame(['919000000001@sportzley.com', '919000000001.2@sportzley.com'], User::where('email', 'like', '919000000001%')->orderBy('id')->pluck('email')->all());

        $this->login('9000000001', 'samepass1');
        $this->assertGuest();
    }

    #[Test]
    public function only_admins_set_passwords_and_email_logins_still_work(): void
    {
        $player = Player::withoutGlobalScopes()->create(['organization_id' => $this->org->id, 'name' => 'P', 'mobile_number_full' => '919111111111', 'status' => 'approved']);
        $manager = User::factory()->create(['organization_id' => $this->org->id])->assignRole('Team Manager');

        $this->actingAs($manager)->post(route('admin.players.login-password', $player->id), [
            'password' => 'nope1234', 'password_confirmation' => 'nope1234',
        ])->assertForbidden();
        $this->assertNull($player->fresh()->user_id, 'A team manager cannot create logins');

        $this->login($this->admin->email, 'password')->assertRedirect();
        $this->assertAuthenticatedAs($this->admin);
    }
}

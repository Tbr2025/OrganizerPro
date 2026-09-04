<?php

declare(strict_types=1);

namespace Tests\Feature\Blog;

use App\Models\Setting;
use App\Models\User;
use App\Services\Blog\AiSettings;
use App\Services\Blog\BlogGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Managing the AI credentials from Settings → AI & Blog.
 *
 * The key is stored in the database, not written to .env: php-fpm runs as www-data and .env on
 * the server is owned by ubuntu and not group-writable, so the env-writing path fails silently
 * there. What matters here is that it is encrypted at rest, never rendered back, and never
 * reaches the settings table or the action log in plain text.
 */
class AiSettingsTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Never inherit the developer's own key.
         *
         * These assertions are about which source wins, so a real OPENAI_API_KEY sitting in the
         * local .env silently becomes the answer — and, worse, gets printed into the failure
         * output of any assertion that compares against it.
         */
        config(['services.openai.key' => null]);
    }

    private function superadmin(): User
    {
        $org = $this->makeOrganization('Org ' . uniqid());
        $user = $this->makeSuperadmin($org);

        $role = Role::firstOrCreate(['name' => 'Settings Editor ' . uniqid(), 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'settings.edit', 'guard_name' => 'web']));
        $user->assignRole($role);

        return $user;
    }

    #[Test]
    public function a_saved_key_is_encrypted_and_never_stored_in_plain_text(): void
    {
        $this->actingAs($this->superadmin())
            ->post(route('admin.settings.store'), ['ai_provider' => 'openai', 
                'ai_key_openai' => 'sk-super-secret-value',
                'ai_base_url_openai' => 'https://api.groq.com/openai/v1',
            ])
            ->assertRedirect();

        $settings = app(AiSettings::class);
        $this->assertSame('sk-super-secret-value', $settings->apiKey());

        // The ciphertext is what is on disk, and the plaintext appears in no setting row at all.
        $stored = Setting::where('option_name', 'ai_key_openai')->value('option_value');
        $this->assertNotSame('sk-super-secret-value', $stored);
        $this->assertNotEmpty($stored);

        // The row that exists holds ciphertext; the plaintext appears in no setting row at all,
        // which is what stops it travelling in a database dump.
        foreach (Setting::pluck('option_value') as $value) {
            $this->assertStringNotContainsString('sk-super-secret-value', (string) $value);
        }
    }

    #[Test]
    public function the_key_is_never_rendered_back_to_the_page(): void
    {
        $admin = $this->superadmin();
        $this->actingAs($admin)->post(route('admin.settings.store'), ['ai_provider' => 'openai', 'ai_key_openai' => 'sk-super-secret-value']);

        $html = $this->actingAs($admin)->get(route('admin.settings.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('sk-super-secret-value', $html);
        // Only enough to recognise it: the first three characters, dots, the last four.
        $this->assertStringContainsString('sk-••••••••', $html);
        $this->assertStringContainsString('alue', $html);
    }

    #[Test]
    public function submitting_a_blank_key_keeps_the_saved_one(): void
    {
        $admin = $this->superadmin();
        $this->actingAs($admin)->post(route('admin.settings.store'), ['ai_provider' => 'openai', 'ai_key_openai' => 'sk-first-key']);

        // The field renders empty every time, so an unrelated settings save must not wipe it.
        $this->actingAs($admin)->post(route('admin.settings.store'), ['ai_provider' => 'openai', 'ai_key_openai' => '', 'app_name' => 'Sportzley']);

        $this->assertSame('sk-first-key', app(AiSettings::class)->apiKey());
    }

    #[Test]
    public function the_dashboard_key_wins_over_env(): void
    {
        config(['services.openai.key' => 'sk-from-env']);
        $this->assertSame('sk-from-env', app(AiSettings::class)->apiKey(), 'With nothing saved, .env is used.');

        $this->actingAs($this->superadmin())->post(route('admin.settings.store'), ['ai_provider' => 'openai', 'ai_key_openai' => 'sk-from-dashboard']);

        // Otherwise typing a key in while OPENAI_API_KEY was set would silently change nothing.
        $this->assertSame('sk-from-dashboard', app(AiSettings::class)->apiKey());
        $this->assertSame('dashboard', app(AiSettings::class)->keySource());
    }

    #[Test]
    public function the_base_url_and_model_are_configurable_from_the_page(): void
    {
        $this->actingAs($this->superadmin())->post(route('admin.settings.store'), ['ai_provider' => 'openai', 
            'ai_base_url_openai' => 'https://api.groq.com/openai/v1',
            'ai_model_openai' => 'llama-3.3-70b-versatile',
        ]);

        $this->assertSame('https://api.groq.com/openai/v1', app(AiSettings::class)->baseUrl());
        $this->assertSame('llama-3.3-70b-versatile', app(BlogGenerationService::class)->model());
    }

    #[Test]
    public function the_tab_is_superadmin_only(): void
    {
        $org = $this->makeOrganization('Org ' . uniqid());
        $plain = User::factory()->create(['organization_id' => $org->id]);
        $role = Role::firstOrCreate(['name' => 'Settings Only ' . uniqid(), 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'settings.edit', 'guard_name' => 'web']));
        $plain->assignRole($role);

        $this->actingAs($plain)->get(route('admin.settings.index'))->assertOk()->assertDontSee('AI & Blog');
        $this->actingAs($this->superadmin())->get(route('admin.settings.index'))->assertOk()->assertSee('AI &amp; Blog', false);
    }

    #[Test]
    public function a_non_superadmin_cannot_set_the_key_even_by_posting_the_field(): void
    {
        $org = $this->makeOrganization('Org ' . uniqid());
        $plain = User::factory()->create(['organization_id' => $org->id]);
        $role = Role::firstOrCreate(['name' => 'Settings Only ' . uniqid(), 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'settings.edit', 'guard_name' => 'web']));
        $plain->assignRole($role);

        $this->actingAs($plain)->post(route('admin.settings.store'), ['ai_provider' => 'openai', 'ai_key_openai' => 'sk-sneaky']);

        $this->assertNull(app(AiSettings::class)->apiKey());
        // And it must not have fallen through to the settings table by the generic loop either.
        $this->assertDatabaseMissing('settings', ['option_name' => 'ai_key_openai']);
        foreach (Setting::pluck('option_value') as $value) {
            $this->assertStringNotContainsString('sk-sneaky', (string) $value);
        }
    }
}

<?php

namespace Tests\Feature;

use App\Models\Font;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FontManagerTest extends TestCase
{
    use DatabaseTransactions;

    private function superadmin(): User
    {
        $role = Role::findOrCreate('Superadmin', 'web');
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_non_superadmin_cannot_access_font_manager(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.fonts.index'))
            ->assertForbidden();
    }

    public function test_superadmin_can_access_font_manager(): void
    {
        $this->actingAs($this->superadmin())
            ->get(route('admin.fonts.index'))
            ->assertOk();
    }

    public function test_superadmin_can_upload_custom_font(): void
    {
        $admin = $this->superadmin();
        $file = UploadedFile::fake()->create('brand.ttf', 20, 'font/ttf');

        $this->actingAs($admin)
            ->post(route('admin.fonts.custom'), [
                'family' => 'Brand Test Font',
                'weight' => 700,
                'style'  => 'normal',
                'font_file' => $file,
            ])
            ->assertRedirect();

        $font = Font::where('slug', 'brand-test-font')->first();
        $this->assertNotNull($font);
        $this->assertSame('custom', $font->source);
        $this->assertCount(1, $font->variants);
        $this->assertSame(700, $font->variants[0]['weight']);

        // Cleanup the file written to public/fonts/custom.
        @unlink(public_path('fonts/' . $font->variants[0]['file']));
    }

    public function test_custom_upload_rejects_non_font_file(): void
    {
        $admin = $this->superadmin();
        $file = UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream');

        $this->actingAs($admin)
            ->post(route('admin.fonts.custom'), [
                'family' => 'Bad Font',
                'weight' => 400,
                'style'  => 'normal',
                'font_file' => $file,
            ])
            ->assertSessionHas('error');

        $this->assertNull(Font::where('slug', 'bad-font')->first());
    }

    public function test_superadmin_can_install_google_font(): void
    {
        $admin = $this->superadmin();

        Http::fake([
            'fonts.googleapis.com/*' => Http::response(
                "@font-face { font-family:'Teko'; font-style:normal; font-weight:400; src: url(https://fonts.gstatic.com/teko-400.ttf) format('truetype'); }",
                200
            ),
            'fonts.gstatic.com/*' => Http::response('FAKE-TTF-BYTES', 200),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.fonts.google'), [
                'family'  => 'Teko',
                'weights' => [400],
            ])
            ->assertRedirect();

        $font = Font::where('slug', 'teko')->first();
        $this->assertNotNull($font);
        $this->assertSame('google', $font->source);
        $this->assertCount(1, $font->variants);

        @unlink(public_path('fonts/' . $font->variants[0]['file']));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use App\Models\TournamentTemplate;
use App\Services\Poster\TemplateRenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Image layers started from the create page.
 *
 * The create page had a background upload and nothing else, so a design needing a logo or a
 * cut-out on top could not be begun there at all. The layer it now saves is an ordinary
 * `uploadedImage` element — the same thing the Fabric editor writes — and the trap is the
 * placeholder: the renderer drops any element whose placeholder resolves to nothing, so a
 * layer carrying its file name there would never be drawn on a real poster.
 */
class CreatePageImageLayerTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    #[Test]
    public function an_uploaded_layer_survives_the_store_and_renders(): void
    {
        Storage::fake('public');

        $org = $this->makeOrganization('Org ' . uniqid());
        $tournament = $this->makeTournament($org, 'open');
        $admin = $this->makeSuperadmin($org);

        $upload = $this->actingAs($admin)->post(
            route('admin.tournaments.templates.upload-overlay', $tournament),
            ['overlay_image' => UploadedFile::fake()->image('badge.png', 200, 200)]
        );
        $upload->assertOk();
        $path = $upload->json('path');
        $this->assertNotEmpty($path);
        Storage::disk('public')->assertExists($path);

        $this->actingAs($admin)->post(route('admin.tournaments.templates.store', $tournament), [
            'name' => 'With a badge',
            'type' => TournamentTemplate::TYPE_MATCH_SUMMARY,
            'canvas_width' => 1080,
            'canvas_height' => 1080,
            'layout_json' => json_encode([[
                // What the create page now serialises: no placeholder, a real imagePath.
                'placeholder' => null,
                'type' => 'uploadedImage',
                'imagePath' => $path,
                'x' => 50, 'y' => 50, 'width' => 200, 'height' => 200, 'zIndex' => 1,
                'rotation' => 0, 'opacity' => 100,
            ]]),
        ])->assertRedirect();

        $template = $tournament->templates()->firstWhere('name', 'With a badge');
        $this->assertNotNull($template);
        $this->assertSame($path, $template->layout_json[0]['imagePath']);

        // skipBlanks on — the real generation path, the one that used to drop it.
        $render = new TemplateRenderService();
        $with = $render->renderTemplate($template, [], false, true);

        $template->layout_json = [];
        $template->save();
        $without = $render->renderTemplate($template->fresh(), [], false, true);

        $this->assertNotSame(
            Storage::disk('public')->get($with),
            Storage::disk('public')->get($without),
            'The uploaded layer was not drawn on the generated poster.'
        );
    }

    #[Test]
    public function the_create_page_offers_the_upload(): void
    {
        $org = $this->makeOrganization('Org ' . uniqid());
        $tournament = $this->makeTournament($org, 'open');
        $admin = $this->makeSuperadmin($org);

        $this->actingAs($admin)
            ->get(route('admin.tournaments.templates.create', $tournament) . '?type=match_summary')
            ->assertOk()
            ->assertSee('Image Layers')
            ->assertSee('uploadOverlayImage', false);
    }
}

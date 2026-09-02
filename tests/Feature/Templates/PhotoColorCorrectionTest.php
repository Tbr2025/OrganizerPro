<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use App\Models\TournamentTemplate;
use App\Services\Poster\TemplateRenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Brightness/contrast for player photos, on every poster type.
 *
 * The sliders were hand-placed on four panels — the award player, the featured
 * player, the three match-summary awards and the two captains — so a welcome
 * card, champions poster, flyer or point table had no way to rescue a dark
 * photo at all. They are now driven by the chosen TEMPLATE rather than the
 * poster type: one panel per person image the design actually draws.
 *
 * These cover the two halves that has to get right — which placeholders a
 * template reports, and that an override actually changes the rendered pixels.
 */
class PhotoColorCorrectionTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    /** A template whose layout draws the given placeholders. */
    private function templateDrawing(array $placeholders, string $type = 'welcome_card'): TournamentTemplate
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org, 'open');

        return TournamentTemplate::create([
            'tournament_id' => $tournament->id,
            'name' => 'Test ' . $type,
            'type' => $type,
            'canvas_width' => 1080,
            'canvas_height' => 1080,
            'layout_json' => array_map(fn ($p) => [
                'type' => 'image',
                'placeholder' => $p,
                'x' => 50,
                'y' => 50,
                'width' => 400,
                'height' => 400,
            ], $placeholders),
        ]);
    }

    #[Test]
    public function a_template_reports_only_the_person_images_it_draws(): void
    {
        $template = $this->templateDrawing([
            'player_image',
            'team_logo',          // a crest — wants no colour correction
            'tournament_logo',
            'sponsor_logo',
        ]);

        $this->assertSame(['player_image'], $template->personImagePlaceholders());
    }

    #[Test]
    public function a_design_with_no_player_photo_reports_none(): void
    {
        // A point table draws crests and text only; the panel should not appear.
        $template = $this->templateDrawing(['team_logo', 'tournament_logo'], 'point_table');

        $this->assertSame([], $template->personImagePlaceholders());
    }

    #[Test]
    public function every_person_placeholder_is_discoverable_and_labelled(): void
    {
        $template = $this->templateDrawing(TournamentTemplate::PERSON_IMAGE_PLACEHOLDERS);

        $this->assertSame(
            TournamentTemplate::PERSON_IMAGE_PLACEHOLDERS,
            $template->personImagePlaceholders()
        );

        // The generate page renders a heading per panel from this map.
        foreach (TournamentTemplate::PERSON_IMAGE_PLACEHOLDERS as $placeholder) {
            $this->assertArrayHasKey($placeholder, TournamentTemplate::PERSON_IMAGE_LABELS);
            $this->assertNotSame('', TournamentTemplate::PERSON_IMAGE_LABELS[$placeholder]);
        }
    }

    #[Test]
    public function a_duplicate_placeholder_is_reported_once(): void
    {
        // Two elements can share a placeholder (a photo and its mirrored shadow);
        // the panel must not be offered twice.
        $template = $this->templateDrawing(['player_image', 'player_image']);

        $this->assertSame(['player_image'], $template->personImagePlaceholders());
    }

    #[Test]
    public function the_renderer_and_the_model_agree_on_what_is_a_person(): void
    {
        // TemplateRenderService used to carry its own copy of this list; a panel
        // shown for a placeholder the renderer ignores does nothing at all.
        $service = new class extends TemplateRenderService
        {
            public function isPerson(string $placeholder): bool
            {
                return $this->isPersonPlaceholder($placeholder);
            }
        };

        foreach (TournamentTemplate::PERSON_IMAGE_PLACEHOLDERS as $placeholder) {
            $this->assertTrue($service->isPerson($placeholder), "{$placeholder} should be a person image");
        }

        foreach (['team_logo', 'tournament_logo', 'sponsor_logo', 'winner_logo', 'qr_code'] as $placeholder) {
            $this->assertFalse($service->isPerson($placeholder), "{$placeholder} should not be a person image");
        }
    }

    #[Test]
    public function a_brightness_override_changes_the_rendered_poster(): void
    {
        Storage::fake('public');

        // A mid-grey square standing in for a player photo.
        $photo = imagecreatetruecolor(400, 533);
        imagefill($photo, 0, 0, imagecolorallocate($photo, 120, 120, 120));
        $tmp = tempnam(sys_get_temp_dir(), 'ph') . '.png';
        imagepng($photo, $tmp);
        imagedestroy($photo);
        Storage::disk('public')->put('player_images/probe.png', file_get_contents($tmp));
        @unlink($tmp);

        $template = $this->templateDrawing(['player_image']);
        $data = ['player_image' => 'player_images/probe.png'];

        $dark = (new TemplateRenderService())
            ->overrideBackgroundRemoval('player_image', false)
            ->overrideImageAdjustment('player_image', ['brightness' => -40, 'contrast' => 0])
            ->renderTemplate($template, $data);

        $bright = (new TemplateRenderService())
            ->overrideBackgroundRemoval('player_image', false)
            ->overrideImageAdjustment('player_image', ['brightness' => 40, 'contrast' => 0])
            ->renderTemplate($template, $data);

        $this->assertNotSame(
            Storage::disk('public')->get($dark),
            Storage::disk('public')->get($bright),
            'the two brightness settings produced identical bytes — the override is not reaching the render'
        );

        $this->assertGreaterThan(
            $this->meanLuma(Storage::disk('public')->path($dark)),
            $this->meanLuma(Storage::disk('public')->path($bright)),
            'the +40 render should be lighter than the -40 one'
        );
    }

    #[Test]
    public function a_contrast_override_changes_the_rendered_poster(): void
    {
        Storage::fake('public');

        // A two-tone photo, so contrast has something to push apart.
        $photo = imagecreatetruecolor(400, 533);
        imagefill($photo, 0, 0, imagecolorallocate($photo, 90, 90, 90));
        imagefilledrectangle($photo, 0, 0, 399, 266, imagecolorallocate($photo, 170, 170, 170));
        $tmp = tempnam(sys_get_temp_dir(), 'ph') . '.png';
        imagepng($photo, $tmp);
        imagedestroy($photo);
        Storage::disk('public')->put('player_images/probe2.png', file_get_contents($tmp));
        @unlink($tmp);

        $template = $this->templateDrawing(['player_image']);
        $data = ['player_image' => 'player_images/probe2.png'];

        // GD convention: NEGATIVE contrast increases contrast.
        $flat = (new TemplateRenderService())
            ->overrideBackgroundRemoval('player_image', false)
            ->overrideImageAdjustment('player_image', ['brightness' => 0, 'contrast' => 40])
            ->renderTemplate($template, $data);

        $punchy = (new TemplateRenderService())
            ->overrideBackgroundRemoval('player_image', false)
            ->overrideImageAdjustment('player_image', ['brightness' => 0, 'contrast' => -40])
            ->renderTemplate($template, $data);

        $this->assertGreaterThan(
            $this->lumaSpread(Storage::disk('public')->path($flat)),
            $this->lumaSpread(Storage::disk('public')->path($punchy)),
            'increasing contrast should widen the spread between the light and dark halves'
        );
    }

    private function meanLuma(string $path): float
    {
        [$sum, $count] = $this->sampleLuma($path);

        return $count ? $sum / $count : 0.0;
    }

    private function lumaSpread(string $path): float
    {
        $image = imagecreatefrompng($path);
        $values = [];

        for ($y = 0; $y < imagesy($image); $y += 9) {
            for ($x = 0; $x < imagesx($image); $x += 9) {
                $c = imagecolorat($image, $x, $y);
                $values[] = 0.299 * (($c >> 16) & 255) + 0.587 * (($c >> 8) & 255) + 0.114 * ($c & 255);
            }
        }
        imagedestroy($image);

        $mean = array_sum($values) / count($values);

        return sqrt(array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $values)) / count($values));
    }

    /** @return array{0: float, 1: int} */
    private function sampleLuma(string $path): array
    {
        $image = imagecreatefrompng($path);
        $sum = 0.0;
        $count = 0;

        for ($y = 0; $y < imagesy($image); $y += 9) {
            for ($x = 0; $x < imagesx($image); $x += 9) {
                $c = imagecolorat($image, $x, $y);
                $sum += 0.299 * (($c >> 16) & 255) + 0.587 * (($c >> 8) & 255) + 0.114 * ($c & 255);
                $count++;
            }
        }
        imagedestroy($image);

        return [$sum, $count];
    }
}

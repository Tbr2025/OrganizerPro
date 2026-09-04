<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * The generate page, arrived at directly on a poster type.
 *
 * Every per-type panel is revealed by updateType(), and that only ran on a tab click — so
 * opening ?type=playing_xi (the link out of the templates list, or a refresh, which rewrites
 * the URL) left the panel hidden and the page looked like it had failed to load.
 */
class GeneratePagePanelsTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function page(string $type): string
    {
        $org = $this->makeOrganization('Org ' . uniqid());
        $tournament = $this->makeTournament($org, 'open');
        $admin = $this->makeSuperadmin($org);

        $response = $this->actingAs($admin)
            ->get(route('admin.tournaments.templates.generate', $tournament) . '?type=' . $type);
        $response->assertOk();

        return $response->getContent();
    }

    #[Test]
    public function the_panels_are_shown_for_the_type_in_the_url_at_first_paint(): void
    {
        $html = $this->page('playing_xi');

        $this->assertStringContainsString('function syncTypeSections(type)', $html);
        $this->assertStringContainsString(
            "document.addEventListener('DOMContentLoaded', () => syncTypeSections(currentType));",
            $html,
            'Nothing reveals the panel for the type already chosen by the URL.'
        );
        // And updateType must still route through it, or a tab click stops working.
        $this->assertStringContainsString('    syncTypeSections(type);', $html);
    }

    #[Test]
    public function the_playing_xi_photo_can_be_cropped_like_every_other_player_upload(): void
    {
        $html = $this->page('playing_xi');

        $this->assertStringContainsString('Crop Featured Player', $html);
        $this->assertStringContainsString('featuredCropImage', $html);
        $this->assertStringContainsString('applyCrop()', $html);

        // The crop result is written into the input the request actually reads, so what is sent
        // is the cropped image and never the original camera-roll photo.
        $this->assertStringContainsString("document.getElementById('xiPlayerImageUpload')", $html);
        $this->assertStringContainsString("{ input: 'xiPlayerImageUpload', field: 'featured_player_image_file' }", $html);

        // Shrink-only, the same ceiling as every other upload path in this codebase.
        $this->assertStringContainsString('maxWidth: 1600', $html);
        $this->assertStringNotContainsString('minWidth: 1600', $html);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\AuctionTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Layer ordering in the template editor.
 *
 * Stacking order is edited as a reorderable list rather than by typing z-index numbers. The
 * numbers are still what gets stored and what the LED wall reads — this pins down that the
 * whole path survives a round trip, because the editor UI was the only missing piece: the
 * parser already persisted `zIndex` and the wall already emitted it.
 */
class AuctionTemplateLayersTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    #[Test]
    public function the_editor_ships_a_layers_panel(): void
    {
        $org = $this->makeOrganization();
        $template = AuctionTemplate::create([
            'name' => 'Wall',
            'type' => AuctionTemplate::TYPE_LIVE_DISPLAY,
            'organization_id' => $org->id,
            'canvas_width' => 1601,
            'canvas_height' => 910,
        ]);

        $this->actingAs($this->makeAuctionOperator($org))
            ->get(route('admin.auction-templates.edit', $template))
            ->assertOk()
            ->assertSee('id="layers-list"', false)
            ->assertSee('layersBringToFront()', false)
            ->assertSee('layersSendToBack()', false)
            // Reordering renumbers the whole stack rather than nudging one value, because
            // hand-entered z-indexes collide and a nudge leaves them colliding.
            ->assertSee('function layersRenumber', false)
            // Delete lives in the layer row, but only for custom elements: the wall renders
            // a fixed set of built-ins, so removing one would lose its position rather than
            // take it off the screen.
            ->assertSee('function layersDelete', false)
            ->assertSee("key.startsWith('custom_')", false);
    }

    #[Test]
    public function a_layer_order_survives_a_save_and_reaches_the_wall(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $template = AuctionTemplate::create([
            'name' => 'Wall',
            'type' => AuctionTemplate::TYPE_LIVE_DISPLAY,
            'organization_id' => $org->id,
            'canvas_width' => 1601,
            'canvas_height' => 910,
        ]);

        // What the form posts after the designer drags "player_name" above "stats_table".
        $payload = [
            'name' => 'Wall',
            'type' => AuctionTemplate::TYPE_LIVE_DISPLAY,
            'render_mode' => 'positioned',
            'canvas_width' => 1601,
            'canvas_height' => 910,
            // The form posts a hidden 0 companion for this, so an absent key means the
            // operator unticked it — omit it here and the template saves as INACTIVE and the
            // wall stops resolving it.
            'is_active' => 1,
            'pos_player_name_top' => 210,
            'pos_player_name_left' => 545,
            'pos_player_name_zIndex' => 90,
            'pos_stats_table_top' => 545,
            'pos_stats_table_left' => 550,
            'pos_stats_table_zIndex' => 20,
        ];

        $this->actingAs($this->makeAuctionOperator($org))
            ->put(route('admin.auction-templates.update', $template), $payload)
            ->assertSessionHasNoErrors();

        $positions = $template->fresh()->element_positions;

        $this->assertSame(90, $positions['player_name']['zIndex']);
        $this->assertSame(20, $positions['stats_table']['zIndex']);

        // And the wall emits it, so what the designer stacked is what the hall sees.
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'auction_template_id' => $template->id,
        ]);

        $html = (string) $this->get(route('public.auction.live', $auction))->assertOk()->getContent();

        $this->assertStringContainsString('z-index:90', str_replace(' ', '', $html));
        $this->assertStringContainsString('z-index:20', str_replace(' ', '', $html));
    }

    #[Test]
    public function the_wall_scales_up_to_fill_the_screen(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);

        $html = (string) $this->get(route('public.auction.live', $auction))->assertOk()->getContent();

        /*
         * The scale was capped at 1, so a 1601x910 design sat at native size in the middle of
         * a 1920x1080 projector with black bands around it and the template's own background
         * covering only part of the screen.
         */
        // Scoped to the function body: the docblock above it legitimately quotes the old
        // capped expression, so a whole-page search would match our own explanation.
        preg_match('/function scaleLive\(\)\s*\{(.*?)\n        \}/s', $html, $m);
        $body = $m[1] ?? '';

        $this->assertNotSame('', $body, 'scaleLive() must exist on the wall');
        $this->assertStringContainsString('window.innerWidth / canvasWidth', $body);
        $this->assertStringNotContainsString(
            ', 1)',
            $body,
            'the scale must not be capped at 1 — the card has to grow to fill the screen'
        );
    }

    #[Test]
    public function a_player_card_template_is_accepted_by_the_wall(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);

        /*
         * The positioned editor edits one fixed element set whatever `type` is chosen, so
         * "Player Card" and "Live Display" describe the same canvas. Picking the former used
         * to produce a template the wall silently refused, and the auction fell back to its
         * old background with nothing to say why.
         */
        $template = AuctionTemplate::create([
            'name' => 'Main',
            'type' => AuctionTemplate::TYPE_PLAYER_CARD,
            'organization_id' => $org->id,
            'canvas_width' => 1601,
            'canvas_height' => 910,
            'is_default' => true,
            'is_active' => true,
        ]);

        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);

        $this->assertSame(
            $template->id,
            AuctionTemplate::resolveFor($auction, AuctionTemplate::TYPE_LIVE_DISPLAY)?->id
        );

        // The ticker is a genuinely different screen, so it must NOT be broadened.
        $this->assertNull(AuctionTemplate::resolveFor($auction, AuctionTemplate::TYPE_TICKER));
    }

    #[Test]
    public function the_wall_honours_the_tables_own_row_backgrounds(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);

        $template = AuctionTemplate::create([
            'name' => 'Wall',
            'type' => AuctionTemplate::TYPE_LIVE_DISPLAY,
            'organization_id' => $org->id,
            'canvas_width' => 1601,
            'canvas_height' => 910,
            'is_active' => true,
            'element_positions' => [
                'stats_table' => [
                    'top' => 545, 'left' => 550, 'width' => 500, 'height' => 150,
                    'headerBg' => 'rgba(0,0,0,0.7)',
                    'rowBg' => 'rgba(255,255,255,0.1)',
                ],
            ],
        ]);

        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'auction_template_id' => $template->id,
        ]);

        $html = (string) $this->get(route('public.auction.live', $auction))->assertOk()->getContent();

        /*
         * These were hardcoded to `transparent`, so a template that set them in the editor
         * had both silently dropped on the wall — the editor drew panels and the wall let the
         * background artwork show straight through them. That is most of why a stats table
         * "did not match" what was designed.
         */
        $this->assertStringContainsString('background: rgba(0,0,0,0.7);', $html);
        $this->assertStringContainsString('background: rgba(255,255,255,0.1);', $html);
    }

    #[Test]
    public function a_table_that_asks_for_no_background_stays_transparent(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);

        /*
         * The borderless look is deliberate — a boxed grid fights the artwork behind it — so
         * honouring the template must not mean forcing a background on a template that
         * deliberately cleared one.
         *
         * Note the built-in defaults DO specify backgrounds (rgba(0,0,0,0.7) and
         * rgba(255,255,255,0.1)), which the hardcoded `transparent` was overriding as well:
         * the wall was ignoring its own defaults, not just custom templates.
         */
        $template = AuctionTemplate::create([
            'name' => 'Bare',
            'type' => AuctionTemplate::TYPE_LIVE_DISPLAY,
            'organization_id' => $org->id,
            'canvas_width' => 1601,
            'canvas_height' => 910,
            'is_active' => true,
            'element_positions' => [
                'stats_table' => ['top' => 545, 'left' => 550, 'headerBg' => '', 'rowBg' => ''],
            ],
        ]);

        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'auction_template_id' => $template->id,
        ]);

        $html = (string) $this->get(route('public.auction.live', $auction))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/#stats-table-wrap thead tr \{\s*background: transparent;/',
            $html
        );
    }
}

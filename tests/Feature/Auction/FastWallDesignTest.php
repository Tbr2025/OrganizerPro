<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\AuctionTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * The wall's design has to be able to change while the wall is open.
 *
 * It was read ONCE from the boot payload, so a template switched mid-auction — or one a pool
 * carries — never reached the projector until somebody reloaded it.
 */
class FastWallDesignTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function scenario(): array
    {
        $org = $this->makeOrganization('Org ' . uniqid());
        $tournament = $this->makeTournament($org);

        return [$this->makeAuction($org, ['tournament_id' => $tournament->id, 'status' => 'running']), $org];
    }

    #[Test]
    public function the_snapshot_says_which_design_is_in_force(): void
    {
        [$auction] = $this->scenario();

        $key = $this->get(route('public.auction.fast-wall-snapshot', $auction))
            ->assertOk()
            ->json('design_key');

        $this->assertNotNull($key, 'Without a key the wall can never notice a change.');
    }

    #[Test]
    public function the_key_moves_when_the_template_changes(): void
    {
        [$auction, $org] = $this->scenario();

        $template = AuctionTemplate::create([
            'organization_id' => $org->id,
            'auction_id' => $auction->id,
            'name' => 'Wall',
            'type' => 'live_display',
            'canvas_width' => 1601,
            'canvas_height' => 910,
            'element_positions' => [],
            'is_active' => true,
            'is_default' => true,
        ]);

        $before = $this->get(route('public.auction.fast-wall-snapshot', $auction))->json('design_key');
        $this->assertStringStartsWith($template->id . ':', $before);

        // A touched template is a different design as far as the wall is concerned — the editor
        // saving new coordinates has to reach the projector.
        $template->forceFill(['updated_at' => now()->addMinute()])->save();

        $after = $this->get(route('public.auction.fast-wall-snapshot', $auction))->json('design_key');

        $this->assertNotSame($before, $after);
    }

    #[Test]
    public function the_design_is_a_separate_fetch_and_not_in_the_snapshot(): void
    {
        [$auction] = $this->scenario();

        $snapshot = $this->get(route('public.auction.fast-wall-snapshot', $auction))->assertOk()->json();

        /*
         * The design is nearly 12 KB against a snapshot of under two. Sending it every couple of
         * seconds to say "still the same template" would be most of the wall's traffic and carry
         * no information at all.
         */
        $this->assertArrayNotHasKey('design', $snapshot);
        $this->assertLessThan(6000, strlen(json_encode($snapshot)), 'The wall snapshot has grown heavy.');

        $design = $this->get(route('public.auction.fast-wall-design', $auction))->assertOk()->json();

        foreach (['positions', 'canvasWidth', 'canvasHeight', 'background'] as $key) {
            $this->assertArrayHasKey($key, $design);
        }
    }

    #[Test]
    public function the_wall_treats_its_design_as_something_that_can_change(): void
    {
        $wall = file_get_contents(resource_path('js/fast-auction/screens/Wall.vue'));

        // A const read once from boot is the bug this replaced.
        $this->assertStringContainsString('const design = ref(', $wall);
        $this->assertStringContainsString('design_key', $wall);

        // Everything derived from it has to be derived reactively, or the canvas keeps the old
        // size and the elements the old coordinates.
        foreach (['const positions = computed', 'const cw = computed', 'const columns = computed'] as $derived) {
            $this->assertStringContainsString($derived, $wall);
        }
    }
}

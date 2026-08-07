<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * The two control panels must never use a native browser dialog.
 *
 * Both are designed to run fullscreen on a hall projector, and the browser leaves
 * fullscreen to display an alert() or confirm() — so every confirmation dropped the room's
 * screen back to a windowed browser mid-auction. Restart was the worst of them, because it
 * confirms and is pressed while people are watching.
 *
 * There is no browser test layer in this repo, so the guard is a source assertion plus a
 * render check: the replacement markup has to actually reach the page, and nothing may
 * quietly reintroduce a native call later.
 */
class AuctionPanelDialogTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    /** @return array<string, string> */
    public static function panelSources(): array
    {
        return [
            'organizer panel' => ['resources/views/backend/pages/auction/organizer-panel.blade.php'],
            'offline panel' => ['resources/views/backend/pages/auction/offline-panel.blade.php'],
        ];
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('panelSources')]
    public function a_panel_calls_no_native_browser_dialog(string $relativePath): void
    {
        $source = file_get_contents(base_path($relativePath));
        $this->assertNotFalse($source, "could not read {$relativePath}");

        // Strip Blade comments first: the explanatory notes in these files legitimately
        // mention alert()/confirm() by name, and matching them would be noise.
        $code = preg_replace('/\{\{--.*?--\}\}/s', '', $source);
        // ...and the JS block comments that carry the same explanation.
        $code = preg_replace('#/\*.*?\*/#s', '', (string) $code);

        // A call, not a property or a method of our own: askConfirm( and confirmSale(
        // must not trip this, but confirm( and window.confirm( must.
        $pattern = '/(?<![A-Za-z0-9_$.])(?:window\.)?(alert|confirm)\s*\(/';

        $this->assertSame(
            0,
            preg_match_all($pattern, (string) $code, $matches),
            "{$relativePath} calls a native dialog: ".implode(', ', $matches[0] ?? [])
                .' — use this.toast() or await this.askConfirm() instead, or fullscreen breaks.'
        );
    }

    #[Test]
    public function the_organizer_panel_ships_the_in_page_dialogs(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id, 'status' => 'running']);

        $this->actingAs($this->makeAuctionOperator($org))
            ->get(route('admin.auction.organizer.panel', $auction))
            ->assertOk()
            // The replacement has to be on the page, not merely defined in the component.
            ->assertSee('_settleConfirm(true)', false)
            ->assertSee('confirmBox.open', false)
            ->assertSee('t in toasts', false);
    }

    #[Test]
    public function the_offline_panel_ships_the_in_page_dialogs(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'status' => 'running',
            'open_bid_mode' => 'offline',
        ]);

        $this->actingAs($this->makeAuctionOperator($org))
            ->get(route('admin.auction.organizer.offline-panel', $auction))
            ->assertOk()
            ->assertSee('_settleConfirm(true)', false)
            ->assertSee('confirmBox.open', false)
            ->assertSee('t in toasts', false);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\TournamentTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * The auction page has to offer the poster export from where the outcome is already chosen.
 *
 * The status chips (All / Sold / Unsold / On Auction / Waiting) are the selection an operator
 * has already made by the time they want posters of the sold players; a second, separate way to
 * pick them would only be a way to disagree with what is on screen.
 */
class AuctionPosterExportUiTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    #[Test]
    public function an_auction_with_no_poster_design_is_pointed_at_the_designer(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $this->makeAuctionPlayer($auction);

        // An empty menu tells an operator nothing about why it is empty.
        $this->actingAs($this->makeSuperadmin($org))
            ->get(route('admin.auctions.show', $auction))
            ->assertOk()
            ->assertSee('Design a poster')
            ->assertDontSee('Export posters');
    }

    #[Test]
    public function the_export_control_lists_the_designs_and_follows_the_status_chip(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $this->makeAuctionPlayer($auction);

        TournamentTemplate::create([
            'tournament_id' => $tournament->id,
            'type' => TournamentTemplate::TYPE_AUCTION_POSTER,
            'name' => 'Sold Design',
            'canvas_width' => 1920,
            'canvas_height' => 1080,
        ]);

        TournamentTemplate::create([
            'tournament_id' => $tournament->id,
            'type' => TournamentTemplate::TYPE_AUCTION_POSTER_PORTRAIT,
            'name' => 'Story Design',
            'canvas_width' => 1080,
            'canvas_height' => 1350,
        ]);

        $html = html_entity_decode(
            $this->actingAs($this->makeSuperadmin($org))
                ->get(route('admin.auctions.show', $auction))
                ->assertOk()
                ->getContent()
        );

        $this->assertStringContainsString('Sold Design', $html);
        $this->assertStringContainsString('Story Design', $html);
        // Both orientations are named, because "which shape is this one" is the question the
        // operator is actually asking when two designs sit side by side.
        $this->assertStringContainsString('Horizontal', $html);
        $this->assertStringContainsString('Vertical', $html);

        // The export carries whichever outcome the chips are showing — not a second selection.
        $this->assertStringContainsString(
            "['sold', 'unsold'].includes(statusFilter) ? statusFilter : 'all'",
            $html
        );

        // And it says which set it will run, so the wrong one cannot be run by accident.
        $this->assertStringContainsString("'Export Sold posters'", $html);
        $this->assertStringContainsString("'Export Unsold posters'", $html);
    }
}

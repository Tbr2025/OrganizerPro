<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\TournamentTemplate;
use App\Services\Poster\AuctionPosterData;
use App\Services\Poster\TemplateRenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * An auction poster has to carry the figures a room bids on, and survive being saved.
 *
 * The first cut of the placeholder list left out career stats entirely — the same three the
 * LED wall puts under a player's name — so a bidding poster showed a name, a role and a base
 * price, and nothing anybody could form a view from.
 */
class AuctionPosterTemplateTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    #[Test]
    public function the_designer_offers_the_stats_a_bidder_needs(): void
    {
        $placeholders = TournamentTemplate::getDefaultPlaceholders(TournamentTemplate::TYPE_AUCTION_POSTER);

        foreach (['total_matches', 'total_runs', 'total_wickets'] as $stat) {
            $this->assertContains($stat, $placeholders, "The poster should be able to show {$stat}.");
        }

        // Both orientations carry the same fields — a vertical poster is not a horizontal one
        // with less on it.
        $this->assertSame(
            $placeholders,
            TournamentTemplate::getDefaultPlaceholders(TournamentTemplate::TYPE_AUCTION_POSTER_PORTRAIT)
        );

        /*
         * And nothing the players table cannot fill. A placeholder for a batting average would
         * render blank on every poster ever made, which is worse than not offering it.
         */
        foreach ($placeholders as $field) {
            $this->assertNotContains($field, ['batting_average', 'strike_rate', 'best_score']);
        }
    }

    #[Test]
    public function a_real_player_fills_the_stats_and_hides_the_ones_they_have_not_earned(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);

        $withStats = $this->makeAuctionPlayer($auction, [
            'player' => $this->makePlayer($org, [
                'name' => 'Career Player',
                'total_matches' => 42, 'total_runs' => 1500, 'total_wickets' => 60,
            ]),
        ]);

        $data = app(AuctionPosterData::class)->forPlayer($withStats);

        $this->assertSame('42', $data['total_matches']);
        $this->assertSame('1500', $data['total_runs']);
        $this->assertSame('60', $data['total_wickets']);

        $debutant = $this->makeAuctionPlayer($auction, [
            'player' => $this->makePlayer($org, [
                'name' => 'Debutant', 'total_matches' => 0, 'total_runs' => 0, 'total_wickets' => 0,
            ]),
        ]);

        /*
         * Blank, not "0". Generation hides blanks, so a player with no record gets a clean
         * poster instead of one advertising three zeroes — which on a bidding screen actively
         * misinforms the room.
         */
        $blank = app(AuctionPosterData::class)->forPlayer($debutant);

        $this->assertSame('', $blank['total_matches']);
        $this->assertSame('', $blank['total_runs']);
        $this->assertSame('', $blank['total_wickets']);
    }

    #[Test]
    public function a_saved_layout_comes_back_and_renders(): void
    {
        Storage::fake('public');

        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $ap = $this->makeAuctionPlayer($auction, [
            'player' => $this->makePlayer($org, ['name' => 'Poster Player', 'total_runs' => 900]),
        ]);

        $layout = [
            ['placeholder' => 'player_name', 'type' => 'text', 'x' => 20, 'y' => 20, 'fontSize' => 48],
            ['placeholder' => 'total_runs', 'type' => 'text', 'x' => 20, 'y' => 40, 'fontSize' => 32],
            ['placeholder' => 'sold_status', 'type' => 'text', 'x' => 70, 'y' => 80, 'fontSize' => 40],
        ];

        $this->actingAs($this->makeSuperadmin($org))
            ->post(route('admin.tournaments.templates.store', $tournament), [
                'name' => 'Lot Announcement',
                'type' => TournamentTemplate::TYPE_AUCTION_POSTER,
                'layout_json' => json_encode($layout),
                'canvas_width' => 1920,
                'canvas_height' => 1080,
            ])
            ->assertRedirect();

        $template = TournamentTemplate::where('tournament_id', $tournament->id)->sole();

        // The layout survives the round trip — a template saved with elements and read back
        // empty is a poster that renders as a bare background.
        $this->assertCount(3, $template->layout_json);
        $this->assertSame(1920, $template->canvas_width);
        $this->assertSame(1080, $template->canvas_height);
        // The stats placeholder is stored as an offered field, not silently dropped.
        $this->assertContains('total_runs', $template->placeholders);

        // And it draws, with the player's own figures rather than the editor's samples.
        $path = app(TemplateRenderService::class)->renderTemplate(
            $template,
            app(AuctionPosterData::class)->forPlayer($ap),
            false,
            true
        );

        $this->assertNotEmpty($path);
        Storage::disk('public')->assertExists($path);
    }
}

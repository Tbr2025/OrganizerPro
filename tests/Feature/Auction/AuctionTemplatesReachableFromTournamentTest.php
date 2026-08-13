<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\AuctionTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * The auction screens were unreachable from the place people looked for them.
 *
 * A tournament's Templates page listed eight poster types and no auction among them, because
 * `AuctionTemplate` is a separate table owned by an auction (or an organization, or nothing at
 * all) rather than by a tournament. The designer existed, at `/admin/auction-templates` — it
 * was simply never linked from the tournament that needed it, so an organizer looking for the
 * bidding poster concluded it did not exist.
 */
class AuctionTemplatesReachableFromTournamentTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    #[Test]
    public function an_auction_tournaments_templates_page_offers_the_auction_screens(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);

        AuctionTemplate::create([
            'name' => 'Season Wall',
            'type' => AuctionTemplate::TYPE_LIVE_DISPLAY,
            'auction_id' => $auction->id,
            'organization_id' => $org->id,
            'canvas_width' => 1601,
            'canvas_height' => 910,
            'is_default' => true,
            'is_active' => true,
        ]);

        $page = $this->actingAs($this->makeSuperadmin($org))
            ->get(route('admin.tournaments.templates.index', $tournament))
            ->assertOk();

        $page->assertSee('Auction Screens');
        $page->assertSee('Season Wall');

        // Each type must lead somewhere useful — the create form for THIS auction, not a
        // blank one the organizer then has to point at the right auction by hand.
        // Escaped, because the href carries `&amp;` between the two query parameters.
        $page->assertSee(route('admin.auction-templates.create', [
            'type' => AuctionTemplate::TYPE_PLAYER_CARD,
            'auction_id' => $auction->id,
        ]));
    }

    #[Test]
    public function an_open_tournament_is_not_offered_auction_screens(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org, 'open');

        $this->actingAs($this->makeSuperadmin($org))
            ->get(route('admin.tournaments.templates.index', $tournament))
            ->assertOk()
            ->assertDontSee('Auction Screens');
    }

    #[Test]
    public function an_auction_tournament_without_an_auction_is_told_so_rather_than_sent_to_the_designer(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);

        $this->actingAs($this->makeSuperadmin($org))
            ->get(route('admin.tournaments.templates.index', $tournament))
            ->assertOk()
            ->assertSee('Auction Screens')
            ->assertSee('no auction set up yet');
    }

    #[Test]
    public function the_create_form_arrives_on_the_type_and_auction_it_was_linked_for(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id, 'name' => 'Prefill Cup']);

        $html = $this->actingAs($this->makeAuctionOperator($org))
            ->get(route('admin.auction-templates.create', [
                'type' => AuctionTemplate::TYPE_PLAYER_CARD,
                'auction_id' => $auction->id,
            ]))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<option value="player_card"[^>]*selected/',
            $html,
            'The type picker should arrive on the type the link asked for.'
        );

        $this->assertMatchesRegularExpression(
            '/<option value="' . $auction->id . '"[^>]*selected/',
            $html,
            'The auction picker should arrive on the auction the link asked for.'
        );
    }

    #[Test]
    public function a_guessed_auction_id_in_the_url_does_not_preselect_it(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);

        // Nonsense in, nothing selected — the prefill is a convenience, never an authority.
        $html = $this->actingAs($this->makeAuctionOperator($org))
            ->get(route('admin.auction-templates.create', [
                'type' => 'not_a_real_type',
                'auction_id' => 999999,
            ]))
            ->assertOk()
            ->getContent();

        $this->assertDoesNotMatchRegularExpression('/<option value="999999"/', $html);
        $this->assertMatchesRegularExpression(
            '/<option value="live_display"[^>]*selected/',
            $html,
            'An unrecognised type should fall back to the LED wall, not select nothing.'
        );
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Http\Controllers\Backend\AuctionOrganizerController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * What the organizer panel's poll actually carries.
 *
 * `sold_players` shipped EVERY sale, each with the whole Player model and the whole team:
 * measured at 870 KB of an 889 KB payload on a 465-player auction, fetched again on every
 * reconcile and then merged and re-rendered by Alpine. The panel reads four fields off exactly
 * one of them — it looks up the player who just left the block to caption the SOLD overlay.
 */
class AuctionPanelPollPayloadTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    /** @return array{0: \App\Models\Auction, 1: \App\Models\ActualTeam} */
    private function auctionWithSales(int $sales): array
    {
        $org = $this->makeOrganization('Org ' . uniqid());
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 100_000_000,
            'min_squad_size' => 8,
            'min_price_per_player' => 1_000_000,
        ]);
        $team = $this->makeTeam($org, 'Buyers', $tournament);

        for ($i = 0; $i < $sales; $i++) {
            $this->makeAuctionPlayer($auction, [
                'status' => 'sold',
                'sold_to_team_id' => $team->id,
                'final_price' => 1_000_000 + $i,
            ]);
        }

        return [$auction, $team];
    }

    private function poll(\App\Models\Auction $auction): array
    {
        return json_decode(app(AuctionOrganizerController::class)->pollState($auction)->getContent(), true);
    }

    #[Test]
    public function the_poll_carries_only_the_recent_sales_however_many_there_have_been(): void
    {
        [$auction] = $this->auctionWithSales(40);
        $data = $this->poll($auction);

        // A window, not the whole auction — this is what stops the payload growing all evening.
        $this->assertCount(10, $data['sold_players']);

        // And the count is still the truth, because it is counted in the database rather than
        // taken from the length of the list that was sent.
        $this->assertSame(40, $data['stats']['sold_count']);
    }

    #[Test]
    public function a_sale_carries_the_four_fields_the_overlay_reads_and_no_more(): void
    {
        [$auction, $team] = $this->auctionWithSales(1);
        $sale = $this->poll($auction)['sold_players'][0];

        $this->assertSame(
            ['id', 'player_id', 'final_price', 'player', 'sold_to_team'],
            array_keys($sale)
        );

        // The whole 86-column Player model used to travel here, on every poll, per sale.
        $this->assertSame(['id', 'name', 'image_path'], array_keys($sale['player']));

        /*
         * `logo_url` is the name the overlay binds to and it has never had one: ActualTeam
         * declares no $appends, so serialising the model emitted real columns only and the
         * winning team's crest silently never rendered on the SOLD card.
         */
        $this->assertArrayHasKey('logo_url', $sale['sold_to_team']);
        $this->assertSame($team->team_logo_url, $sale['sold_to_team']['logo_url']);
    }

    #[Test]
    public function the_payload_does_not_grow_with_the_size_of_the_auction(): void
    {
        [$small] = $this->auctionWithSales(5);
        [$large] = $this->auctionWithSales(60);

        $smallSize = strlen(json_encode($this->poll($small)['sold_players']));
        $largeSize = strlen(json_encode($this->poll($large)['sold_players']));

        // Sixty sales must not cost twelve times five. Capped at ten either way, so the larger
        // auction is at most twice the smaller — and never a function of how long the evening is.
        $this->assertLessThan($smallSize * 3, $largeSize);
    }
}

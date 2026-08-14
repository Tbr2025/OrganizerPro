<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Services\Auction\ClosedBidService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * The hall does not learn the winner before the draw it is watching has finished.
 *
 * `drawLot()` records the draw and awards the player in the SAME request, so from the first
 * millisecond the player is sold with a buying team and a final price. The wall read that and
 * painted the SOLD stamp, the buyer's crest and the result banner while the ring was still
 * supposed to be deciding.
 *
 * A hold in the browser is not a hold: the name has already been sent, a reload mid-spin shows it,
 * and the network tab always has it. So the PUBLIC payload withholds it, and this test is about
 * that payload rather than about any animation.
 */
class AuctionLotRevealTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAuctionScenario;

    private function tiedRound(): array
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'status' => 'running',
            'bid_type' => 'closed',
            'max_budget_per_team' => 100_000_000,
            'closed_bid_starts_at' => 8_000_000,
            'closed_bid_step' => 100_000,
            'closed_bid_max_pct_of_budget' => 70,
            'closed_bid_max_rebid_rounds' => 0,
            'bid_rules' => [['from' => 0, 'to' => 200_000_000, 'increment' => 100_000]],
        ]);

        $teamA = $this->makeTeam($org, 'Alpha', $tournament);
        $teamB = $this->makeTeam($org, 'Bravo', $tournament);

        $player = $this->makeAuctionPlayer($auction, [
            'status' => 'on_auction',
            'current_price' => 8_000_000,
            'current_bid_team_id' => $teamA->id,
        ]);

        $sealed = app(ClosedBidService::class);
        $round = $sealed->openRoundFor($player, $auction);
        $sealed->start($round, null);

        // Both at the same figure: the tie the draw exists to settle.
        foreach ([$teamA, $teamB] as $team) {
            $sealed->accept($round, $team);
            $sealed->submit($round, $team, 8_100_000, null);
        }

        $sealed->lockAndReveal($round->fresh(), null);

        // The award writes an audit bid, and that row requires a user — in a hall it is whoever
        // pressed the button, so the test acts as one too.
        $operator = $this->makeAuctionOperator($org);

        return compact('auction', 'player', 'teamA', 'teamB', 'sealed', 'operator')
            + ['round' => $round->fresh()];
    }

    #[Test]
    public function the_winner_is_withheld_from_the_room_while_the_spin_runs(): void
    {
        ['auction' => $auction, 'player' => $player, 'sealed' => $sealed, 'operator' => $operator] = $this->tiedRound();

        $round = $sealed->currentRound($player->fresh());
        $this->assertContains($round->state, ['tie', 'awaiting_lot'], 'the round must be tied to draw');

        $sealed->drawLot($round, $operator);

        // Mid-spin: the server has a winner and the room may not have it.
        $public = $sealed->stateForPublic($auction->fresh(), $player->fresh());

        $this->assertGreaterThan(0, $public['tie']['spin_remaining_ms'], 'the spin must still be running');
        $this->assertNull($public['tie']['lot_winner_team_id'], 'the room must not be told yet');

        // The organizer's own payload is NOT narrowed — the desk is allowed to know what it drew.
        $organizer = $sealed->stateForOrganizer($auction->fresh(), $player->fresh());
        $this->assertNotNull($organizer['tie']['lot_winner_team_id']);
    }

    #[Test]
    public function the_public_feed_still_reports_the_player_as_on_the_block_during_the_spin(): void
    {
        ['auction' => $auction, 'player' => $player, 'sealed' => $sealed, 'operator' => $operator] = $this->tiedRound();

        $sealed->drawLot($sealed->currentRound($player->fresh()), $operator);

        // The sale has been applied server-side already — that is precisely the problem.
        $this->assertSame('sold', $player->fresh()->status);

        $response = $this->getJson("/auction/{$auction->id}/active-player")->assertOk();

        $shown = $response->json('auctionPlayer');

        $this->assertSame('on_auction', $shown['status'], 'the wall must not see a sale yet');
        $this->assertNull($shown['sold_to_team'] ?? null);
        $this->assertNull($shown['current_bid_team'] ?? null);
    }

    #[Test]
    public function once_the_spin_is_over_the_winner_is_public(): void
    {
        ['auction' => $auction, 'player' => $player, 'sealed' => $sealed, 'operator' => $operator] = $this->tiedRound();

        $round = $sealed->currentRound($player->fresh());
        $sealed->drawLot($round, $operator);

        // Wind the draw back past the spin window rather than waiting fifteen seconds for it.
        $round->fresh()->update(['lot_drawn_at' => now()->subSeconds(60)]);

        // The round is finished AND its spin is over, so the sealed block retires entirely — the
        // wall goes back to the card, which now carries the sale it was holding.
        $this->assertNull($sealed->stateForPublic($auction->fresh(), $player->fresh()));

        $shown = $this->getJson("/auction/{$auction->id}/active-player")->assertOk();

        $this->assertSame('sold', $shown->json('lastActionPlayer.status'), 'and now the room is told');
    }
}

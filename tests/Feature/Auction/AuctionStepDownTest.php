<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\AuctionBid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * "−" lowers the price. It does not delete anybody's bid.
 *
 * Reported live as "increment is working, decrement not working — it auto hides the field and
 * jerks". Both halves had the same cause: the button was wired to the auction's UNDO stack, which
 * reverses the last ACTION. On a lot with a leading team that action is their bid, so pressing "−"
 * removed the team — the leading-team panel emptied, the card reflowed around the gap — and when
 * the last action was not a bid it refused outright, which reads as a dead button.
 *
 * It is now the mirror of "+": the ladder decides the rung, the price moves, the leader stays.
 */
class AuctionStepDownTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAuctionScenario;

    #[Test]
    public function it_lowers_one_rung_and_takes_the_leading_team_off_the_price(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, [
            'max_budget_per_team' => 100_000_000,
            'base_price' => 1_000_000,
            'bid_rules' => [['from' => 0, 'to' => 200_000_000, 'increment' => 100_000]],
        ]);
        $operator = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'Alpha');

        $ap = $this->makeAuctionPlayer($auction, [
            'status' => 'on_auction',
            'base_price' => 1_000_000,
            'current_price' => 1_300_000,
            'current_bid_team_id' => $team->id,
        ]);

        $bidsBefore = AuctionBid::where('auction_player_id', $ap->id)->count();

        $this->actingAs($operator)
            ->postJson(route('admin.auctions.players.decreaseBid'), [
                'auctionId' => $auction->id,
                'playerID' => $ap->id,
            ])
            ->assertOk()
            ->assertJson(['success' => true, 'current_price' => 1_200_000]);

        $ap->refresh();

        $this->assertSame('1200000.00', (string) $ap->current_price);

        /*
         * The leader comes off with the figure.
         *
         * This asserted the opposite for an afternoon — that a price correction leaves the leading
         * team alone — which is defensible and was wrong in the room: "−" is the organizer saying
         * the bidding is at this figure, and a crest beside it asserts that a particular team bid
         * it. Nobody did. The panel showed a team leading at a price the organizer had just typed.
         */
        $this->assertNull($ap->current_bid_team_id, 'an organizer adjustment names nobody');

        // The history is still intact: a correction moves a price, it does not delete a bid.
        $this->assertSame($bidsBefore, AuctionBid::where('auction_player_id', $ap->id)->count(), 'no bid is deleted');
    }

    #[Test]
    public function it_refuses_to_go_below_the_price_the_lot_opened_at(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, [
            'max_budget_per_team' => 100_000_000,
            'base_price' => 1_000_000,
            'bid_rules' => [['from' => 0, 'to' => 200_000_000, 'increment' => 100_000]],
        ]);
        $operator = $this->makeAuctionOperator($org);

        $ap = $this->makeAuctionPlayer($auction, [
            'status' => 'on_auction',
            'base_price' => 1_000_000,
            'current_price' => 1_000_000,
        ]);

        // A figure below the base is one no team could have bid, so it must never reach the wall.
        $this->actingAs($operator)
            ->postJson(route('admin.auctions.players.decreaseBid'), [
                'auctionId' => $auction->id,
                'playerID' => $ap->id,
            ])
            ->assertStatus(400)
            ->assertJson(['success' => false]);

        $this->assertSame('1000000.00', (string) $ap->fresh()->current_price);
    }
}

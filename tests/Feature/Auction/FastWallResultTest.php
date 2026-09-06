<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * What the wall shows between lots.
 *
 * The card OUTLIVES the lot. `auctionPlayer` is only ever somebody on the block, so the instant
 * the hammer falls the player is gone from that half of the feed — and a wall reading only that
 * half loses the face the room is still looking at. `lastActionPlayer` is the other half: the
 * whole settled row, so the card stays up wearing its SOLD badge and the buyer's crest.
 *
 * The wall decides how long to hold it from `stage.key` and the two server clocks, never from a
 * browser clock: the app runs on Asia/Dubai and the database on UTC.
 */
class FastWallResultTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function scenario(): array
    {
        $org = $this->makeOrganization('Org ' . uniqid());
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id, 'status' => 'running']);

        return [$auction, $this->makeTeam($org, 'Alpha', $tournament)];
    }

    private function active($auction): array
    {
        return $this->get(route('public.auction.fast-wall-snapshot', $auction))
            ->assertOk()
            ->json('active');
    }

    #[Test]
    public function a_sale_leaves_the_whole_row_on_the_feed_so_the_card_can_stay_up(): void
    {
        [$auction, $team] = $this->scenario();
        $sale = $this->makeAuctionPlayer($auction, [
            'status' => 'sold',
            'sold_to_team_id' => $team->id,
            'final_price' => 4_200_000,
        ]);

        $active = $this->active($auction);
        $last = $active['lastActionPlayer'];

        $this->assertNull($active['auctionPlayer'], 'A sold player is no longer on the block.');
        $this->assertSame($sale->id, $last['id']);
        $this->assertSame('sold', $last['status']);
        $this->assertSame(4_200_000, (int) $last['final_price']);
        $this->assertSame('Alpha', $last['sold_to_team']['name']);
        // The crest goes on the card at the template's own coordinates.
        $this->assertArrayHasKey('logo_path', $last['sold_to_team']);
        // The player, not a summary of them: the card draws a face, a name and a role.
        $this->assertNotEmpty($last['player']['name']);
    }

    #[Test]
    public function an_unsold_and_a_skipped_lot_stay_on_the_feed_too(): void
    {
        foreach (['unsold', 'skipped'] as $outcome) {
            [$auction] = $this->scenario();
            $this->makeAuctionPlayer($auction, ['status' => $outcome]);

            $last = $this->active($auction)['lastActionPlayer'];

            $this->assertSame($outcome, $last['status']);
            $this->assertNull($last['sold_to_team'], 'Nothing was bought, so there is no buyer to name.');
        }
    }

    #[Test]
    public function the_newest_result_wins(): void
    {
        [$auction, $team] = $this->scenario();

        $this->makeAuctionPlayer($auction, ['status' => 'unsold'])
            ->forceFill(['updated_at' => now()->subSeconds(10)])->save();
        $latest = $this->makeAuctionPlayer($auction, ['status' => 'sold', 'sold_to_team_id' => $team->id]);

        $this->assertSame($latest->id, $this->active($auction)['lastActionPlayer']['id']);
    }

    #[Test]
    public function both_clocks_the_hold_is_measured_with_are_on_the_feed(): void
    {
        [$auction] = $this->scenario();

        $this->makeAuctionPlayer($auction, ['status' => 'sold'])
            ->forceFill(['updated_at' => now()->subMinutes(5)])->save();

        $active = $this->active($auction);

        // Both from the server. A browser subtracting its own clock from the database's would
        // be four hours out, and the hold would either never start or never end.
        $this->assertIsInt($active['server_time']);
        $this->assertIsInt($active['lastActionPlayer']['updated_at']);
        $this->assertGreaterThanOrEqual(
            300,
            $active['server_time'] - $active['lastActionPlayer']['updated_at'],
            'The wall works out how long a result has been up from these two numbers.'
        );

        // And the caption it falls back to once the hold is over.
        $this->assertArrayHasKey('key', $active['stage']);
    }

    #[Test]
    public function a_player_on_the_block_is_the_card_and_there_is_no_result_to_hold(): void
    {
        [$auction] = $this->scenario();
        $this->makeAuctionPlayer($auction, ['status' => 'waiting']);
        $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);

        $active = $this->active($auction);

        $this->assertSame('on_auction', $active['auctionPlayer']['status']);
        $this->assertArrayNotHasKey('lastActionPlayer', $active);
    }
}

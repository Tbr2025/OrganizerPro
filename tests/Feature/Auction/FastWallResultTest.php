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
 * `activePlayer()` only ever returns somebody ON the block, so the moment a player sold they
 * vanished from the feed and the sale passed without a mark on the wall. The result block is the
 * missing half.
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

    private function snapshot($auction): array
    {
        return $this->get(route('public.auction.fast-wall-snapshot', $auction))->assertOk()->json();
    }

    #[Test]
    public function a_sale_is_reported_with_everything_the_seal_prints(): void
    {
        [$auction, $team] = $this->scenario();
        $sale = $this->makeAuctionPlayer($auction, [
            'status' => 'sold',
            'sold_to_team_id' => $team->id,
            'final_price' => 4_200_000,
        ]);

        $result = $this->snapshot($auction)['result'];

        $this->assertSame($sale->id, $result['id']);
        $this->assertSame('sold', $result['outcome']);
        $this->assertSame(4_200_000, (int) $result['price']);
        $this->assertSame('Alpha', $result['team']);
        $this->assertArrayHasKey('image_path', $result);
    }

    #[Test]
    public function an_unsold_and_a_skipped_lot_are_reported_too(): void
    {
        foreach (['unsold', 'skipped'] as $outcome) {
            [$auction] = $this->scenario();
            $this->makeAuctionPlayer($auction, ['status' => $outcome]);

            $result = $this->snapshot($auction)['result'];

            // Each gets its own seal on the wall — a red one and an amber one.
            $this->assertSame($outcome, $result['outcome']);
            $this->assertNull($result['price'], 'Only a sale has a price to print.');
        }
    }

    #[Test]
    public function the_newest_result_wins(): void
    {
        [$auction, $team] = $this->scenario();

        $this->makeAuctionPlayer($auction, ['status' => 'unsold'])
            ->forceFill(['updated_at' => now()->subSeconds(10)])->save();
        $latest = $this->makeAuctionPlayer($auction, ['status' => 'sold', 'sold_to_team_id' => $team->id]);

        $this->assertSame($latest->id, $this->snapshot($auction)['result']['id']);
    }

    #[Test]
    public function an_old_result_clears_rather_than_sitting_on_the_wall_all_evening(): void
    {
        [$auction] = $this->scenario();

        $this->makeAuctionPlayer($auction, ['status' => 'sold'])
            ->forceFill(['updated_at' => now()->subMinutes(5)])->save();

        // A break must not leave a stale SOLD card up for twenty minutes.
        $this->assertNull($this->snapshot($auction)['result']);
    }

    #[Test]
    public function a_waiting_player_is_not_a_result(): void
    {
        [$auction] = $this->scenario();
        $this->makeAuctionPlayer($auction, ['status' => 'waiting']);
        $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);

        $this->assertNull($this->snapshot($auction)['result']);
    }
}

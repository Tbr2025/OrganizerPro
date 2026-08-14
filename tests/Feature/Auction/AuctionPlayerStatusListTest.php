<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\ActualTeam;
use App\Models\Auction;
use App\Models\AuctionPlayer;
use App\Models\Organization;
use App\Models\Player;
use App\Models\Tournament;
use App\Services\Auction\AuctionStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Sold, unsold and still-to-come, in one list.
 *
 * The team manager's player list excluded `player_mode = 'retained'`, meaning to drop pre-kept
 * players — and AuctionSaleService writes that same value on a SALE. So every player who had
 * actually been bought vanished from the list the moment they were bought. On live all six sold
 * players were invisible there, which is the opposite of what a manager needs during an auction.
 */
class AuctionPlayerStatusListTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_sale_and_a_retention_are_told_apart_by_the_auction_row_not_player_mode(): void
    {
        $org = Organization::create(['name' => 'Org']);
        $tournament = Tournament::create([
            'name' => 'T', 'slug' => 't-status', 'start_date' => '2026-01-01',
            'organization_id' => $org->id, 'type' => 'auction',
        ]);
        $auction = Auction::create([
            'name' => 'A', 'status' => 'running', 'max_budget_per_team' => 100_000_000,
            'base_price' => 1_000_000, 'organization_id' => $org->id, 'tournament_id' => $tournament->id,
            'bid_type' => 'open', 'bid_timer_seconds' => 30,
            'bid_rules' => [['from' => 0, 'to' => 200_000_000, 'increment' => 100_000]],
        ]);
        $team = ActualTeam::create([
            'name' => 'Alpha', 'organization_id' => $org->id, 'tournament_id' => $tournament->id,
        ]);

        $make = function (string $name, array $row) use ($org, $auction) {
            // Both a sale and a keep leave player_mode as 'retained'. That is the trap.
            $player = Player::create([
                'organization_id' => $org->id, 'name' => $name, 'email' => str($name)->slug() . '@x.test',
                'status' => 'approved', 'player_mode' => 'retained',
            ]);

            AuctionPlayer::create(array_merge([
                'auction_id' => $auction->id, 'player_id' => $player->id,
                'organization_id' => $org->id, 'base_price' => 1_000_000,
            ], $row));

            return $player;
        };

        $sold = $make('Bought Player', [
            'status' => 'sold', 'is_retained' => false,
            'final_price' => 2_600_000, 'sold_to_team_id' => $team->id,
        ]);
        $kept = $make('Kept Player', [
            'status' => 'waiting', 'is_retained' => true, 'retained_price' => 5_000_000,
        ]);
        $unsold = $make('Unsold Player', ['status' => 'unsold', 'is_retained' => false]);
        $waiting = $make('Waiting Player', ['status' => 'waiting', 'is_retained' => false]);

        $service = app(AuctionStatusService::class);

        $this->assertSame([$sold->id], $service->playerIdsWithStatus($auction, AuctionStatusService::STATUS_SOLD));
        $this->assertSame([$unsold->id], $service->playerIdsWithStatus($auction, AuctionStatusService::STATUS_UNSOLD));
        $this->assertSame([$waiting->id], $service->playerIdsWithStatus($auction, AuctionStatusService::STATUS_UPCOMING));

        // Only the genuine keep is excluded from a list of players still in play.
        $this->assertSame([$kept->id], $service->retainedPlayerIds($auction));

        $players = Player::whereIn('id', [$sold->id, $unsold->id, $waiting->id, $kept->id])->get();
        $service->attach($players, $auction);

        $this->assertSame('sold', $players->firstWhere('id', $sold->id)->auction_status);
        $this->assertSame(2_600_000.0, $players->firstWhere('id', $sold->id)->auction_price);
        $this->assertSame('Alpha', $players->firstWhere('id', $sold->id)->auction_team?->name);
        $this->assertSame('unsold', $players->firstWhere('id', $unsold->id)->auction_status);
        $this->assertSame('upcoming', $players->firstWhere('id', $waiting->id)->auction_status);
        $this->assertSame('retained', $players->firstWhere('id', $kept->id)->auction_status);
    }

    /**
     * A player ON the block counts as upcoming.
     *
     * Nothing has been decided about them yet, and a filter that dropped them for the minute they
     * are up would blink a name out of the list mid-lot.
     */
    #[Test]
    public function a_player_on_the_block_is_still_upcoming(): void
    {
        $org = Organization::create(['name' => 'Org']);
        $tournament = Tournament::create([
            'name' => 'T', 'slug' => 't-block', 'start_date' => '2026-01-01',
            'organization_id' => $org->id, 'type' => 'auction',
        ]);
        $auction = Auction::create([
            'name' => 'A', 'status' => 'running', 'max_budget_per_team' => 100_000_000,
            'base_price' => 1_000_000, 'organization_id' => $org->id, 'tournament_id' => $tournament->id,
            'bid_type' => 'open', 'bid_timer_seconds' => 30,
            'bid_rules' => [['from' => 0, 'to' => 200_000_000, 'increment' => 100_000]],
        ]);
        $player = Player::create([
            'organization_id' => $org->id, 'name' => 'On The Block',
            'email' => 'block@x.test', 'status' => 'approved',
        ]);
        AuctionPlayer::create([
            'auction_id' => $auction->id, 'player_id' => $player->id, 'organization_id' => $org->id,
            'status' => 'on_auction', 'is_retained' => false, 'base_price' => 1_000_000,
        ]);

        $this->assertSame(
            [$player->id],
            app(AuctionStatusService::class)->playerIdsWithStatus($auction, AuctionStatusService::STATUS_UPCOMING)
        );
    }
}

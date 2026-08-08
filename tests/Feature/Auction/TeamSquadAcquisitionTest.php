<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\AuctionPlayer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * A squad shows how each player arrived, and what they cost.
 *
 * `players.player_mode` cannot answer this: selling a player sets it to `retained`
 * (AuctionSaleService), so a buy and a keep are indistinguishable by that column — and the
 * value `sold` is never written at all, which makes the squad query's
 * whereIn('player_mode', ['retained','sold']) effectively a test for 'retained'.
 *
 * The auction row is the only honest source, and it is what the badges read.
 */
class TeamSquadAcquisitionTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    #[Test]
    public function a_bought_player_and_a_kept_player_are_told_apart(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $team = $this->makeTeam($org, 'Alpha Strikers', $tournament);

        $bought = $this->makePlayer($org, ['name' => 'Bought Bella', 'actual_team_id' => $team->id]);
        $kept = $this->makePlayer($org, ['name' => 'Kept Kiran', 'actual_team_id' => $team->id]);

        AuctionPlayer::create([
            'auction_id' => $auction->id, 'player_id' => $bought->id,
            'organization_id' => $org->id, 'base_price' => 100, 'current_price' => 100,
            'starting_price' => 100, 'status' => 'sold',
            'sold_to_team_id' => $team->id, 'final_price' => 4_500_000,
        ]);

        AuctionPlayer::create([
            'auction_id' => $auction->id, 'player_id' => $kept->id,
            'organization_id' => $org->id, 'base_price' => 0, 'current_price' => 0,
            'starting_price' => 0, 'status' => 'waiting',
            'is_retained' => true, 'team_id' => $team->id, 'retained_price' => 2_000_000,
        ]);

        $controller = app(\App\Http\Controllers\Backend\TeamManagerController::class);
        $method = new \ReflectionMethod($controller, 'attachAcquisition');
        $method->setAccessible(true);

        $players = collect([$bought->fresh(), $kept->fresh()]);
        $method->invoke($controller, $players, $team);

        $this->assertSame('auction', $players[0]->acquisition);
        $this->assertSame(4_500_000.0, $players[0]->acquisition_price);

        $this->assertSame('retained', $players[1]->acquisition);
        $this->assertSame(2_000_000.0, $players[1]->acquisition_price);
    }

    #[Test]
    public function a_player_who_never_went_through_an_auction_is_left_alone(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $team = $this->makeTeam($org, 'Alpha Strikers', $tournament);

        // An ordinary squad member. They are a player like any other — just not acquired
        // through the auction — so nothing is claimed about them.
        $plain = $this->makePlayer($org, ['name' => 'Plain Pat', 'actual_team_id' => $team->id]);

        $controller = app(\App\Http\Controllers\Backend\TeamManagerController::class);
        $method = new \ReflectionMethod($controller, 'attachAcquisition');
        $method->setAccessible(true);

        $players = collect([$plain->fresh()]);
        $method->invoke($controller, $players, $team);

        $this->assertNull($players[0]->acquisition);
        $this->assertNull($players[0]->acquisition_price_label);
    }

    #[Test]
    public function another_teams_purchase_is_not_claimed_as_ours(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $mine = $this->makeTeam($org, 'Alpha Strikers', $tournament);
        $theirs = $this->makeTeam($org, 'Bravo Kings', $tournament);

        $player = $this->makePlayer($org, ['name' => 'Their Buy']);

        AuctionPlayer::create([
            'auction_id' => $auction->id, 'player_id' => $player->id,
            'organization_id' => $org->id, 'base_price' => 100, 'current_price' => 100,
            'starting_price' => 100, 'status' => 'sold',
            'sold_to_team_id' => $theirs->id, 'final_price' => 9_000_000,
        ]);

        $controller = app(\App\Http\Controllers\Backend\TeamManagerController::class);
        $method = new \ReflectionMethod($controller, 'attachAcquisition');
        $method->setAccessible(true);

        $players = collect([$player->fresh()]);
        $method->invoke($controller, $players, $mine);

        $this->assertNull($players[0]->acquisition, 'a rival\'s purchase must not appear on our squad');
    }
}

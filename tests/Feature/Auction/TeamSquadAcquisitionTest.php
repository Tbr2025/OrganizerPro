<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\AuctionPlayer;
use App\Services\Auction\SquadAcquisitionService;
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


        $players = collect([$bought->fresh(), $kept->fresh()]);
        app(SquadAcquisitionService::class)->attach($players, $team);

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


        $players = collect([$plain->fresh()]);
        app(SquadAcquisitionService::class)->attach($players, $team);

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


        $players = collect([$player->fresh()]);
        app(SquadAcquisitionService::class)->attach($players, $mine);

        $this->assertNull($players[0]->acquisition, 'a rival\'s purchase must not appear on our squad');
    }

    #[Test]
    public function the_organizer_can_open_a_teams_roster_with_prices_and_stats(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $team = $this->makeTeam($org, 'Alpha Strikers', $tournament);

        $bought = $this->makePlayer($org, [
            'name' => 'Bought Bella', 'total_matches' => 120, 'total_runs' => 4000, 'total_wickets' => 12,
        ]);
        $kept = $this->makePlayer($org, ['name' => 'Kept Kiran', 'total_matches' => 80]);

        AuctionPlayer::create([
            'auction_id' => $auction->id, 'player_id' => $bought->id, 'organization_id' => $org->id,
            'base_price' => 100, 'current_price' => 100, 'starting_price' => 100,
            'status' => 'sold', 'sold_to_team_id' => $team->id, 'final_price' => 4_500_000,
        ]);
        AuctionPlayer::create([
            'auction_id' => $auction->id, 'player_id' => $kept->id, 'organization_id' => $org->id,
            'base_price' => 0, 'current_price' => 0, 'starting_price' => 0,
            'status' => 'waiting', 'is_retained' => true, 'team_id' => $team->id,
            'retained_price' => 2_000_000,
        ]);

        $response = $this->actingAs($this->makeAuctionOperator($org))
            ->getJson(route('admin.auction.organizer.api.team.squad', [$auction, $team]))
            ->assertOk();

        // Bought first, then kept — and the money and the career figures travel with them.
        $response->assertJsonPath('players.0.name', 'Bought Bella')
            ->assertJsonPath('players.0.acquisition', 'auction')
            ->assertJsonPath('players.0.price', 4500000)
            ->assertJsonPath('players.0.matches', 120)
            ->assertJsonPath('players.1.name', 'Kept Kiran')
            ->assertJsonPath('players.1.acquisition', 'retained')
            ->assertJsonPath('players.1.price', 2000000)
            ->assertJsonPath('totals.auction', 4500000)
            ->assertJsonPath('totals.retained', 2000000)
            ->assertJsonPath('totals.count', 2);
    }

    #[Test]
    public function a_team_from_another_tournament_is_refused(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, ['tournament_id' => $this->makeTournament($org)->id]);

        // {team} is not covered by the organizer-access middleware, so the controller has to
        // check that the team actually belongs to this auction's tournament.
        $elsewhere = $this->makeTeam($org, 'Elsewhere', $this->makeTournament($org));

        $this->actingAs($this->makeAuctionOperator($org))
            ->getJson(route('admin.auction.organizer.api.team.squad', [$auction, $elsewhere]))
            ->assertNotFound();
    }
}

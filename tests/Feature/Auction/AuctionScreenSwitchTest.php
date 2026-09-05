<?php
declare(strict_types=1);
namespace Tests\Feature\Auction;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * The classic and Fast screens both stay, and each carries a door to the other.
 */
class AuctionScreenSwitchTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    #[Test]
    public function the_organizer_panel_offers_the_fast_panel_and_back(): void
    {
        $org = $this->makeOrganization('Org ' . uniqid());
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id, 'status' => 'running']);
        $operator = $this->makeAuctionOperator($org);

        // Both screens stay. Neither replaces the other; each is a door to the other.
        $this->actingAs($operator)->get(route('admin.auction.organizer.panel', $auction))
            ->assertOk()
            ->assertSee(route('admin.auction.organizer.fast-panel', $auction->id), false)
            ->assertSee('Fast panel');

        $this->actingAs($operator)->get(route('admin.auction.organizer.fast-panel', $auction))
            ->assertOk()
            ->assertSee(route('admin.auction.organizer.panel', $auction->id), false)
            ->assertSee('Classic panel');
    }

    #[Test]
    public function the_team_bidding_screen_offers_the_fast_screen_and_back(): void
    {
        $org = $this->makeOrganization('Org ' . uniqid());
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'status' => 'running',
            'max_budget_per_team' => 100_000_000,
        ]);
        $team = $this->makeTeam($org, 'Alpha', $tournament);
        $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);

        $manager = $this->makePlainUser($org);
        $team->users()->syncWithoutDetaching([$manager->id => ['role' => 'Owner']]);

        $this->actingAs($manager)->get(route('team.auction.bidding.show', $auction))
            ->assertOk()
            ->assertSee(route('team.auction.bidding.fast', $auction->id), false)
            ->assertSee('Fast screen');

        $this->actingAs($manager)->get(route('team.auction.bidding.fast', $auction))
            ->assertOk()
            ->assertSee(route('team.auction.bidding.show', $auction->id), false)
            ->assertSee('Classic screen');
    }

    #[Test]
    public function the_public_wall_offers_the_fast_wall_and_back(): void
    {
        $org = $this->makeOrganization('Org ' . uniqid());
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id, 'status' => 'running']);

        $this->get(route('public.auction.live', $auction->id))
            ->assertOk()
            ->assertSee(route('public.auction.fast-wall', $auction->id), false)
            ->assertSee('Fast wall');

        $this->get(route('public.auction.fast-wall', $auction->id))
            ->assertOk()
            ->assertSee(route('public.auction.live', $auction->id), false)
            ->assertSee('Classic wall');
    }
}

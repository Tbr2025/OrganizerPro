<?php

namespace Tests\Feature\Auction;

use App\Models\ActualTeam;
use App\Models\Auction;
use App\Models\AuctionPlayer;
use App\Models\AuctionTeamBudget;
use App\Models\Organization;
use App\Models\Player;
use App\Services\Auction\AuctionPoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuctionTeamBudgetTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Auction $auction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create(['name' => 'Org']);
        $this->auction = Auction::create(['name' => 'A', 'status' => 'scheduled', 'max_budget_per_team' => 1000, 'organization_id' => $this->org->id]);
    }

    private function team(string $name): ActualTeam
    {
        return ActualTeam::create(['name' => $name, 'organization_id' => $this->org->id]);
    }

    #[Test]
    public function allocated_budget_uses_per_team_row_then_falls_back_to_uniform_cap(): void
    {
        $service = app(AuctionPoolService::class);
        $teamA = $this->team('A');
        $teamB = $this->team('B');

        AuctionTeamBudget::create([
            'auction_id' => $this->auction->id,
            'actual_team_id' => $teamA->id,
            'organization_id' => $this->org->id,
            'budget' => 1500,
        ]);

        $this->assertSame(1500.0, $service->allocatedBudget($this->auction, $teamA->id));
        // No row for team B → uniform cap.
        $this->assertSame(1000.0, $service->allocatedBudget($this->auction, $teamB->id));
    }

    #[Test]
    public function remaining_budget_subtracts_spend_and_enforces_affordability(): void
    {
        $service = app(AuctionPoolService::class);
        $team = $this->team('A');

        $player = Player::create(['name' => 'P', 'email' => 'p@x.test', 'status' => 'approved', 'organization_id' => $this->org->id]);
        AuctionPlayer::create([
            'auction_id' => $this->auction->id,
            'player_id' => $player->id,
            'organization_id' => $this->org->id,
            'starting_price' => 10,
            'base_price' => 10,
            'status' => 'sold',
            'sold_to_team_id' => $team->id,
            'final_price' => 400,
        ]);

        $this->assertSame(400.0, $service->spent($this->auction, $team->id));
        $this->assertSame(600.0, $service->remainingBudget($this->auction, $team->id));
        $this->assertTrue($service->canAfford($this->auction, $team->id, 600));
        $this->assertFalse($service->canAfford($this->auction, $team->id, 700));
    }
}

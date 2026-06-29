<?php

namespace Tests\Feature\Auction;

use App\Models\ActualTeam;
use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionPlayer;
use App\Models\Organization;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BudgetEnforcementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function sell_player_refuses_a_winning_bid_above_remaining_budget(): void
    {
        $org = Organization::create(['name' => 'Org']);
        $auction = Auction::create([
            'name' => 'Open', 'status' => 'scheduled', 'max_budget_per_team' => 1000,
            'organization_id' => $org->id, 'bid_type' => 'open',
        ]);
        $user = User::factory()->create(['organization_id' => $org->id]);
        $team = ActualTeam::create(['name' => 'A', 'organization_id' => $org->id]);

        $player = Player::create(['name' => 'P', 'email' => 'p@x.test', 'status' => 'approved', 'organization_id' => $org->id]);
        $ap = AuctionPlayer::create([
            'auction_id' => $auction->id, 'player_id' => $player->id, 'organization_id' => $org->id,
            'starting_price' => 100, 'base_price' => 100, 'status' => 'on_auction',
        ]);

        // Winning bid 5000 > 1000 cap → must be refused, player stays on auction.
        AuctionBid::create(['auction_id' => $auction->id, 'auction_player_id' => $ap->id, 'player_id' => $player->id, 'team_id' => $team->id, 'user_id' => $user->id, 'amount' => 5000]);

        $this->actingAs($user)
            ->postJson(route('admin.auction.organizer.api.player.sell', $auction), ['auction_player_id' => $ap->id])
            ->assertStatus(400);

        $this->assertSame('on_auction', $ap->fresh()->status);
    }

    #[Test]
    public function sell_player_completes_a_winning_bid_within_budget(): void
    {
        $org = Organization::create(['name' => 'Org']);
        $auction = Auction::create([
            'name' => 'Open', 'status' => 'scheduled', 'max_budget_per_team' => 1000,
            'organization_id' => $org->id, 'bid_type' => 'open',
        ]);
        $user = User::factory()->create(['organization_id' => $org->id]);
        $team = ActualTeam::create(['name' => 'A', 'organization_id' => $org->id]);

        $player = Player::create(['name' => 'P2', 'email' => 'p2@x.test', 'status' => 'approved', 'organization_id' => $org->id]);
        $ap = AuctionPlayer::create([
            'auction_id' => $auction->id, 'player_id' => $player->id, 'organization_id' => $org->id,
            'starting_price' => 100, 'base_price' => 100, 'status' => 'on_auction',
        ]);
        AuctionBid::create(['auction_id' => $auction->id, 'auction_player_id' => $ap->id, 'player_id' => $player->id, 'team_id' => $team->id, 'user_id' => $user->id, 'amount' => 600]);

        $this->actingAs($user)
            ->postJson(route('admin.auction.organizer.api.player.sell', $auction), ['auction_player_id' => $ap->id])
            ->assertOk();

        $ap->refresh();
        $this->assertSame('sold', $ap->status);
        $this->assertSame($team->id, $ap->sold_to_team_id);
    }
}

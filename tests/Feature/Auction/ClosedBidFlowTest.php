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

class ClosedBidFlowTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Auction $auction;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create(['name' => 'Org']);
        $this->auction = Auction::create([
            'name' => 'Closed Auction', 'status' => 'scheduled',
            'max_budget_per_team' => 1000, 'organization_id' => $this->org->id, 'bid_type' => 'closed',
        ]);
        $this->user = User::factory()->create(['organization_id' => $this->org->id]);
    }

    private function team(string $name): ActualTeam
    {
        return ActualTeam::create(['name' => $name, 'organization_id' => $this->org->id]);
    }

    private function onAuctionPlayer(int $base = 100): AuctionPlayer
    {
        $player = Player::create(['name' => 'P' . uniqid(), 'email' => uniqid() . '@x.test', 'status' => 'approved', 'organization_id' => $this->org->id]);

        return AuctionPlayer::create([
            'auction_id' => $this->auction->id, 'player_id' => $player->id, 'organization_id' => $this->org->id,
            'starting_price' => $base, 'base_price' => $base, 'status' => 'on_auction',
        ]);
    }

    #[Test]
    public function organizer_can_award_a_sealed_bid_winner_via_sell_to_team(): void
    {
        $ap = $this->onAuctionPlayer();
        $teamA = $this->team('A');
        $teamB = $this->team('B');

        // Two sealed bids; organizer manually awards team B (the higher one).
        AuctionBid::create(['auction_id' => $this->auction->id, 'auction_player_id' => $ap->id, 'player_id' => $ap->player_id, 'team_id' => $teamA->id, 'user_id' => $this->user->id, 'amount' => 300]);
        AuctionBid::create(['auction_id' => $this->auction->id, 'auction_player_id' => $ap->id, 'player_id' => $ap->player_id, 'team_id' => $teamB->id, 'user_id' => $this->user->id, 'amount' => 500]);

        $this->actingAs($this->user)
            ->postJson(route('admin.auction.organizer.api.player.sell-to-team', $this->auction), [
                'auction_player_id' => $ap->id, 'team_id' => $teamB->id, 'amount' => 500,
            ])->assertOk()->assertJson(['success' => true]);

        $ap->refresh();
        $this->assertSame('sold', $ap->status);
        $this->assertSame($teamB->id, $ap->sold_to_team_id);
        $this->assertSame('500.00', (string) $ap->final_price);
    }

    #[Test]
    public function sell_to_team_rejects_a_bid_above_the_team_budget(): void
    {
        $ap = $this->onAuctionPlayer();
        $team = $this->team('A');

        $this->actingAs($this->user)
            ->postJson(route('admin.auction.organizer.api.player.sell-to-team', $this->auction), [
                'auction_player_id' => $ap->id, 'team_id' => $team->id, 'amount' => 5000, // > 1000 cap
            ])->assertStatus(400)->assertJson(['success' => false]);

        $this->assertSame('on_auction', $ap->fresh()->status);
    }

    #[Test]
    public function closed_bids_team_filter_returns_only_that_teams_players(): void
    {
        $teamA = $this->team('A');
        $teamB = $this->team('B');

        $a = $this->onAuctionPlayer();
        $a->update(['status' => 'closed', 'sold_to_team_id' => $teamA->id]);
        $b = $this->onAuctionPlayer();
        $b->update(['status' => 'closed', 'sold_to_team_id' => $teamB->id]);

        $res = $this->actingAs($this->user)
            ->getJson(route('admin.auctions.closed-bids.fetch', ['team_id' => $teamA->id]))
            ->assertOk()->json('closedBids');

        $ids = collect($res)->pluck('id')->all();
        $this->assertContains($a->id, $ids);
        $this->assertNotContains($b->id, $ids); // regression: was returning nothing/everything
    }
}

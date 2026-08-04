<?php

namespace Tests\Feature\Auction;

use App\Models\AuctionPlayer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

class BudgetEnforcementTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    #[Test]
    public function sell_player_refuses_a_winning_bid_above_remaining_budget(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, ['max_budget_per_team' => 1000]);
        $user = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'A');

        $ap = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);

        // Winning bid 5000 > 1000 cap → must be refused, player stays on auction.
        $this->makeBid($ap, $team, 5000, $user);

        $this->actingAs($user)
            ->postJson(route('admin.auction.organizer.api.player.sell', $auction), ['auction_player_id' => $ap->id])
            ->assertStatus(400);

        $this->assertSame('on_auction', $ap->fresh()->status);
    }

    #[Test]
    public function sell_player_completes_a_winning_bid_within_budget(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, ['max_budget_per_team' => 1000]);
        $user = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'A');

        $ap = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);
        $this->makeBid($ap, $team, 600, $user);

        $this->actingAs($user)
            ->postJson(route('admin.auction.organizer.api.player.sell', $auction), ['auction_player_id' => $ap->id])
            ->assertOk();

        $ap->refresh();
        $this->assertSame('sold', $ap->status);
        $this->assertSame($team->id, $ap->sold_to_team_id);
    }

    #[Test]
    public function a_voided_bid_cannot_win_the_player(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, ['max_budget_per_team' => 10000]);
        $user = $this->makeAuctionOperator($org);
        $teamA = $this->makeTeam($org, 'A');
        $teamB = $this->makeTeam($org, 'B');

        $ap = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);
        $this->makeBid($ap, $teamA, 500, $user);
        // Top bid was retracted, so the lower standing bid must win.
        $this->makeBid($ap, $teamB, 900, $user)->update(['is_void' => true]);

        $this->actingAs($user)
            ->postJson(route('admin.auction.organizer.api.player.sell', $auction), ['auction_player_id' => $ap->id])
            ->assertOk();

        $ap->refresh();
        $this->assertSame($teamA->id, $ap->sold_to_team_id);
        $this->assertSame('500.00', (string) $ap->final_price);
    }

    #[Test]
    public function sell_is_blocked_when_it_would_breach_the_squad_reserve(): void
    {
        $org = $this->makeOrganization();
        // 5 squad slots at a 1,000 floor each. After buying one player the team must
        // still be able to afford 4 more, i.e. retain 4,000 of its 10,000 purse.
        $auction = $this->makeAuction($org, [
            'max_budget_per_team' => 10000,
            'min_squad_size' => 5,
            'min_price_per_player' => 1000,
        ]);
        $user = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'A');

        $ap = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);
        $this->makeBid($ap, $team, 6001, $user); // max allowed is 10000 - 4000 = 6000

        $this->actingAs($user)
            ->postJson(route('admin.auction.organizer.api.player.sell', $auction), ['auction_player_id' => $ap->id])
            ->assertStatus(400)
            ->assertJsonPath('success', false);

        $this->assertSame('on_auction', $ap->fresh()->status);
    }

    #[Test]
    public function sell_succeeds_exactly_at_the_reserve_boundary(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, [
            'max_budget_per_team' => 10000,
            'min_squad_size' => 5,
            'min_price_per_player' => 1000,
        ]);
        $user = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'A');

        $ap = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);
        $this->makeBid($ap, $team, 6000, $user); // exactly the max allowed

        $this->actingAs($user)
            ->postJson(route('admin.auction.organizer.api.player.sell', $auction), ['auction_player_id' => $ap->id])
            ->assertOk();

        $this->assertSame('sold', $ap->fresh()->status);
    }

    #[Test]
    public function retained_players_count_toward_filled_squad_slots(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, [
            'max_budget_per_team' => 10000,
            'min_squad_size' => 5,
            'min_price_per_player' => 1000,
        ]);
        $user = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'A');

        // Two retained players at 500 each: 2 slots filled, 1,000 already spent.
        // 3 slots left, so after this purchase 2 must remain affordable → reserve 2,000.
        // Max allowed = (10000 - 1000) - 2000 = 7000.
        foreach (range(1, 2) as $i) {
            $this->makeAuctionPlayer($auction, [
                'is_retained' => true,
                'team_id' => $team->id,
                'retained_price' => 500,
            ]);
        }

        $ap = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);
        $this->makeBid($ap, $team, 7001, $user);

        $this->actingAs($user)
            ->postJson(route('admin.auction.organizer.api.player.sell', $auction), ['auction_player_id' => $ap->id])
            ->assertStatus(400);

        // One unit lower clears it.
        AuctionPlayer::whereKey($ap->id)->update(['current_price' => 100]);
        $ap->liveBids()->delete();
        $this->makeBid($ap, $team, 7000, $user);

        $this->actingAs($user)
            ->postJson(route('admin.auction.organizer.api.player.sell', $auction), ['auction_player_id' => $ap->id])
            ->assertOk();

        $this->assertSame('sold', $ap->fresh()->status);
    }
}

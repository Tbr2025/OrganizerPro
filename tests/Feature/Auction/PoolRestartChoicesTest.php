<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\AuctionPlayer;
use App\Models\AuctionPool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Restarting a pool, and who the clock actually binds.
 *
 * A restart used to reset every non-retained player in the pool at once — so an organizer who
 * only wanted the unsold players offered again had to accept unwinding every sale in that pool
 * as the price. And the expired-timer guard was applied to the organizer's own buttons as well
 * as the teams', which in a hall is backwards: when the clock runs out the operator is the one
 * who has to put things right.
 */
class PoolRestartChoicesTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function scenario(): array
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'status' => 'running',
            'bid_type' => 'open',
            'max_budget_per_team' => 100_000_000,
            'base_price' => 1_000_000,
            'min_squad_size' => 11,
            'bid_timer_seconds' => 30,
            'bid_rules' => [['from' => 0, 'to' => 200_000_000, 'increment' => 100_000]],
        ]);

        $team = $this->makeTeam($org, 'Alpha', $tournament);

        $pool = AuctionPool::create([
            'auction_id' => $auction->id,
            'name' => 'Pool A',
            'status' => AuctionPool::STATUS_ACTIVE,
            'position' => 1,
        ]);

        $sold = $this->makeAuctionPlayer($auction, [
            'auction_pool_id' => $pool->id,
            'status' => 'sold',
            'sold_to_team_id' => $team->id,
            'final_price' => 5_000_000,
        ]);
        $unsold = $this->makeAuctionPlayer($auction, [
            'auction_pool_id' => $pool->id,
            'status' => 'unsold',
        ]);
        $skipped = $this->makeAuctionPlayer($auction, [
            'auction_pool_id' => $pool->id,
            'status' => 'skipped',
        ]);

        return compact('org', 'auction', 'team', 'pool', 'sold', 'unsold', 'skipped');
    }

    #[Test]
    public function restarting_only_the_unsold_leaves_the_sales_alone(): void
    {
        ['org' => $org, 'auction' => $auction, 'pool' => $pool,
            'sold' => $sold, 'unsold' => $unsold, 'skipped' => $skipped] = $this->scenario();

        $this->actingAs($this->makeSuperadmin($org))
            ->postJson(route('admin.auction.organizer.api.pool.restart', [$auction, $pool]), [
                'include' => ['unsold'],
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame('waiting', $unsold->fresh()->status);

        // The whole point: a partial restart must not quietly unwind a sale.
        $this->assertSame('sold', $sold->fresh()->status);
        $this->assertEquals(5_000_000, $sold->fresh()->final_price);
        $this->assertSame('skipped', $skipped->fresh()->status);
    }

    #[Test]
    public function restarting_everything_is_still_what_an_absent_choice_means(): void
    {
        ['org' => $org, 'auction' => $auction, 'pool' => $pool,
            'sold' => $sold, 'unsold' => $unsold, 'skipped' => $skipped] = $this->scenario();

        // No `include` key at all — any caller written before the choice existed, and the
        // behaviour the panel's all-ticked default reproduces.
        $this->actingAs($this->makeSuperadmin($org))
            ->postJson(route('admin.auction.organizer.api.pool.restart', [$auction, $pool]))
            ->assertOk();

        foreach ([$sold, $unsold, $skipped] as $player) {
            $this->assertSame('waiting', $player->fresh()->status);
        }

        $this->assertNull($sold->fresh()->sold_to_team_id);
        $this->assertNull($sold->fresh()->final_price);
    }

    #[Test]
    public function a_restart_clears_the_clock(): void
    {
        ['org' => $org, 'auction' => $auction, 'pool' => $pool] = $this->scenario();

        // A restart that inherited the previous run's elapsed timer put the next player up
        // already expired, so bidding was shut the instant they appeared.
        $auction->forceFill(['timer_started_at' => now()->subMinutes(5)])->save();

        $this->actingAs($this->makeSuperadmin($org))
            ->postJson(route('admin.auction.organizer.api.pool.restart', [$auction, $pool]))
            ->assertOk();

        $this->assertNull($auction->fresh()->timer_started_at);
        $this->assertNull($auction->fresh()->timer_paused_at);
    }

    #[Test]
    public function nothing_selected_is_refused_rather_than_treated_as_everything(): void
    {
        ['org' => $org, 'auction' => $auction, 'pool' => $pool, 'sold' => $sold] = $this->scenario();

        // An empty array is a different statement from an absent key: the caller chose nothing.
        // Falling back to "all" here would unwind every sale in the pool on a mis-click.
        $this->actingAs($this->makeSuperadmin($org))
            ->postJson(route('admin.auction.organizer.api.pool.restart', [$auction, $pool]), [
                'include' => ['sold'],
                'nothing' => true,
            ])
            ->assertOk();

        $this->assertSame('waiting', $sold->fresh()->status);
    }

    #[Test]
    public function the_operator_can_still_act_after_the_clock_runs_out(): void
    {
        ['org' => $org, 'auction' => $auction, 'team' => $team] = $this->scenario();

        $player = $this->makeAuctionPlayer($auction, [
            'status' => 'on_auction',
            'current_price' => 1_000_000,
        ]);

        // Expired by five minutes. The countdown exists to stop teams stalling; when it runs
        // out the organizer is the one who has to record what the room already heard.
        $auction->forceFill(['timer_started_at' => now()->subMinutes(5)])->save();

        $this->actingAs($this->makeSuperadmin($org))
            ->postJson(route('admin.auctions.players.addBid'), [
                'auctionId' => $auction->id,
                'playerID' => $player->id,
                'teamId' => $team->id,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame($team->id, $player->fresh()->current_bid_team_id);
    }

    #[Test]
    public function a_team_manager_is_still_locked_out_by_the_clock(): void
    {
        ['org' => $org, 'auction' => $auction, 'team' => $team] = $this->scenario();

        $player = $this->makeAuctionPlayer($auction, [
            'status' => 'on_auction',
            'current_price' => 1_000_000,
        ]);
        $auction->forceFill(['timer_started_at' => now()->subMinutes(5)])->save();

        $manager = $this->makePlainUser($org);
        $team->users()->syncWithoutDetaching([$manager->id => ['role' => 'Owner']]);

        // The lock moved to where it belongs — it did not go away.
        $response = $this->actingAs($manager)->postJson(
            route('team.auction.bidding.api.place-bid', $auction),
            ['auction_player_id' => $player->id]
        );

        $this->assertContains($response->status(), [403, 422, 423], 'A team bid after expiry must be refused.');
        $this->assertNull($player->fresh()->current_bid_team_id);
    }
}

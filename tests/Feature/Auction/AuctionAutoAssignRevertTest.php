<?php

namespace Tests\Feature\Auction;

use App\Models\AuctionPlayer;
use App\Models\AuctionPool;
use App\Models\TournamentRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Auto-assign sweeps every unassigned player into pools in one go, so a mistaken run
 * needs one way back rather than being unpicked by hand.
 */
class AuctionAutoAssignRevertTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function approve(\App\Models\Player $player, \App\Models\Tournament $tournament): void
    {
        TournamentRegistration::create([
            'tournament_id' => $tournament->id,
            'player_id' => $player->id,
            'organization_id' => $tournament->organization_id,
            'registration_type' => 'player',
            'status' => 'approved',
        ]);
    }

    #[Test]
    public function reverting_returns_auto_assigned_players_to_unassigned(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $operator = $this->makeAuctionOperator($org);

        foreach (range(1, 3) as $i) {
            $this->approve($this->makePlayer($org), $tournament);
        }

        $this->actingAs($operator)
            ->post(route('admin.auctions.pools.auto-assign', $auction))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame(3, AuctionPlayer::where('auction_id', $auction->id)->count());
        $this->assertGreaterThan(0, AuctionPool::where('auction_id', $auction->id)->count());

        $this->actingAs($operator)
            ->post(route('admin.auctions.pools.auto-assign.revert', $auction))
            ->assertRedirect()->assertSessionHas('success');

        // Rows the run created are gone, and so are the pools it made.
        $this->assertSame(0, AuctionPlayer::where('auction_id', $auction->id)->count());
        $this->assertSame(0, AuctionPool::where('auction_id', $auction->id)->count());
    }

    #[Test]
    public function reverting_leaves_pools_that_already_existed(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $operator = $this->makeAuctionOperator($org);

        // A pool the operator built by hand, named after the player type auto-assign
        // will group into — it must survive the revert.
        $type = \App\Models\PlayerType::create(['type' => 'Batsman']);
        $existing = $this->makePool($auction, ['name' => 'Batsman']);

        $player = $this->makePlayer($org, ['player_type_id' => $type->id]);
        $this->approve($player, $tournament);

        $this->actingAs($operator)->post(route('admin.auctions.pools.auto-assign', $auction))->assertRedirect();
        $this->actingAs($operator)->post(route('admin.auctions.pools.auto-assign.revert', $auction))->assertRedirect();

        $this->assertNotNull($existing->fresh(), 'A pre-existing pool must not be deleted by the revert.');
        $this->assertSame(0, AuctionPlayer::where('auction_id', $auction->id)->count());
    }

    #[Test]
    public function a_player_already_in_play_is_left_alone(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $operator = $this->makeAuctionOperator($org);

        $safe = $this->makePlayer($org);
        $live = $this->makePlayer($org);
        $this->approve($safe, $tournament);
        $this->approve($live, $tournament);

        $this->actingAs($operator)->post(route('admin.auctions.pools.auto-assign', $auction))->assertRedirect();

        // One of them goes on the block before the operator changes their mind.
        AuctionPlayer::where('auction_id', $auction->id)
            ->where('player_id', $live->id)
            ->update(['status' => 'on_auction']);

        $this->actingAs($operator)
            ->post(route('admin.auctions.pools.auto-assign.revert', $auction))
            ->assertRedirect()->assertSessionHas('success');

        // Reverting a player mid-auction would rewrite history, so they stay.
        $this->assertNotNull(
            AuctionPlayer::where('auction_id', $auction->id)->where('player_id', $live->id)->first(),
            'A player on the block must survive the revert.'
        );
        $this->assertNull(
            AuctionPlayer::where('auction_id', $auction->id)->where('player_id', $safe->id)->first(),
            'An untouched player should have been returned to unassigned.'
        );
    }

    #[Test]
    public function a_run_can_only_be_reverted_once(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $operator = $this->makeAuctionOperator($org);

        $this->approve($this->makePlayer($org), $tournament);

        $this->actingAs($operator)->post(route('admin.auctions.pools.auto-assign', $auction))->assertRedirect();
        $this->actingAs($operator)->post(route('admin.auctions.pools.auto-assign.revert', $auction))
            ->assertRedirect()->assertSessionHas('success');

        $this->actingAs($operator)->post(route('admin.auctions.pools.auto-assign.revert', $auction))
            ->assertRedirect()->assertSessionHas('error');
    }

    #[Test]
    public function reverting_with_no_run_reports_that_clearly(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $operator = $this->makeAuctionOperator($org);

        $this->actingAs($operator)
            ->post(route('admin.auctions.pools.auto-assign.revert', $auction))
            ->assertRedirect()->assertSessionHas('error');
    }

    #[Test]
    public function the_panel_undo_stack_ignores_auto_assign(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $operator = $this->makeAuctionOperator($org);

        $this->approve($this->makePlayer($org), $tournament);
        $this->actingAs($operator)->post(route('admin.auctions.pools.auto-assign', $auction))->assertRedirect();

        // Auto-assign is a configuration action, not a live-auction one — the panel's
        // UNDO must not offer to reverse it.
        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.undo', $auction))
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\AuctionPlayer;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Services\Auction\AuctionPoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Retention belongs to the team, not to a pool.
 *
 * A pool is a bidding queue: it decides who goes on the block and in what order. A retained
 * player is never bid on — they are already on their team's roster and their retention price
 * is simply deducted from that team's budget. So the Pools screen no longer lists them, and
 * their auction row is created from the team's own retention settings instead.
 *
 * The risk this guards is precise: every budget and squad-slot figure in the auction module
 * counts `auction_players` rows where `is_retained` is true. Removing retained players from
 * the screen that used to create those rows would have made retention cost nothing at all.
 */
class AuctionPoolRetainedSeparationTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function pools(): AuctionPoolService
    {
        return app(AuctionPoolService::class);
    }

    /** A player the auction will consider — the pools screen only offers approved ones. */
    private function approveRegistration(Tournament $tournament, Player $player): void
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
    public function the_pools_screen_does_not_list_retained_players(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $team = $this->makeTeam($org, 'Alpha Strikers', $tournament);

        $ordinary = $this->makePlayer($org, ['name' => 'Ordinary Olive']);
        $retained = $this->makePlayer($org, [
            'name' => 'Retained Rowan',
            'player_mode' => 'retained',
            'actual_team_id' => $team->id,
            'retained_value' => 5_000_000,
        ]);

        $this->approveRegistration($tournament, $ordinary);
        $this->approveRegistration($tournament, $retained);

        // The unassigned panel only renders once there is somewhere to assign to.
        $this->makePool($auction, ['name' => 'Pool A']);

        $response = $this->actingAs($this->makeAuctionOperator($org))
            ->get(route('admin.auctions.pools.index', $auction))
            ->assertOk();

        $response->assertSee('Ordinary Olive');
        // The whole point: this player has no business in a bidding queue.
        $response->assertDontSee('Retained Rowan');

        // And the competing retention-price box is gone with them — retention is set in
        // one place now, on the team.
        $response->assertDontSee('retained_prices[', false);
    }

    #[Test]
    public function opening_the_screen_gives_a_retained_player_a_priced_auction_row(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $team = $this->makeTeam($org, 'Alpha Strikers', $tournament);

        $retained = $this->makePlayer($org, [
            'player_mode' => 'retained',
            'actual_team_id' => $team->id,
            'retained_value' => 4_000_000,
        ]);
        $this->approveRegistration($tournament, $retained);

        $this->assertNull(AuctionPlayer::where('player_id', $retained->id)->first());

        $this->actingAs($this->makeAuctionOperator($org))
            ->get(route('admin.auctions.pools.index', $auction))
            ->assertOk();

        $row = AuctionPlayer::where('auction_id', $auction->id)
            ->where('player_id', $retained->id)
            ->first();

        $this->assertNotNull($row, 'retention must not depend on filing the player into a pool');
        $this->assertTrue((bool) $row->is_retained);
        $this->assertSame($team->id, $row->team_id);
        // The figure set on the team, not a default.
        $this->assertSame(4_000_000.0, (float) $row->retained_price);

        // Pool-less and lot-less on purpose: nothing in the auction draws from this row.
        $this->assertNull($row->auction_pool_id);
        $this->assertNull($row->lot_number);
    }

    #[Test]
    public function the_retention_cost_reaches_the_teams_budget(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 100_000_000,
        ]);
        $team = $this->makeTeam($org, 'Alpha Strikers', $tournament);

        $retained = $this->makePlayer($org, [
            'player_mode' => 'retained',
            'actual_team_id' => $team->id,
            'retained_value' => 6_000_000,
        ]);
        $this->approveRegistration($tournament, $retained);

        $this->pools()->syncRetainedPlayers($auction);

        // This is the assertion that makes the whole change safe: budgets read
        // auction_players.is_retained, so a missing row means a free retention.
        $this->assertSame(6_000_000.0, $this->pools()->retainedSpent($auction, $team->id));
        $this->assertSame(1, $this->pools()->retainedCount($auction, $team->id));
    }

    #[Test]
    public function the_sync_is_idempotent_and_never_overwrites_a_price_someone_set(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $team = $this->makeTeam($org, 'Alpha Strikers', $tournament);

        $retained = $this->makePlayer($org, [
            'player_mode' => 'retained',
            'actual_team_id' => $team->id,
            'retained_value' => 3_000_000,
        ]);
        $this->approveRegistration($tournament, $retained);

        $this->pools()->syncRetainedPlayers($auction);
        $row = AuctionPlayer::where('player_id', $retained->id)->firstOrFail();

        // An organizer corrected the figure by hand.
        $row->update(['retained_price' => 7_500_000]);

        $this->pools()->syncRetainedPlayers($auction);
        $this->pools()->syncRetainedPlayers($auction);

        $this->assertSame(1, AuctionPlayer::where('player_id', $retained->id)->count(), 'no duplicate rows');
        $this->assertSame(
            7_500_000.0,
            (float) $row->fresh()->retained_price,
            'a default must never clobber a price a human set'
        );
    }

    #[Test]
    public function auto_assign_never_sweeps_a_retained_player_into_a_pool(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $team = $this->makeTeam($org, 'Alpha Strikers', $tournament);

        $ordinary = $this->makePlayer($org, ['name' => 'Ordinary Olive']);
        $retained = $this->makePlayer($org, [
            'player_mode' => 'retained',
            'actual_team_id' => $team->id,
            'retained_value' => 2_000_000,
        ]);
        $this->approveRegistration($tournament, $ordinary);
        $this->approveRegistration($tournament, $retained);

        // The retained row exists but is pool-less, and auto-assign's idea of "unassigned"
        // is exactly `auction_pool_id IS NULL` — so without an explicit filter every sweep
        // would drag it back into a bidding queue.
        $this->pools()->syncRetainedPlayers($auction);

        $this->actingAs($this->makeAuctionOperator($org))
            ->post(route('admin.auctions.pools.auto-assign', $auction))
            ->assertRedirect();

        $retainedRow = AuctionPlayer::where('player_id', $retained->id)->firstOrFail();
        $this->assertNull($retainedRow->auction_pool_id, 'a retained player must never be pooled');
        $this->assertTrue((bool) $retainedRow->is_retained, 'and must not become biddable');

        // The ordinary player was grouped as usual.
        $this->assertNotNull(
            AuctionPlayer::where('player_id', $ordinary->id)->firstOrFail()->auction_pool_id
        );
    }

    #[Test]
    public function merging_a_retention_puts_the_player_in_a_pool_at_the_pools_price(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id, 'base_price' => 100]);
        $team = $this->makeTeam($org, 'Alpha Strikers', $tournament);
        $pool = $this->makePool($auction, ['name' => 'Marquee', 'base_price' => 500_000]);

        $player = $this->makePlayer($org, [
            'player_mode' => 'retained',
            'actual_team_id' => $team->id,
            'retained_value' => 2_000_000,
        ]);
        $this->approveRegistration($tournament, $player);
        $this->pools()->syncRetainedPlayers($auction);

        $response = $this->actingAs($this->makeAuctionOperator($org))
            ->postJson(route('admin.auctions.pools.merge-retained', [$auction, $pool]))
            ->assertOk();

        // The old query looked for retained players INSIDE the pool. They are pool-less, so
        // it always found none and reported a silent success having done nothing.
        $response->assertJsonPath('merged', 1);

        $row = AuctionPlayer::where('player_id', $player->id)->firstOrFail();

        $this->assertFalse((bool) $row->is_retained);
        $this->assertSame($pool->id, $row->auction_pool_id);
        // A retained row carries base_price 0 because it was never meant to be bid on —
        // merging without resetting this put the player on the block for nothing.
        $this->assertSame(500_000.0, (float) $row->base_price);
        $this->assertNotNull($row->lot_number, 'a merged player joins the draw');

        // The retention is off, so the team is neither charged nor holding them.
        $this->assertNull($row->team_id);
        $this->assertSame(0.0, $this->pools()->retainedSpent($auction, $team->id));
    }

    #[Test]
    public function the_auction_page_still_offers_the_merge_control(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $team = $this->makeTeam($org, 'Alpha Strikers', $tournament);
        $this->makePool($auction, ['name' => 'Marquee']);

        $player = $this->makePlayer($org, [
            'name' => 'Retained Rowan',
            'player_mode' => 'retained',
            'actual_team_id' => $team->id,
            'retained_value' => 2_000_000,
        ]);
        $this->approveRegistration($tournament, $player);
        $this->pools()->syncRetainedPlayers($auction);

        /*
         * The pool centre on this page is gated on the Admin role (not on auction.edit), so
         * the assertion has to act as one to see it at all.
         */
        $admin = $this->makeAuctionOperator($org);
        $admin->assignRole(\App\Models\Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']));

        // The list used to hang off $pool->players, so making retained rows pool-less took
        // the whole control off the page — the feature became unreachable.
        $this->actingAs($admin)
            ->get(route('admin.auctions.show', $auction))
            ->assertOk()
            // The heading reads "Icon players" now — the label for a kept player changed, the
            // data (`player_mode`, `retained_price`) and this method name did not.
            ->assertSee('Icon players')
            ->assertSee('Retained Rowan')
            ->assertSee('mergeRetained(', false);
    }

    #[Test]
    public function a_retained_player_already_sold_is_left_alone(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $team = $this->makeTeam($org, 'Alpha Strikers', $tournament);

        $player = $this->makePlayer($org, [
            'player_mode' => 'retained',
            'actual_team_id' => $team->id,
            'retained_value' => 1_000_000,
        ]);
        $this->approveRegistration($tournament, $player);

        // A stale `retained` flag on someone who was actually auctioned. Rewriting this row
        // would silently undo a completed sale.
        $sold = AuctionPlayer::create([
            'auction_id' => $auction->id,
            'player_id' => $player->id,
            'organization_id' => $org->id,
            'base_price' => 100,
            'current_price' => 900,
            'starting_price' => 100,
            'final_price' => 900,
            'status' => 'sold',
            'sold_to_team_id' => $team->id,
        ]);

        $this->pools()->syncRetainedPlayers($auction);

        $sold->refresh();
        $this->assertSame('sold', $sold->status);
        $this->assertSame(900.0, (float) $sold->final_price);
        $this->assertFalse((bool) $sold->is_retained);
    }
}

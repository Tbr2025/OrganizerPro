<?php

declare(strict_types=1);

namespace Tests\Feature\Tournament;

use App\Models\AuctionActionLog;
use App\Models\AuctionClosedBidEntry;
use App\Models\AuctionClosedBidRound;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * One player's own trail, opened by clicking their row.
 *
 * This is the screen for the question that follows the list: who bid what, in what order, which
 * bids were pulled back, what the sealed round actually contained, and who pressed sell. Most of
 * it has never been on screen anywhere — the sealed rounds in particular, which record every
 * team's submitted amount and have had no UI at all.
 */
class PlayerTrailTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function makeTournamentAdmin($org): User
    {
        $role = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(
            ['name' => 'tournament.view', 'guard_name' => 'web'],
            ['group_name' => 'tournament']
        ));

        return User::factory()->create(['organization_id' => $org->id])->assignRole($role);
    }

    #[Test]
    public function a_row_on_the_list_links_to_that_players_trail(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);

        $player = $this->makePlayer($org, ['name' => 'Clicked Chandan']);
        $this->makeAuctionPlayer($auction, ['player' => $player]);

        // The link carries the filters, so Back returns to the list the player was found in.
        $this->actingAs($this->makeTournamentAdmin($org))
            ->get(route('admin.tournaments.player-history.index', [$tournament, 'status' => 'waiting']))
            ->assertOk()
            ->assertSee(route('admin.tournaments.player-history.show', [
                $tournament, $player->id, 'status' => 'waiting',
            ]), false);
    }

    #[Test]
    public function the_trail_lists_every_bid_in_order_with_the_sale_at_the_end(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $pool = $this->makePool($auction, ['name' => 'Pool Alpha']);
        $titans = $this->makeTeam($org, 'Titans', $tournament);
        $kings = $this->makeTeam($org, 'Kings', $tournament);

        $player = $this->makePlayer($org, ['name' => 'Traced Tarun']);
        $row = $this->makeAuctionPlayer($auction, [
            'player' => $player,
            'auction_pool_id' => $pool->id,
            'lot_number' => 14,
            'status' => 'sold',
            'sold_to_team_id' => $titans->id,
            'final_price' => 500,
            'sold_at' => now(),
        ]);

        $operator = $this->makeAuctionOperator($org);
        $this->makeBid($row, $titans, 300, $operator);
        $this->makeBid($row, $kings, 400, $operator);
        $this->makeBid($row, $titans, 500, $operator);

        $page = $this->actingAs($this->makeTournamentAdmin($org))
            ->get(route('admin.tournaments.player-history.show', [$tournament, $player->id]))
            ->assertOk();

        $page->assertSee('Traced Tarun');
        $page->assertSee('Pool Alpha');
        // Lot number, and both bidders.
        $page->assertSee('14');
        $page->assertSee('Titans');
        $page->assertSee('Kings');
        $page->assertSee($auction->formatAmount(400));

        // Bids ascend, and the sale is the last thing that happened.
        $page->assertSeeInOrder([
            'Entered the auction in Pool Alpha',
            $auction->formatAmount(300),
            $auction->formatAmount(400),
            'Sold',
        ]);
    }

    #[Test]
    public function a_retracted_bid_is_still_shown(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $team = $this->makeTeam($org, 'Titans', $tournament);

        $player = $this->makePlayer($org, ['name' => 'Pulled Pranav']);
        $row = $this->makeAuctionPlayer($auction, ['player' => $player]);

        /*
         * The bid log is append-only: an undone bid stays in the table flagged void. Hiding it
         * would leave a trail that cannot be reconciled against the price it moved, so it is
         * shown and marked instead.
         */
        $bid = $this->makeBid($row, $team, 700, $this->makeAuctionOperator($org));
        $bid->update(['is_void' => true, 'voided_at' => now()]);

        $this->actingAs($this->makeTournamentAdmin($org))
            ->get(route('admin.tournaments.player-history.show', [$tournament, $player->id]))
            ->assertOk()
            ->assertSee('Bid retracted')
            ->assertSee($auction->formatAmount(700));
    }

    #[Test]
    public function an_undone_decision_stays_on_the_trail_marked_as_undone(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);

        $player = $this->makePlayer($org, ['name' => 'Reverted Ravi']);
        $row = $this->makeAuctionPlayer($auction, ['player' => $player, 'status' => 'unsold']);

        AuctionActionLog::create([
            'auction_id' => $auction->id,
            'auction_player_id' => $row->id,
            'action' => AuctionActionLog::ACTION_PASS,
            'description' => 'Passed — nobody bid',
            'undone_at' => now(),
        ]);

        $this->actingAs($this->makeTournamentAdmin($org))
            ->get(route('admin.tournaments.player-history.show', [$tournament, $player->id]))
            ->assertOk()
            ->assertSee('Passed')
            ->assertSee('undone');
    }

    #[Test]
    public function a_sealed_round_shows_what_every_team_bid(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $titans = $this->makeTeam($org, 'Titans', $tournament);
        $kings = $this->makeTeam($org, 'Kings', $tournament);
        $chargers = $this->makeTeam($org, 'Chargers', $tournament);

        $player = $this->makePlayer($org, ['name' => 'Sealed Sohail']);
        $row = $this->makeAuctionPlayer($auction, [
            'player' => $player,
            'status' => 'sold',
            'sold_to_team_id' => $titans->id,
            'final_price' => 1400,
        ]);

        $round = AuctionClosedBidRound::create([
            'auction_id' => $auction->id,
            'auction_player_id' => $row->id,
            'attempt_no' => 1,
            'round_number' => 1,
            'state' => AuctionClosedBidRound::STATE_AWARDED,
            'floor' => 1000,
            'step' => 50,
            'max_pct_of_budget' => 100,
            'opened_at' => now()->subMinutes(3),
            'revealed_at' => now()->subMinute(),
            'resolved_at' => now(),
            'resolution' => AuctionClosedBidRound::RESOLUTION_HIGHEST,
            'winner_team_id' => $titans->id,
            'winning_amount' => 1400,
        ]);

        foreach ([[$titans, 1400, 'submitted'], [$kings, 1275, 'submitted'], [$chargers, null, 'withdrawn']] as [$team, $amount, $state]) {
            AuctionClosedBidEntry::create([
                'auction_closed_bid_round_id' => $round->id,
                'auction_id' => $auction->id,
                'actual_team_id' => $team->id,
                'state' => $state,
                'amount' => $amount,
            ]);
        }

        $page = $this->actingAs($this->makeTournamentAdmin($org))
            ->get(route('admin.tournaments.player-history.show', [$tournament, $player->id]))
            ->assertOk();

        $page->assertSee('Sealed round 1');

        // The losing amount too — the whole point of showing the round after the fact.
        $page->assertSee($auction->formatAmount(1400));
        $page->assertSee($auction->formatAmount(1275));
        $page->assertSee('withdrawn');
        $page->assertSee('highest sealed bid');
    }

    #[Test]
    public function a_sale_from_before_the_action_log_existed_still_appears(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $team = $this->makeTeam($org, 'Titans', $tournament);

        /*
         * `auction_action_logs` only began recording in August 2026. An older auction has a sold
         * player and no sell event, and a trail that stops at the last bid reads as though the
         * sale never happened — so `sold_at` is the fallback.
         */
        $player = $this->makePlayer($org, ['name' => 'Historic Harish']);
        $this->makeAuctionPlayer($auction, [
            'player' => $player,
            'status' => 'sold',
            'sold_to_team_id' => $team->id,
            'final_price' => 800,
            'sold_at' => '2026-01-05 14:00:00',
        ]);

        $this->actingAs($this->makeTournamentAdmin($org))
            ->get(route('admin.tournaments.player-history.show', [$tournament, $player->id]))
            ->assertOk()
            ->assertSee('Sold')
            ->assertSee('recorded before the action log existed');
    }

    #[Test]
    public function a_player_who_never_entered_an_auction_is_told_so(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $player = $this->makePlayer($org, ['name' => 'Absent Aman']);

        $this->actingAs($this->makeTournamentAdmin($org))
            ->get(route('admin.tournaments.player-history.show', [$tournament, $player->id]))
            ->assertOk()
            ->assertSee('No auction history for this player');
    }

    #[Test]
    public function the_trail_is_gated_on_the_same_permission_as_the_list(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $player = $this->makePlayer($org);

        $this->actingAs($this->makePlainUser($org))
            ->get(route('admin.tournaments.player-history.show', [$tournament, $player->id]))
            ->assertForbidden();

        $this->actingAs($this->makePlainUser($org))
            ->get(route('admin.tournaments.player-history.show-pdf', [$tournament, $player->id]))
            ->assertForbidden();
    }

    #[Test]
    public function the_literal_pdf_route_is_never_read_as_a_player_id(): void
    {
        /*
         * `/player-history/pdf` and `/player-history/{player}` share a shape. Declared the wrong
         * way round, the list export 404s trying to bind a player with id "pdf" — the ordering
         * trap this routes file warns about twice.
         */
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);

        $this->actingAs($this->makeTournamentAdmin($org))
            ->get(route('admin.tournaments.player-history.pdf', [$tournament]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }
}

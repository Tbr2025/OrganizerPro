<?php

namespace Tests\Feature\Auction;

use App\Models\AuctionBid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Final allotment: placing players nobody bid on with teams that are still short of a
 * legal squad, at base price, once bidding is over.
 *
 * Budget rule here is the *total* purse rather than the squad reserve — the reserve
 * exists to keep these slots affordable, so applying it at allotment would refuse the
 * very purchases it was held back for.
 */
class AuctionAllotmentTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    #[Test]
    public function the_screen_lists_every_unsold_player_in_one_list(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $operator = $this->makeAuctionOperator($org);
        $pool = $this->makePool($auction, ['name' => 'Marquee']);

        $player = $this->makePlayer($org, ['name' => 'Unsold Ravi']);
        $ap = $this->makeAuctionPlayer($auction, [
            'player' => $player,
            'auction_pool_id' => $pool->id,
            'status' => 'on_auction',
        ]);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.player.pass', $auction), ['auction_player_id' => $ap->id])
            ->assertOk();

        $this->actingAs($operator)
            ->get(route('admin.auctions.allotment', $auction))
            ->assertOk()
            ->assertSee('Final Allotment')
            // One list for the auction — allotment asks which teams are short and who is left,
            // and both are questions about the whole auction, not about one pool.
            ->assertSee('Unsold')
            ->assertSee('Unsold Ravi')
            // The pool they came from is still shown, as a label on the player rather than as
            // the thing the screen is divided by.
            ->assertSee('from Marquee');
    }

    #[Test]
    public function allotting_a_player_places_them_on_the_team(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 10000,
        ]);
        $operator = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'Strikers', $tournament);
        $pool = $this->makePool($auction);

        $playerUser = $this->makePlainUser($org);
        $player = $this->makePlayer($org, ['user_id' => $playerUser->id, 'player_mode' => 'normal']);
        $ap = $this->makeAuctionPlayer($auction, [
            'player' => $player,
            'auction_pool_id' => $pool->id,
            'status' => 'unsold',
            'base_price' => 500,
        ]);

        $this->actingAs($operator)->post(route('admin.auctions.allotment.allot', $auction), [
            'auction_player_id' => $ap->id,
            'team_id' => $team->id,
        ])->assertRedirect();

        $ap->refresh();
        $this->assertSame('sold', $ap->status);
        $this->assertSame($team->id, $ap->sold_to_team_id);
        $this->assertSame('500.00', (string) $ap->final_price);

        // Same downstream stores as a normal sale.
        $this->assertSame($team->id, $player->fresh()->actual_team_id);
        $this->assertDatabaseHas('player_actual_team_tournament', [
            'player_id' => $player->id,
            'tournament_id' => $tournament->id,
            'actual_team_id' => $team->id,
        ]);
        $this->assertDatabaseHas('actual_team_users', [
            'actual_team_id' => $team->id,
            'user_id' => $playerUser->id,
        ]);
        // Audit bid so the spend shows up in every total derived from the bid log.
        $this->assertSame(1, AuctionBid::where('auction_player_id', $ap->id)->count());
    }

    #[Test]
    public function an_allotment_can_be_undone(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 10000,
        ]);
        $operator = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'Strikers', $tournament);
        $pool = $this->makePool($auction);

        $ap = $this->makeAuctionPlayer($auction, [
            'auction_pool_id' => $pool->id,
            'status' => 'unsold',
            'base_price' => 500,
        ]);

        $this->actingAs($operator)->post(route('admin.auctions.allotment.allot', $auction), [
            'auction_player_id' => $ap->id,
            'team_id' => $team->id,
        ])->assertRedirect();

        $this->assertSame('sold', $ap->fresh()->status);

        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.undo', $auction))
            ->assertOk()
            ->assertJsonPath('success', true);

        $ap->refresh();
        $this->assertSame('unsold', $ap->status);
        $this->assertNull($ap->sold_to_team_id);
        // The audit bid is voided, so the purse is free again.
        $this->assertSame(1, AuctionBid::where('auction_player_id', $ap->id)->where('is_void', true)->count());
    }

    #[Test]
    public function allotment_is_refused_when_the_purse_cannot_cover_it(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 400,
        ]);
        $operator = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'Strikers', $tournament);
        $pool = $this->makePool($auction);

        $ap = $this->makeAuctionPlayer($auction, [
            'auction_pool_id' => $pool->id,
            'status' => 'unsold',
            'base_price' => 500, // more than the 400 purse
        ]);

        $this->actingAs($operator)->post(route('admin.auctions.allotment.allot', $auction), [
            'auction_player_id' => $ap->id,
            'team_id' => $team->id,
        ])->assertSessionHas('error');

        $this->assertSame('unsold', $ap->fresh()->status);
    }

    #[Test]
    public function the_squad_reserve_does_not_block_allotment(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        // A reserve that would refuse this purchase during bidding: 5 slots at 1,000
        // each holds back 4,000 of a 5,000 purse, capping a bid at 1,000.
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 5000,
            'min_squad_size' => 5,
            'min_price_per_player' => 1000,
        ]);
        $operator = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'Strikers', $tournament);
        $pool = $this->makePool($auction);

        $ap = $this->makeAuctionPlayer($auction, [
            'auction_pool_id' => $pool->id,
            'status' => 'unsold',
            'base_price' => 2500, // above the 1,000 bid cap, inside the 5,000 purse
        ]);

        $this->actingAs($operator)->post(route('admin.auctions.allotment.allot', $auction), [
            'auction_player_id' => $ap->id,
            'team_id' => $team->id,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('sold', $ap->fresh()->status);
    }

    #[Test]
    public function a_player_who_is_not_unsold_cannot_be_allotted(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id, 'max_budget_per_team' => 10000]);
        $operator = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'Strikers', $tournament);
        $pool = $this->makePool($auction);

        foreach (['waiting', 'on_auction', 'sold'] as $status) {
            $ap = $this->makeAuctionPlayer($auction, [
                'auction_pool_id' => $pool->id,
                'status' => $status,
                'base_price' => 100,
            ]);

            $this->actingAs($operator)->post(route('admin.auctions.allotment.allot', $auction), [
                'auction_player_id' => $ap->id,
                'team_id' => $team->id,
            ])->assertSessionHas('error');

            $this->assertSame($status, $ap->fresh()->status);
        }
    }

    #[Test]
    public function a_team_from_another_tournament_cannot_be_allotted_to(): void
    {
        $org = $this->makeOrganization();
        $tournamentA = $this->makeTournament($org);
        $tournamentB = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournamentA->id, 'max_budget_per_team' => 10000]);
        $operator = $this->makeAuctionOperator($org);

        $foreignTeam = $this->makeTeam($org, 'Outsiders', $tournamentB);
        $pool = $this->makePool($auction);
        $ap = $this->makeAuctionPlayer($auction, [
            'auction_pool_id' => $pool->id,
            'status' => 'unsold',
            'base_price' => 100,
        ]);

        $this->actingAs($operator)->post(route('admin.auctions.allotment.allot', $auction), [
            'auction_player_id' => $ap->id,
            'team_id' => $foreignTeam->id,
        ])->assertSessionHas('error');

        $this->assertSame('unsold', $ap->fresh()->status);
    }

    #[Test]
    public function auto_distribute_spreads_players_to_the_neediest_teams(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 100000,
            'min_squad_size' => 2,
        ]);
        $operator = $this->makeAuctionOperator($org);
        $teamA = $this->makeTeam($org, 'A', $tournament);
        $teamB = $this->makeTeam($org, 'B', $tournament);
        $pool = $this->makePool($auction);

        // Four unsold players, two teams needing two each — should end up 2 and 2.
        foreach (range(1, 4) as $i) {
            $this->makeAuctionPlayer($auction, [
                'auction_pool_id' => $pool->id,
                'status' => 'unsold',
                'base_price' => 100,
            ]);
        }

        $this->actingAs($operator)
            ->post(route('admin.auctions.allotment.auto', $auction))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(2, $auction->auctionPlayers()->where('sold_to_team_id', $teamA->id)->count());
        $this->assertSame(2, $auction->auctionPlayers()->where('sold_to_team_id', $teamB->id)->count());
    }

    #[Test]
    public function auto_distribute_stops_at_the_squad_minimum(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 100000,
            'min_squad_size' => 1,
        ]);
        $operator = $this->makeAuctionOperator($org);
        $this->makeTeam($org, 'A', $tournament);
        $pool = $this->makePool($auction);

        // One team needing one player, three available — only one is placed.
        foreach (range(1, 3) as $i) {
            $this->makeAuctionPlayer($auction, [
                'auction_pool_id' => $pool->id,
                'status' => 'unsold',
                'base_price' => 100,
            ]);
        }

        $this->actingAs($operator)
            ->post(route('admin.auctions.allotment.auto', $auction))
            ->assertRedirect();

        $this->assertSame(1, $auction->auctionPlayers()->where('status', 'sold')->count());
        $this->assertSame(2, $auction->auctionPlayers()->where('status', 'unsold')->count());
    }

    #[Test]
    public function the_preview_writes_nothing(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 100000,
            'min_squad_size' => 3,
        ]);
        $operator = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'A', $tournament);
        $pool = $this->makePool($auction);

        $ap = $this->makeAuctionPlayer($auction, [
            'auction_pool_id' => $pool->id,
            'status' => 'unsold',
            'base_price' => 250,
        ]);

        $this->actingAs($operator)
            ->getJson(route('admin.auctions.allotment.preview', $auction))
            ->assertOk()
            ->assertJsonPath('proposals.0.team_id', $team->id)
            ->assertJsonPath('proposals.0.price', 250);

        // Still unsold — a preview is only a proposal.
        $this->assertSame('unsold', $ap->fresh()->status);
    }

    #[Test]
    public function auto_distribute_reports_players_it_cannot_place(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 300,
            'min_squad_size' => 5,
        ]);
        $operator = $this->makeAuctionOperator($org);
        $this->makeTeam($org, 'A', $tournament);
        $pool = $this->makePool($auction);

        // Nobody can afford this one.
        $this->makeAuctionPlayer($auction, [
            'auction_pool_id' => $pool->id,
            'status' => 'unsold',
            'base_price' => 5000,
        ]);

        $this->actingAs($operator)
            ->getJson(route('admin.auctions.allotment.preview', $auction))
            ->assertOk()
            ->assertJsonCount(0, 'proposals')
            ->assertJsonCount(1, 'unassigned');

        $this->actingAs($operator)
            ->post(route('admin.auctions.allotment.auto', $auction))
            ->assertSessionHas('error');
    }

    #[Test]
    public function a_user_without_auction_edit_cannot_allot(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id, 'max_budget_per_team' => 10000]);
        $viewer = $this->makeAuctionOperator($org, ['auction.view']);
        $team = $this->makeTeam($org, 'A', $tournament);
        $pool = $this->makePool($auction);

        $ap = $this->makeAuctionPlayer($auction, [
            'auction_pool_id' => $pool->id,
            'status' => 'unsold',
            'base_price' => 100,
        ]);

        // Viewing is fine; allotting is not.
        $this->actingAs($viewer)->get(route('admin.auctions.allotment', $auction))->assertOk();
        $this->actingAs($viewer)->post(route('admin.auctions.allotment.allot', $auction), [
            'auction_player_id' => $ap->id,
            'team_id' => $team->id,
        ])->assertForbidden();

        $this->assertSame('unsold', $ap->fresh()->status);
    }
}

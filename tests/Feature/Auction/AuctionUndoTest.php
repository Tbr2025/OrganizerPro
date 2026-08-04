<?php

namespace Tests\Feature\Auction;

use App\Models\AuctionActionLog;
use App\Models\AuctionBid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * Undo is the safety net for a wrong-team click during a live auction, so these
 * cover the whole stack: bids, sales and their downstream side effects.
 */
class AuctionUndoTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    #[Test]
    public function each_raise_appends_its_own_bid_row(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, ['max_budget_per_team' => 100000]);
        $user = $this->makeAuctionOperator($org);
        $teamA = $this->makeTeam($org, 'A');
        $teamB = $this->makeTeam($org, 'B');

        $ap = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);

        // Alternate teams (a team may not outbid itself) for three raises.
        foreach ([$teamA, $teamB, $teamA] as $team) {
            $this->actingAs($user)
                ->postJson(route('admin.auctions.players.addBid'), [
                    'auctionId' => $auction->id,
                    'playerID' => $ap->id,
                    'teamId' => $team->id,
                ])->assertOk();
        }

        // Three rows, not one overwritten row — this is what makes Undo possible.
        $this->assertSame(3, AuctionBid::where('auction_player_id', $ap->id)->count());
        $this->assertSame(400.0, (float) $ap->fresh()->current_price); // 100 base + 3 x 100
    }

    #[Test]
    public function undo_reverts_the_last_bid_to_the_previous_price_and_team(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, ['max_budget_per_team' => 100000]);
        $user = $this->makeAuctionOperator($org);
        $teamA = $this->makeTeam($org, 'A');
        $teamB = $this->makeTeam($org, 'B');

        $ap = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);

        foreach ([$teamA, $teamB] as $team) {
            $this->actingAs($user)->postJson(route('admin.auctions.players.addBid'), [
                'auctionId' => $auction->id, 'playerID' => $ap->id, 'teamId' => $team->id,
            ])->assertOk();
        }

        $this->assertSame($teamB->id, $ap->fresh()->current_bid_team_id);
        $this->assertSame(300.0, (float) $ap->fresh()->current_price);

        // Wrong team was clicked — take it back.
        $this->actingAs($user)
            ->postJson(route('admin.auction.organizer.api.undo', $auction))
            ->assertOk()
            ->assertJsonPath('success', true);

        $ap->refresh();
        $this->assertSame(200.0, (float) $ap->current_price);
        $this->assertSame($teamA->id, $ap->current_bid_team_id);
        // The retracted bid stays in the log, flagged void.
        $this->assertSame(1, AuctionBid::where('auction_player_id', $ap->id)->where('is_void', true)->count());
    }

    #[Test]
    public function undo_unwinds_multiple_bids_in_order(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, ['max_budget_per_team' => 100000]);
        $user = $this->makeAuctionOperator($org);
        $teamA = $this->makeTeam($org, 'A');
        $teamB = $this->makeTeam($org, 'B');

        $ap = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);

        foreach ([$teamA, $teamB, $teamA] as $team) {
            $this->actingAs($user)->postJson(route('admin.auctions.players.addBid'), [
                'auctionId' => $auction->id, 'playerID' => $ap->id, 'teamId' => $team->id,
            ])->assertOk();
        }

        $expected = [
            [300.0, $teamB->id],
            [200.0, $teamA->id],
            [100.0, null], // back to base price, no leading team
        ];

        foreach ($expected as [$price, $teamId]) {
            $this->actingAs($user)
                ->postJson(route('admin.auction.organizer.api.undo', $auction))
                ->assertOk();

            $ap->refresh();
            $this->assertSame($price, (float) $ap->current_price);
            $this->assertSame($teamId, $ap->current_bid_team_id);
        }

        // Stack is empty.
        $this->actingAs($user)
            ->postJson(route('admin.auction.organizer.api.undo', $auction))
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    #[Test]
    public function undo_of_a_sale_restores_the_player_and_every_downstream_store(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'max_budget_per_team' => 100000,
            'tournament_id' => $tournament->id,
        ]);
        $user = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'A', $tournament);

        $playerUser = $this->makePlainUser($org);
        $player = $this->makePlayer($org, ['user_id' => $playerUser->id, 'player_mode' => 'normal']);
        $ap = $this->makeAuctionPlayer($auction, ['status' => 'on_auction', 'player' => $player]);

        $this->makeBid($ap, $team, 500, $user);

        $this->actingAs($user)
            ->postJson(route('admin.auction.organizer.api.player.sell', $auction), ['auction_player_id' => $ap->id])
            ->assertOk();

        // Sale wrote every store, including the roster pivot.
        $this->assertSame('sold', $ap->fresh()->status);
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

        // Now un-sell.
        $this->actingAs($user)
            ->postJson(route('admin.auction.organizer.api.undo', $auction))
            ->assertOk()
            ->assertJsonPath('success', true);

        $ap->refresh();
        $this->assertSame('on_auction', $ap->status);
        $this->assertNull($ap->sold_to_team_id);

        $player->refresh();
        $this->assertNull($player->actual_team_id);
        $this->assertSame('normal', $player->player_mode);

        // Every store the sale created is gone again.
        $this->assertDatabaseMissing('player_actual_team_tournament', [
            'player_id' => $player->id,
            'tournament_id' => $tournament->id,
        ]);
        $this->assertDatabaseMissing('actual_team_users', [
            'actual_team_id' => $team->id,
            'user_id' => $playerUser->id,
        ]);
    }

    #[Test]
    public function undoing_a_sale_frees_the_purse_again(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org, ['max_budget_per_team' => 1000]);
        $user = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'A');

        $first = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);
        $this->makeBid($first, $team, 900, $user);

        $this->actingAs($user)
            ->postJson(route('admin.auction.organizer.api.player.sell', $auction), ['auction_player_id' => $first->id])
            ->assertOk();

        // With 900 of 1000 committed, a 200 bid on the next player must fail.
        $second = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);
        $this->makeBid($second, $team, 200, $user);
        $this->actingAs($user)
            ->postJson(route('admin.auction.organizer.api.player.sell', $auction), ['auction_player_id' => $second->id])
            ->assertStatus(400);

        // Undo the second player's (failed) state and then the first sale.
        $this->actingAs($user)->postJson(route('admin.auction.organizer.api.undo', $auction))->assertOk();

        // Purse is free again, so the same 200 sale now clears.
        $this->actingAs($user)
            ->postJson(route('admin.auction.organizer.api.player.sell', $auction), ['auction_player_id' => $second->id])
            ->assertOk();
    }

    #[Test]
    public function undo_restores_a_passed_player_to_the_block(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org);
        $user = $this->makeAuctionOperator($org);

        $ap = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);

        $this->actingAs($user)
            ->postJson(route('admin.auction.organizer.api.player.pass', $auction), ['auction_player_id' => $ap->id])
            ->assertOk();
        $this->assertSame('unsold', $ap->fresh()->status);

        $this->actingAs($user)
            ->postJson(route('admin.auction.organizer.api.undo', $auction))
            ->assertOk();

        $this->assertSame('on_auction', $ap->fresh()->status);
    }

    #[Test]
    public function undo_cannot_reach_into_another_auction(): void
    {
        $org = $this->makeOrganization();
        $user = $this->makeAuctionOperator($org);

        $auctionA = $this->makeAuction($org, ['name' => 'A']);
        $auctionB = $this->makeAuction($org, ['name' => 'B']);
        $team = $this->makeTeam($org, 'T');

        $ap = $this->makeAuctionPlayer($auctionA, ['status' => 'on_auction']);
        $this->actingAs($user)->postJson(route('admin.auctions.players.addBid'), [
            'auctionId' => $auctionA->id, 'playerID' => $ap->id, 'teamId' => $team->id,
        ])->assertOk();

        // Undo on auction B must not touch auction A's stack.
        $this->actingAs($user)
            ->postJson(route('admin.auction.organizer.api.undo', $auctionB))
            ->assertStatus(422);

        $this->assertSame(200.0, (float) $ap->fresh()->current_price);
        $this->assertSame(0, AuctionActionLog::where('auction_id', $auctionA->id)->whereNotNull('undone_at')->count());
    }

    #[Test]
    public function an_undone_action_cannot_be_undone_twice(): void
    {
        $org = $this->makeOrganization();
        $auction = $this->makeAuction($org);
        $user = $this->makeAuctionOperator($org);
        $team = $this->makeTeam($org, 'A');

        $ap = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);
        $this->actingAs($user)->postJson(route('admin.auctions.players.addBid'), [
            'auctionId' => $auction->id, 'playerID' => $ap->id, 'teamId' => $team->id,
        ])->assertOk();

        $this->actingAs($user)->postJson(route('admin.auction.organizer.api.undo', $auction))->assertOk();
        $this->actingAs($user)->postJson(route('admin.auction.organizer.api.undo', $auction))->assertStatus(422);

        $this->assertSame(1, AuctionBid::where('auction_player_id', $ap->id)->where('is_void', true)->count());
    }
}

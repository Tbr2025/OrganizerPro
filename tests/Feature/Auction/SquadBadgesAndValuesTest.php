<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Models\AuctionPlayer;
use App\Services\Auction\SquadAcquisitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * A player bought in the room is not a retained player.
 *
 * Squad views read `players.player_mode` to decide the badge — and selling a player sets that
 * to `retained` just as keeping one does. So every player bought at auction wore a "Retained"
 * badge, the organizer's team page offered to UN-RETAIN them (which would have quietly stripped
 * a purchase), and on the team manager's own card the chip at the top said "auction" while the
 * badge underneath said "retained": one card, two answers.
 *
 * The honest source is the auction row, and it now has one owner — SquadAcquisitionService —
 * because the two views had already started to drift apart on it.
 */
class SquadBadgesAndValuesTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function scenario(array $auctionOverrides = []): array
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, array_merge([
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 100_000_000,
            'amount_unit' => 'points',
        ], $auctionOverrides));

        $team = $this->makeTeam($org, 'Alpha', $tournament);

        $bought = $this->makePlayer($org, ['name' => 'Bought Bala', 'player_mode' => 'retained']);
        $kept = $this->makePlayer($org, ['name' => 'Kept Kumar', 'player_mode' => 'retained']);

        AuctionPlayer::create([
            'auction_id' => $auction->id, 'player_id' => $bought->id, 'organization_id' => $org->id,
            'base_price' => 100, 'current_price' => 100, 'starting_price' => 100,
            'status' => 'sold', 'sold_to_team_id' => $team->id, 'final_price' => 4_500_000,
        ]);

        AuctionPlayer::create([
            'auction_id' => $auction->id, 'player_id' => $kept->id, 'organization_id' => $org->id,
            'base_price' => 0, 'current_price' => 0, 'starting_price' => 0, 'status' => 'waiting',
            'is_retained' => true, 'team_id' => $team->id, 'retained_price' => 2_000_000,
        ]);

        return compact('org', 'auction', 'team', 'bought', 'kept');
    }

    #[Test]
    public function a_purchase_is_badged_icon_player_and_a_keep_is_badged_retained(): void
    {
        ['team' => $team, 'bought' => $bought, 'kept' => $kept] = $this->scenario();

        // Both have player_mode = 'retained' in the database, which is exactly why that column
        // cannot be the source: it says nothing about how the player arrived.
        $players = collect([$bought->fresh(), $kept->fresh()]);
        app(SquadAcquisitionService::class)->attach($players, $team);

        $this->assertSame('auction', $players[0]->acquisition);
        $this->assertSame('Icon Player', $players[0]->acquisition_label);

        $this->assertSame('retained', $players[1]->acquisition);
        $this->assertSame('Retained', $players[1]->acquisition_label);
    }

    #[Test]
    public function each_players_value_comes_from_the_right_column(): void
    {
        ['team' => $team, 'bought' => $bought, 'kept' => $kept] = $this->scenario();

        $players = collect([$bought->fresh(), $kept->fresh()]);
        app(SquadAcquisitionService::class)->attach($players, $team);

        // A buy is worth its final price; a keep is worth its retention price. Reading one from
        // the other is how a purchase ends up displayed as a blank or a retention as zero.
        $this->assertSame(4_500_000.0, $players[0]->acquisition_price);
        $this->assertStringContainsString('4.5M', $players[0]->acquisition_price_label);

        $this->assertSame(2_000_000.0, $players[1]->acquisition_price);
        $this->assertStringContainsString('2M', $players[1]->acquisition_price_label);
    }

    #[Test]
    public function switching_values_off_hides_the_money_but_keeps_the_badge(): void
    {
        ['team' => $team, 'bought' => $bought, 'kept' => $kept] = $this->scenario([
            'show_squad_values' => false,
        ]);

        $players = collect([$bought->fresh(), $kept->fresh()]);
        app(SquadAcquisitionService::class)->attach($players, $team);

        // The badge is the useful part and stays; the price is what a rival leans over to read.
        $this->assertNull($players[0]->acquisition_price_label);
        $this->assertNull($players[1]->acquisition_price_label);
        $this->assertSame('Icon Player', $players[0]->acquisition_label);
        $this->assertSame('Retained', $players[1]->acquisition_label);

        // The figure itself is still attached, for anything that needs to compute with it.
        $this->assertSame(4_500_000.0, $players[0]->acquisition_price);
    }

    #[Test]
    public function values_are_shown_by_default(): void
    {
        ['auction' => $auction] = $this->scenario();

        // Every squad view showed prices before the setting existed, so nothing may change
        // until an organizer decides otherwise.
        $this->assertTrue($auction->showsSquadValues());
    }

    #[Test]
    public function the_toggle_can_be_switched_off_and_back_on_again(): void
    {
        ['org' => $org, 'auction' => $auction] = $this->scenario();
        $operator = $this->makeSuperadmin($org);

        $payload = [
            'name' => $auction->name,
            'organization_id' => $auction->organization_id,
            'tournament_id' => $auction->tournament_id,
            'status' => 'scheduled',
            'max_budget_per_team' => 100_000_000,
            'base_price' => 1_000_000,
            'bid_type' => 'open',
            'bid_timer_seconds' => 30,
            'bid_rules' => [['from' => 0, 'to' => 200_000_000, 'increment' => 100_000]],
        ];

        // An unticked checkbox posts nothing, so the form sends a hidden 0 alongside it. Without
        // that the server cannot tell "unticked" from "this form has no such field", and the
        // toggle could be switched on and never off.
        $this->actingAs($operator)
            ->put(route('admin.auctions.update', $auction), $payload + ['show_squad_values' => 0])
            ->assertSessionHasNoErrors();
        $this->assertFalse($auction->fresh()->showsSquadValues());

        $this->actingAs($operator)
            ->put(route('admin.auctions.update', $auction), $payload + ['show_squad_values' => 1])
            ->assertSessionHasNoErrors();
        $this->assertTrue($auction->fresh()->showsSquadValues());
    }

    #[Test]
    public function a_purchase_is_not_offered_the_unretain_button(): void
    {
        ['org' => $org, 'team' => $team, 'bought' => $bought, 'kept' => $kept] = $this->scenario();

        // The roster on this page is read from player_actual_team_tournament, not from the
        // actual_team_users pivot — so that is where these two have to be.
        foreach ([$bought, $kept] as $player) {
            \Illuminate\Support\Facades\DB::table('player_actual_team_tournament')->insert([
                'player_id' => $player->id,
                'actual_team_id' => $team->id,
                'tournament_id' => $team->tournament_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // The page authorizes `actual-team.view`, which makeSuperadmin() does not grant.
        $operator = $this->makeSuperadmin($org);
        $operator->roles->first()->givePermissionTo(
            \App\Models\Permission::firstOrCreate(
                ['name' => 'actual-team.view', 'guard_name' => 'web'],
                ['group_name' => 'actual-team']
            )
        );

        $html = $this->actingAs($operator)
            ->get(route('admin.actual-teams.show', $team))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Icon Player', $html);
        $this->assertStringContainsString('Retained', $html);

        // One Unretain form, for the kept player — not two.
        $this->assertSame(1, substr_count($html, 'Unretain'));
    }

}

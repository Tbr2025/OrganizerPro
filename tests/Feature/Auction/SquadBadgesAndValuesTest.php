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
 * A player bought in the room is not an icon player.
 *
 * Squad views read `players.player_mode` to decide the badge — and selling a player sets that
 * to `retained` just as keeping one does. So every player bought at auction wore the keeper's
 * badge, the organizer's team page offered to UN-RETAIN them (which would have quietly stripped
 * a purchase), and on the team manager's own card the chip at the top said "auction" while the
 * badge underneath disagreed: one card, two answers.
 *
 * The honest source is the auction row, and it has one owner — SquadAcquisitionService.
 *
 * The two labels were then the wrong way round: a BUY was called "Icon Player" and a KEEP was
 * called "Retained". An icon player is one a team keeps before the auction — that is what the
 * word means here — so the badges have been swapped. The DATA keeps its own names
 * (`player_mode = 'retained'`, `retained_price`), because renaming a column to match a label is
 * how one thing ends up with two meanings.
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
    public function a_keep_is_badged_icon_player_and_a_purchase_is_badged_auction(): void
    {
        ['team' => $team, 'bought' => $bought, 'kept' => $kept] = $this->scenario();

        // Both have player_mode = 'retained' in the database, which is exactly why that column
        // cannot be the source: it says nothing about how the player arrived.
        $players = collect([$bought->fresh(), $kept->fresh()]);
        app(SquadAcquisitionService::class)->attach($players, $team);

        $this->assertSame('auction', $players[0]->acquisition);
        $this->assertSame('Auction', $players[0]->acquisition_label);

        $this->assertSame('retained', $players[1]->acquisition);
        $this->assertSame('Icon Player', $players[1]->acquisition_label);
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
        $this->assertSame('Auction', $players[0]->acquisition_label);
        $this->assertSame('Icon Player', $players[1]->acquisition_label);

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
        $this->assertStringContainsString('Auction', $html);

        // One Unretain form, for the kept player — not two.
        $this->assertSame(1, substr_count($html, 'Unretain'));
    }


    #[Test]
    public function a_players_list_resolves_each_player_against_their_own_team(): void
    {
        ['team' => $team, 'bought' => $bought, 'kept' => $kept] = $this->scenario();

        // A players list spans every team, so it cannot pass one in. attachForOwnTeams() reads
        // players.actual_team_id instead — and must reach the same verdict as attach() does.
        foreach ([$bought, $kept] as $player) {
            $player->forceFill(['actual_team_id' => $team->id])->save();
        }

        $players = collect([$bought->fresh(), $kept->fresh()]);
        app(SquadAcquisitionService::class)->attachForOwnTeams($players);

        $this->assertSame('Auction', $players[0]->acquisition_label);
        $this->assertSame('Icon Player', $players[1]->acquisition_label);
        $this->assertSame(4_500_000.0, $players[0]->acquisition_price);
    }

    #[Test]
    public function a_player_who_never_went_through_an_auction_gets_no_badge(): void
    {
        ['org' => $org] = $this->scenario();

        // Somebody who simply joined a squad is neither bought nor kept, and a list must not
        // invent a label for them — this is the case player_mode could never express either.
        $joined = $this->makePlayer($org, ['name' => 'Just Joined', 'player_mode' => 'normal']);

        $players = collect([$joined]);
        app(SquadAcquisitionService::class)->attachForOwnTeams($players);

        $this->assertNull($players[0]->acquisition);
        $this->assertNull($players[0]->acquisition_label);
        $this->assertNull($players[0]->acquisition_price_label);
    }

    #[Test]
    public function the_players_list_page_badges_a_purchase_correctly(): void
    {
        ['org' => $org, 'team' => $team, 'bought' => $bought] = $this->scenario();

        // The list only shows players with a user account holding the Player role.
        $playerRole = \App\Models\Role::firstOrCreate(['name' => 'Player', 'guard_name' => 'web']);
        $account = $this->makePlainUser($org);
        $account->assignRole($playerRole);

        $bought->forceFill(['actual_team_id' => $team->id, 'user_id' => $account->id])->save();

        $operator = $this->makeSuperadmin($org);
        $operator->roles->first()->givePermissionTo(
            \App\Models\Permission::firstOrCreate(
                ['name' => 'player.view', 'guard_name' => 'web'],
                ['group_name' => 'player']
            )
        );

        $html = $this->actingAs($operator)
            ->get(route('admin.players.index'))
            ->assertOk()
            ->getContent();

        // The badge said "Retained" here for every purchase, beside a Remove Retention action
        // that would have stripped one.
        $this->assertStringContainsString('Icon Player', $html);
    }

    #[Test]
    public function every_player_field_is_exported_to_a_workbook(): void
    {
        ['org' => $org, 'team' => $team, 'bought' => $bought] = $this->scenario();
        $bought->forceFill(['actual_team_id' => $team->id])->save();

        $players = \App\Models\Player::with(['actualTeam', 'playerType'])->get();
        app(SquadAcquisitionService::class)->attachForOwnTeams($players);

        $path = tempnam(sys_get_temp_dir(), 'players-') . '.xlsx';
        app(\App\Services\Export\PlayerWorkbookExport::class)->write($players, $path);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path) === true, 'the workbook must be a readable xlsx');
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($path);

        /*
         * The columns are read from the SCHEMA, not hand-listed like the CSV beside this — so a
         * field added to `players` appears in the export the day it is added rather than being
         * silently absent. Asserted on a couple that the CSV never carried.
         */
        $this->assertStringContainsString('Jersey Number', $sheet);
        $this->assertStringContainsString('Visa Status', $sheet);
        $this->assertStringContainsString('Tshirt Size', $sheet);

        // And the relationship columns a human actually reads, rather than raw foreign keys.
        $this->assertStringContainsString('Acquired As', $sheet);
        $this->assertStringContainsString('Icon Player', $sheet);
        $this->assertStringNotContainsString('Batting Profile Id', $sheet);
    }
}

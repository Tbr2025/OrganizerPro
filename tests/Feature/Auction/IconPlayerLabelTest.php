<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use App\Services\Auction\SquadAcquisitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * An icon player is one a team KEEPS before the auction.
 *
 * The two labels were the wrong way round: a player bought in the room was badged "Icon Player"
 * and a player kept was badged "Retained". That is backwards — keeping a marquee name before the
 * bidding starts is what the word describes — so every squad list in the application has been
 * calling purchases icons.
 *
 * The rename is display-only, and that boundary is the point of this test. `player_mode` still
 * stores `retained`, the columns are still `retained_price` / `retained_value` / `is_retained`,
 * and every route, request field and method name is untouched. Renaming data to match a label is
 * how one thing ends up with two meanings, and a half-finished rename is how it ends up with two
 * names for the same thing.
 */
class IconPlayerLabelTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    /**
     * A Superadmin with the permissions these two pages check.
     *
     * `makeSuperadmin()` grants auction permissions only, and both screens go through
     * checkAuthorization() for their own — so without these the test asserts 403s.
     */
    private function viewer($org)
    {
        $user = $this->makeSuperadmin($org);

        foreach (['player.view', 'actual-team.view', 'team.view'] as $name) {
            $user->givePermissionTo(
                \Spatie\Permission\Models\Permission::firstOrCreate(
                    ['name' => $name, 'guard_name' => 'web'],
                    ['group_name' => 'general']
                )
            );
        }

        return $user->fresh();
    }

    #[Test]
    public function a_kept_player_is_an_icon_player_and_a_bought_one_is_not(): void
    {
        $this->assertSame('Icon Player', SquadAcquisitionService::label(SquadAcquisitionService::RETAINED));
        $this->assertSame('Auction', SquadAcquisitionService::label(SquadAcquisitionService::AUCTION));
        $this->assertNull(SquadAcquisitionService::label(null));

        // One place to change it, rather than a sweep of twenty Blade files next time.
        $this->assertSame('Icon Player', SquadAcquisitionService::retainedLabel());
    }

    #[Test]
    public function the_data_keeps_its_own_names(): void
    {
        /*
         * The acquisition VALUES are what the rest of the system matches on — a filter posting
         * `player_mode=retained`, a query on `acquisition`, an export column. Renaming these to
         * follow the badge would have broken every one of them silently.
         */
        $this->assertSame('retained', SquadAcquisitionService::RETAINED);
        $this->assertSame('auction', SquadAcquisitionService::AUCTION);
    }

    /*
     * The RENDERED badge is asserted where the fixtures for it already exist:
     * SquadBadgesAndValuesTest covers the squad pages, and AuctionPoolRetainedSeparationTest
     * covers the auction page's "Icon players" heading. Duplicating those here would mean a
     * third copy of an auction-plus-retention fixture to assert the same string.
     */

    #[Test]
    public function the_tournament_can_switch_the_badge_off_without_touching_the_data(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        \App\Models\TournamentSetting::updateOrCreate(
            ['tournament_id' => $tournament->id],
            ['show_acquisition_badge' => false]
        );

        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'overrides_tournament_rules' => false,
        ]);
        $team = $this->makeTeam($org, 'Keepers', $tournament);

        $kept = $this->makePlayer($org, ['name' => 'Kept Kohli']);
        $kept->update(['actual_team_id' => $team->id]);
        $this->makeAuctionPlayer($auction, [
            'player' => $kept, 'is_retained' => true, 'team_id' => $team->id,
            'retained_price' => 5_000_000, 'status' => 'waiting',
        ]);

        $players = collect([$kept->fresh()]);
        app(SquadAcquisitionService::class)->attach($players, $team);

        /*
         * The LABEL goes; what the player IS does not. Filters, exports and squad arithmetic all
         * read `acquisition`, so hiding a badge must not change the answer to "how did this
         * player get here" — only whether the screen says so.
         */
        $this->assertNull($players[0]->acquisition_label);
        $this->assertSame('retained', $players[0]->acquisition);

        // And with it on, the badge is back.
        $tournament->settings->update(['show_acquisition_badge' => true]);

        $again = collect([$kept->fresh()]);
        app(SquadAcquisitionService::class)->attach($again, $team);

        $this->assertSame('Icon Player', $again[0]->acquisition_label);
    }

    #[Test]
    public function the_players_list_filter_keeps_its_value_and_gains_the_new_label(): void
    {
        $org = $this->makeOrganization();

        $html = $this->actingAs($this->viewer($org))
            ->get(route('admin.players.index'))
            ->assertOk()
            ->getContent();

        /*
         * The label a person reads, on the filter that can actually answer it.
         *
         * This used to assert `value="retained"` — the old Player Mode control, which read
         * `players.player_mode`. That column is set to `retained` when a player is SOLD as
         * well as when one is kept, so the option labelled Icon Player returned every purchase
         * in the room alongside the genuinely kept players. The filter now reads the auction
         * row (`is_retained` is a keep, `status = sold` is a buy), and its value says so.
         */
        $this->assertMatchesRegularExpression(
            '/<option value="icon"[^>]*>\s*Icon Player \(kept\)\s*<\/option>/',
            $html
        );

        // And the buy has a name of its own, which the old control could not express at all.
        $this->assertMatchesRegularExpression(
            '/<option value="auction"[^>]*>\s*Auction \(bought\)\s*<\/option>/',
            $html
        );
    }
}

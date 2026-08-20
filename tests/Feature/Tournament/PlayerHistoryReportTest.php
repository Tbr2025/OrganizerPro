<?php

declare(strict_types=1);

namespace Tests\Feature\Tournament;

use App\Models\ActualTeam;
use App\Models\AuctionPlayer;
use App\Models\User;
use App\Services\Auction\AuctionSaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * The tournament's player history: how every player was acquired, out of which pool, for how much
 * and when — in both of the zones this competition is read in.
 *
 * The interesting cases are the ones the schema makes easy to get wrong: an icon player must not
 * be described as a buy (selling a player sets `player_mode` to `retained` too, so that column
 * cannot be read), an unsold player's pool survives only in `source_pool_id` after the unsold
 * piles were merged, and a date range means nothing until you say whose midnight it is.
 */
class PlayerHistoryReportTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    /** An admin who may view tournaments — the permission the page is gated on. */
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
    public function the_page_is_routed_and_reachable_by_an_admin(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('admin.tournaments.player-history.index'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('admin.tournaments.player-history.pdf'));

        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);

        $this->actingAs($this->makeTournamentAdmin($org))
            ->get(route('admin.tournaments.player-history.index', $tournament))
            ->assertOk()
            ->assertSee('Player History');
    }

    #[Test]
    public function a_user_without_the_tournament_permission_is_refused(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);

        $this->actingAs($this->makePlainUser($org))
            ->get(route('admin.tournaments.player-history.index', $tournament))
            ->assertForbidden();
    }

    #[Test]
    public function a_team_manager_never_lands_on_it(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);

        $role = Role::firstOrCreate(['name' => 'Team Manager', 'guard_name' => 'web']);
        $manager = User::factory()->create(['organization_id' => $org->id])->assignRole($role);

        $this->actingAs($manager)
            ->get(route('admin.tournaments.player-history.index', $tournament))
            ->assertRedirect(route('team-manager.dashboard'));
    }

    #[Test]
    public function an_open_tournament_is_told_why_it_has_no_history(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org, 'open');

        /*
         * Open tournaments have no auction rows at all — managers add players to squads directly.
         * The page is still reachable from the dashboard, so it explains itself rather than
         * showing an empty table that reads like a bug.
         */
        $this->actingAs($this->makeTournamentAdmin($org))
            ->get(route('admin.tournaments.player-history.index', $tournament))
            ->assertOk()
            ->assertSee('has no auction');
    }

    #[Test]
    public function a_buy_a_keep_and_an_unsold_player_are_each_described_correctly(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $team = $this->makeTeam($org, 'Alpha Strikers', $tournament);
        $pool = $this->makePool($auction, ['name' => 'Pool Alpha']);

        $bought = $this->makePlayer($org, ['name' => 'Bought Bella']);
        $this->makeAuctionPlayer($auction, [
            'player' => $bought,
            'auction_pool_id' => $pool->id,
            'status' => 'sold',
            'sold_to_team_id' => $team->id,
            'final_price' => 4500,
        ]);

        $kept = $this->makePlayer($org, ['name' => 'Kept Kiran']);
        $this->makeAuctionPlayer($auction, [
            'player' => $kept,
            'status' => 'waiting',
            'is_retained' => true,
            'team_id' => $team->id,
            'retained_price' => 2000,
        ]);

        $unsold = $this->makePlayer($org, ['name' => 'Unsold Umar']);
        $this->makeAuctionPlayer($auction, [
            'player' => $unsold,
            'status' => 'unsold',
            'source_pool_id' => $pool->id,
        ]);

        $page = $this->actingAs($this->makeTournamentAdmin($org))
            ->get(route('admin.tournaments.player-history.index', $tournament))
            ->assertOk();

        $page->assertSee('Bought Bella');
        $page->assertSee('Kept Kiran');
        $page->assertSee('Unsold Umar');

        /*
         * The label that matters. A kept player reads "Icon Player" and a bought one reads
         * "Auction" — the wrong way round is exactly what every squad view did while it was
         * reading `players.player_mode`, which a sale also sets to `retained`.
         */
        $page->assertSee('Icon Player');
        $page->assertSee('Auction');

        // Both prices, in the auction's own unit.
        $page->assertSee($auction->formatAmount(4500));
        $page->assertSee($auction->formatAmount(2000));
    }

    #[Test]
    public function the_pool_filter_finds_an_unsold_player_by_the_pool_they_were_bid_in(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $pool = $this->makePool($auction, ['name' => 'Pool Alpha']);
        $other = $this->makePool($auction, ['name' => 'Pool Beta', 'sequence' => 2]);

        /*
         * Unsold players share one pile per auction, so `auction_pool_id` no longer says which
         * pool they came out of — `source_pool_id` is the only record of it. "Who went unsold out
         * of Pool Alpha" has to keep working.
         */
        $stranded = $this->makePlayer($org, ['name' => 'Unsold Umar']);
        $this->makeAuctionPlayer($auction, [
            'player' => $stranded,
            'status' => 'unsold',
            'auction_pool_id' => null,
            'source_pool_id' => $pool->id,
        ]);

        $elsewhere = $this->makePlayer($org, ['name' => 'Beta Bhavin']);
        $this->makeAuctionPlayer($auction, [
            'player' => $elsewhere,
            'auction_pool_id' => $other->id,
        ]);

        $page = $this->actingAs($this->makeTournamentAdmin($org))
            ->get(route('admin.tournaments.player-history.index', [$tournament, 'pool_id' => $pool->id]))
            ->assertOk();

        $page->assertSee('Unsold Umar');
        $page->assertDontSee('Beta Bhavin');
    }

    #[Test]
    public function the_search_matches_an_email_as_well_as_a_name(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);

        $wanted = $this->makePlayer($org, ['name' => 'Wanted Wasim', 'email' => 'wasim@club.test']);
        $this->makeAuctionPlayer($auction, ['player' => $wanted]);

        $other = $this->makePlayer($org, ['name' => 'Other Omar', 'email' => 'omar@club.test']);
        $this->makeAuctionPlayer($auction, ['player' => $other]);

        $page = $this->actingAs($this->makeTournamentAdmin($org))
            ->get(route('admin.tournaments.player-history.index', [$tournament, 'search' => 'wasim@club']))
            ->assertOk();

        $page->assertSee('Wanted Wasim');
        $page->assertDontSee('Other Omar');
    }

    #[Test]
    public function a_date_range_is_read_in_the_zone_the_user_picked(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $team = $this->makeTeam($org, 'Alpha Strikers', $tournament);

        /*
         * A late-evening sale in the Gulf is already the next day in India: 19:00 UTC is 23:00 on
         * the 10th in Dubai and 00:30 on the 11th in IST. So "from the 11th" includes this sale
         * when the range is read in IST and excludes it when read in Dubai time — which is the
         * whole reason the filter bar carries a zone at all.
         */
        $player = $this->makePlayer($org, ['name' => 'Midnight Mo']);
        $this->makeAuctionPlayer($auction, [
            'player' => $player,
            'status' => 'sold',
            'sold_to_team_id' => $team->id,
            'final_price' => 1200,
            'sold_at' => '2026-08-10 19:00:00',
        ]);

        $admin = $this->makeTournamentAdmin($org);

        $this->actingAs($admin)
            ->get(route('admin.tournaments.player-history.index', [
                $tournament, 'date_from' => '2026-08-11', 'tz' => 'Asia/Kolkata',
            ]))
            ->assertOk()
            ->assertSee('Midnight Mo');

        $this->actingAs($admin)
            ->get(route('admin.tournaments.player-history.index', [
                $tournament, 'date_from' => '2026-08-11', 'tz' => 'Asia/Dubai',
            ]))
            ->assertOk()
            ->assertDontSee('Midnight Mo');
    }

    #[Test]
    public function the_summary_describes_every_match_not_just_the_first_page(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $team = $this->makeTeam($org, 'Alpha Strikers', $tournament);

        // More than one page of 25, so a total walked over the paginator would be wrong.
        for ($i = 0; $i < 30; $i++) {
            $this->makeAuctionPlayer($auction, [
                'status' => 'sold',
                'sold_to_team_id' => $team->id,
                'final_price' => 100,
            ]);
        }

        $this->actingAs($this->makeTournamentAdmin($org))
            ->get(route('admin.tournaments.player-history.index', $tournament))
            ->assertOk()
            // 30 sold at 100 each, not the 25 the page is showing.
            ->assertSee($auction->formatAmount(3000));
    }

    #[Test]
    public function a_sale_records_when_it_happened_and_an_undone_sale_stops_claiming_a_time(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $team = ActualTeam::find($this->makeTeam($org, 'Alpha Strikers', $tournament)->id);

        $auctionPlayer = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);

        $service = app(AuctionSaleService::class);
        $snapshot = $service->applySale($auctionPlayer, $team, 900.0);

        $sold = AuctionPlayer::find($auctionPlayer->id);
        $this->assertNotNull($sold->sold_at, 'A sale must record when it happened.');

        $service->revert($sold, $snapshot);

        $this->assertNull(
            AuctionPlayer::find($auctionPlayer->id)->sold_at,
            'An undone sale must stop claiming a sale time.'
        );
    }

    #[Test]
    public function the_pdf_export_carries_the_filters_and_says_no_signature_is_needed(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $team = $this->makeTeam($org, 'Alpha Strikers', $tournament);

        $player = $this->makePlayer($org, ['name' => 'Bought Bella']);
        $this->makeAuctionPlayer($auction, [
            'player' => $player,
            'status' => 'sold',
            'sold_to_team_id' => $team->id,
            'final_price' => 4500,
        ]);

        /*
         * The document itself is rendered by headless Chrome, which is not something to boot in
         * a unit test — so this asserts the view that goes INTO it: the branded header, the
         * filters it was run with, and the footer line the organizer asked for.
         */
        $view = $this->view('pdf.player-history', [
            'tournament' => $tournament,
            'auction' => $auction,
            'rows' => collect(),
            'summary' => ['players' => 0, 'sold' => 0, 'icons' => 0, 'unsold' => 0,
                'spend' => 0.0, 'highest' => 0.0, 'average' => 0.0],
            'filters' => app(\App\Services\Auction\PlayerHistoryQuery::class)
                ->filters(request()),
            'zones' => \App\Services\Auction\PlayerHistoryQuery::zones(),
            'describe' => ['Auction: ' . $auction->name],
            'times' => ['Asia/Kolkata' => '20 Aug 2026, 09:14 PM', 'Asia/Dubai' => '20 Aug 2026, 07:44 PM'],
            'omitted' => 0,
            'total' => 0,
            'tournamentLogo' => null,
            'auctionLogo' => null,
        ]);

        $view->assertSee('Player History Report');
        $view->assertSee($tournament->name);
        $view->assertSee($auction->name);
        $view->assertSee('Auction: ' . $auction->name);

        // Both zones are named in the header, so a reader knows which column is which.
        $view->assertSee('IST');
        $view->assertSee('Dubai');

        // The footer Chrome repeats on every page.
        $this->view('pdf.partials.player-history-footer')
            ->assertSee('This is an electronically generated report. No signature is required.');
    }
}

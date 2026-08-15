<?php

namespace Tests\Feature\Auction;

use App\Models\Auction;
use App\Models\AuctionPool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * The streaming overlay at /auction/{auction}/ticker — a transparent 1920x1080 page
 * added to a mixer as a browser source, fed by one public endpoint.
 */
class AuctionTickerTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    #[Test]
    public function the_ticker_renders_without_authentication(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);

        // No actingAs: an OBS browser source has no session.
        $this->get(route('public.auction.ticker', $auction))
            ->assertOk()
            ->assertSee('background: transparent', false)
            ->assertSee('1920px', false)
            ->assertSee('Recent Sales');
    }

    #[Test]
    public function the_feed_reports_the_player_on_the_block(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 100000,
        ]);
        $team = $this->makeTeam($org, 'Strikers', $tournament);

        $player = $this->makePlayer($org, ['name' => 'Live Larry']);
        $ap = $this->makeAuctionPlayer($auction, [
            'player' => $player,
            'status' => 'on_auction',
            'base_price' => 1000,
        ]);
        $ap->update(['current_price' => 2500, 'current_bid_team_id' => $team->id]);

        $this->getJson(route('public.auction.ticker-feed', $auction))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('current_player.name', 'Live Larry')
            ->assertJsonPath('current_player.leading_team', 'Strikers')
            ->assertJsonPath('current_player.current_price', '2500.00');
    }

    /**
     * The broadcast strip carries NO team purses.
     *
     * It used to: the feed listed every team with its remaining purse and squad count so the
     * strip could slide a table in from the side. That panel is not wanted on a stream overlay,
     * and it was the most expensive thing the feed did — one purse read per team, rebuilt every
     * second for as long as a single ticker screen was open anywhere. On a 16-team auction that
     * was ~112 aggregate queries a second from one forgotten browser tab.
     *
     * Dropping the key is the whole change: renderTeams() hides the panel when the list is
     * empty, so an older cached page degrades to a hidden panel rather than a broken one.
     *
     * It also stops a public, unauthenticated endpoint from publishing what every team has left
     * to spend.
     */
    #[Test]
    public function the_feed_carries_no_team_purse_panel(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 10000,
            'min_squad_size' => 4,
        ]);
        $team = $this->makeTeam($org, 'Strikers', $tournament);
        $operator = $this->makeAuctionOperator($org);

        $ap = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);
        $this->makeBid($ap, $team, 2500, $operator);
        $this->actingAs($operator)
            ->postJson(route('admin.auction.organizer.api.player.sell', $auction), ['auction_player_id' => $ap->id])
            ->assertOk();

        $feed = $this->getJson(route('public.auction.ticker-feed', $auction))->assertOk()->json();

        $this->assertArrayNotHasKey('teams', $feed, 'the ticker must not publish team purses');

        // The sale itself is still reported — only the side panel went.
        $this->assertSame('Strikers', $feed['recent_sales'][0]['team_name']);
    }

    #[Test]
    public function the_feed_lists_recent_sales_newest_first(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 100000,
            /*
             * Room for both sales. The shared fixture uses a squad size of 1 to keep the reserve
             * rule from swamping small test budgets — and a squad of 1 is FULL after one player,
             * so the second sale is now refused (a full squad has a bidding ceiling of zero).
             * The two meanings of that number were indistinguishable until fullness was enforced.
             */
            'min_squad_size' => 11,
        ]);
        $team = $this->makeTeam($org, 'Strikers', $tournament);
        $operator = $this->makeAuctionOperator($org);

        foreach (['First Sale', 'Second Sale'] as $name) {
            $player = $this->makePlayer($org, ['name' => $name]);
            $ap = $this->makeAuctionPlayer($auction, ['player' => $player, 'status' => 'on_auction']);
            $this->makeBid($ap, $team, 500, $operator);
            $this->actingAs($operator)
                ->postJson(route('admin.auction.organizer.api.player.sell', $auction), ['auction_player_id' => $ap->id])
                ->assertOk();
        }

        $sales = $this->getJson(route('public.auction.ticker-feed', $auction))->assertOk()->json('recent_sales');

        $this->assertCount(2, $sales);
        $this->assertSame('Strikers', $sales[0]['team_name']);
        $this->assertEqualsCanonicalizing(
            ['First Sale', 'Second Sale'],
            array_column($sales, 'player_name')
        );
    }

    #[Test]
    public function the_feed_reports_pool_progress(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);

        $pool = $this->makePool($auction, ['name' => 'Marquee', 'status' => AuctionPool::STATUS_ACTIVE]);
        $this->makeAuctionPlayer($auction, ['auction_pool_id' => $pool->id, 'lot_number' => 1]);
        $this->makeAuctionPlayer($auction, ['auction_pool_id' => $pool->id, 'lot_number' => 2]);

        $this->getJson(route('public.auction.ticker-feed', $auction))
            ->assertOk()
            ->assertJsonPath('active_pool.name', 'Marquee')
            ->assertJsonPath('active_pool.total', 2)
            ->assertJsonPath('active_pool.waiting', 2);
    }

    #[Test]
    public function the_feed_carries_the_clock_and_closing_calls(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'bid_timer_seconds' => 30,
            'final_call_interval_seconds' => 3,
        ]);
        $ap = $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);
        $auction->update(['timer_started_at' => now()->subSeconds(28)]);

        $this->getJson(route('public.auction.ticker-feed', $auction))
            ->assertOk()
            ->assertJsonPath('timer.enabled', true)
            ->assertJsonPath('timer.final_call.label', 'FINAL CALL')
            ->assertJsonCount(3, 'timer.final_call_stages');
    }

    #[Test]
    public function the_feed_carries_the_amount_unit(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'amount_unit' => Auction::UNIT_USD,
        ]);

        $this->getJson(route('public.auction.ticker-feed', $auction))
            ->assertOk()
            ->assertJsonPath('amount_unit.label', '$')
            ->assertJsonPath('amount_unit.prefix', true);
    }

    #[Test]
    public function the_feed_does_not_leak_the_bid_ceiling(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'closed_bid_starts_at' => 5000000,
        ]);
        $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);

        // A public overlay must not reveal the increment ladder or the sealed-bid
        // threshold — anyone watching the stream can open this URL.
        $this->getJson(route('public.auction.ticker-feed', $auction))
            ->assertOk()
            ->assertJsonMissingPath('bid_rules')
            ->assertJsonMissingPath('closed_bid_starts_at');
    }

    #[Test]
    public function the_feed_does_not_leak_per_team_reserve_internals(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 10_000_000,
        ]);
        $this->makeTeam($org, 'Strikers', $tournament);

        // teamPurseState() carries max_bid_allowed and reserve. Those are effectively the
        // bid ceiling, so the public map hand-picks its keys and must never be
        // "simplified" into a spread of the whole state array.
        $this->getJson(route('public.auction.ticker-feed', $auction))
            ->assertOk()
            ->assertJsonMissingPath('teams.0.max_bid_allowed')
            ->assertJsonMissingPath('teams.0.reserve')
            ->assertJsonMissingPath('teams.0.allocated');
    }

    #[Test]
    public function the_feed_carries_the_career_stats_strip(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $player = $this->makePlayer($org, [
            'total_matches' => 12,
            'total_runs' => null,
            'total_wickets' => 0,
        ]);
        $this->makeAuctionPlayer($auction, ['status' => 'on_auction', 'player_id' => $player->id]);

        // 0 is a figure somebody entered and must render; null was never filled in and
        // its cell is dropped. Collapsing the two would put a fake 0 on a live stream.
        $this->getJson(route('public.auction.ticker-feed', $auction))
            ->assertOk()
            ->assertJsonPath('current_player.stats.matches', 12)
            ->assertJsonPath('current_player.stats.runs', null)
            ->assertJsonPath('current_player.stats.wickets', 0);
    }

    #[Test]
    public function a_player_with_no_declared_figures_has_no_stats_block(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $player = $this->makePlayer($org);
        $this->makeAuctionPlayer($auction, ['status' => 'on_auction', 'player_id' => $player->id]);

        // The strip hides itself rather than rendering three empty cells.
        $this->getJson(route('public.auction.ticker-feed', $auction))
            ->assertOk()
            ->assertJsonPath('current_player.stats', null);
    }

    #[Test]
    public function the_feed_reports_the_squad_bounds(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);

        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'min_squad_size' => 18,
            'max_squad_size' => 25,
        ]);

        $this->getJson(route('public.auction.ticker-feed', $auction))
            ->assertOk()
            ->assertJsonPath('squad.min', 18)
            ->assertJsonPath('squad.max', 25);

        // Unconfigured stays null so the footer omits it instead of inventing a ceiling.
        $noMax = $this->makeAuction($org, ['tournament_id' => $tournament->id, 'min_squad_size' => 11]);

        $this->getJson(route('public.auction.ticker-feed', $noMax))
            ->assertOk()
            ->assertJsonPath('squad.min', 11)
            ->assertJsonPath('squad.max', null);
    }

    #[Test]
    public function the_broadcast_picker_lists_auctions_and_both_screens(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id, 'name' => 'Season Two Auction']);

        $this->actingAs($this->makeAuctionOperator($org))
            ->get(route('admin.auctions.broadcast'))
            ->assertOk()
            ->assertSee('Season Two Auction')
            ->assertSee(route('public.auction.ticker', $auction), false)
            ->assertSee(route('public.auction.live', $auction), false);
    }

    #[Test]
    public function the_broadcast_picker_needs_permission(): void
    {
        $org = $this->makeOrganization();

        $this->actingAs($this->makePlainUser($org))
            ->get(route('admin.auctions.broadcast'))
            ->assertForbidden();
    }

    #[Test]
    public function the_redesigned_ticker_renders_its_blocks(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);

        $this->get(route('public.auction.ticker', $auction))
            ->assertOk()
            ->assertSee('Base Price')
            ->assertSee('Current Bid')
            ->assertSee('teams-panel', false)
            ->assertSee('lt-name-plate', false)
            // The superseded purse pills are gone, not merely hidden.
            ->assertDontSee('class="purse', false);
    }
}

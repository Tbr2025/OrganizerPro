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

    #[Test]
    public function the_feed_reports_team_purses_which_no_other_public_endpoint_exposes(): void
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

        $this->assertCount(1, $feed['teams']);
        $this->assertSame('Strikers', $feed['teams'][0]['name']);
        $this->assertSame(7500.0, (float) $feed['teams'][0]['remaining']);
        $this->assertSame(1, $feed['teams'][0]['players']);
        $this->assertSame(4, $feed['teams'][0]['squad_required']);
    }

    #[Test]
    public function the_feed_lists_recent_sales_newest_first(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'max_budget_per_team' => 100000,
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
}

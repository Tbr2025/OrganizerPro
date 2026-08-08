<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * The public broadcast feeds are shared, not rebuilt per viewer.
 *
 * These three endpoints are read-only and identical for everyone watching, but the hall
 * projector, the OBS ticker, the organizer's second screen and every phone in the room each
 * triggered their own full rebuild every two seconds. On production that was roughly 1,500
 * of 2,400 requests in a two-minute sample, against five PHP workers on two cores — and
 * every page on the site, including /login, had degraded to a ten-to-twenty second response.
 *
 * The cache is deliberately one second: clients already poll on a two-second cycle and tick
 * their own countdowns between polls, so this adds at most a second of staleness.
 */
class AuctionFeedCacheTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function auction(string $orgName = 'Alpha Sports')
    {
        // Distinct names: two auctions in one test would otherwise collide on the
        // organizations unique index.
        $org = $this->makeOrganization($orgName);
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id, 'status' => 'running']);
        $this->makeTeam($org, 'Alpha', $tournament);
        $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);

        return $auction;
    }

    /** @return array{0:int,1:int} queries for the first request, then for a second one */
    private function queryCountsFor(string $url): array
    {
        Cache::flush();

        $first = 0;
        DB::listen(function () use (&$first) {
            $first++;
        });
        $this->getJson($url)->assertOk();
        $afterFirst = $first;

        $this->getJson($url)->assertOk();

        return [$afterFirst, $first - $afterFirst];
    }

    #[Test]
    public function a_second_viewer_costs_no_database_queries(): void
    {
        $auction = $this->auction();

        foreach ([
            "/auction/{$auction->id}/active-player",
            "/auction/{$auction->id}/ticker-feed",
            "/auction/{$auction->id}/sold-players",
        ] as $url) {
            [$first, $second] = $this->queryCountsFor($url);

            // More than the route binding alone, i.e. the feed genuinely built something.
            $this->assertGreaterThan(1, $first, "{$url} should do real work on a cold cache");

            /*
             * Not zero: route-model binding resolves the Auction before the controller runs,
             * and the framework does a little work of its own per request. What must not
             * repeat is the BUILD — the per-team purse fan-out above all, which is why the
             * ticker feed costs 84 queries cold and 2 warm on a six-team auction.
             *
             * Asserted as a ratio rather than an absolute, so it keeps meaning something as
             * the payload grows.
             */
            $this->assertLessThanOrEqual(
                max(3, (int) ceil($first * 0.2)),
                $second,
                "{$url} rebuilt itself for the second viewer ({$second} of {$first} queries "
                    . 'repeated) — the whole point is that every screen watching shares one build'
            );
        }
    }

    #[Test]
    public function each_auction_is_cached_separately(): void
    {
        $a = $this->auction('Org A');
        $b = $this->auction('Org B');

        $this->getJson("/auction/{$a->id}/active-player")->assertOk();

        // A shared key would serve auction A's player on auction B's wall.
        $this->getJson("/auction/{$b->id}/active-player")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotNull(Cache::get("auction-feed:active-player:{$a->id}"));
        $this->assertNotNull(Cache::get("auction-feed:active-player:{$b->id}"));
    }

    #[Test]
    public function the_payload_is_unchanged_by_caching(): void
    {
        $auction = $this->auction();

        $fresh = $this->getJson("/auction/{$auction->id}/active-player")->assertOk()->json();
        $cached = $this->getJson("/auction/{$auction->id}/active-player")->assertOk()->json();

        // Caching must be invisible to every client that already reads these feeds.
        $this->assertSame($fresh, $cached);
        $this->assertArrayHasKey('progress', $cached);
        $this->assertArrayHasKey('auction_status', $cached);
    }
}

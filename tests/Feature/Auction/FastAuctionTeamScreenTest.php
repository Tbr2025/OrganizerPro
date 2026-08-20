<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * The lean bidding screen.
 *
 * The point of this module is one number: the existing bidding page extends
 * `backend.layouts.app` and so ships 1.3 MB of Javascript and 391 KB of CSS to a manager's phone
 * to render one card and a button. Everything asserted here protects that, because the way this
 * gets undone is not a bug — it is somebody adding `@extends` to get a nav bar back.
 */
class FastAuctionTeamScreenTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function biddingScreen(): array
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'status' => 'running',
            'max_budget_per_team' => 100_000_000,
            'bid_rules' => [['from' => 0, 'to' => 200_000_000, 'increment' => 100_000]],
        ]);

        $team = $this->makeTeam($org, 'Strikers', $tournament);
        $manager = $this->makePlainUser($org);
        $team->users()->attach($manager->id, ['role' => 'Owner']);

        $player = $this->makeAuctionPlayer($auction, [
            'status' => 'on_auction',
            'current_price' => 1_000_000,
        ]);

        return compact('org', 'tournament', 'auction', 'team', 'manager', 'player');
    }

    #[Test]
    public function a_team_manager_can_open_the_fast_screen(): void
    {
        $ctx = $this->biddingScreen();

        $page = $this->actingAs($ctx['manager'])
            ->get(route('team.auction.bidding.fast', $ctx['auction']))
            ->assertOk();

        // The mount point and the screen selector the client reads.
        $page->assertSee('id="fast-auction"', false);
        $page->assertSee('data-screen="team-bidding"', false);

        // The snapshot is inlined, so the first paint costs no requests.
        $page->assertSee('id="fast-auction-boot"', false);
        $page->assertSee($ctx['team']->name);
        $page->assertSee($ctx['auction']->name);
    }

    #[Test]
    public function the_host_page_does_not_ship_the_admin_bundle(): void
    {
        $ctx = $this->biddingScreen();

        $html = $this->actingAs($ctx['manager'])
            ->get(route('team.auction.bidding.fast', $ctx['auction']))
            ->assertOk()
            ->getContent();

        /*
         * This is the assertion the whole module exists for. `resources/js/app.js` is 1.3 MB and
         * `resources/css/app.css` is 391 KB; either appearing here means someone reached for
         * `@extends('backend.layouts.app')` and the exercise is over.
         */
        $this->assertStringNotContainsString('resources/js/app.js', $html);
        $this->assertStringNotContainsString('resources/css/app.css', $html);
        $this->assertStringNotContainsString('viteReactRefresh', $html);

        // And it must ship its own two entries instead.
        $this->assertStringContainsString('fast-auction', $html);
    }

    #[Test]
    public function the_snapshot_answers_with_the_same_shape_the_old_screen_polls(): void
    {
        $ctx = $this->biddingScreen();

        /*
         * Delegated to tick() rather than reimplemented, so the two screens cannot drift into
         * disagreeing about the price or the purse. If this shape ever diverges from
         * /api/tick, that delegation has been broken.
         */
        $fast = $this->actingAs($ctx['manager'])
            ->getJson(route('team.auction.bidding.fast-snapshot', $ctx['auction']))
            ->assertOk()
            ->json();

        $old = $this->actingAs($ctx['manager'])
            ->getJson(route('team.auction.bidding.api.tick', $ctx['auction']))
            ->assertOk()
            ->json();

        $this->assertSame(array_keys($old), array_keys($fast));
        $this->assertSame($old['purse']['team_id'], $fast['purse']['team_id']);
    }

    #[Test]
    public function somebody_with_no_team_is_refused_rather_than_shown_an_empty_shell(): void
    {
        $ctx = $this->biddingScreen();

        // A logged-in user holding no team in this tournament. Rendering the shell would only
        // fail again on its first fetch, so the page itself refuses.
        $this->actingAs($this->makePlainUser($ctx['org']))
            ->get(route('team.auction.bidding.fast', $ctx['auction']))
            ->assertForbidden();
    }

    #[Test]
    public function the_way_back_to_the_classic_screen_is_always_on_the_page(): void
    {
        $ctx = $this->biddingScreen();

        /*
         * Not conditional on a flag. The old screen has run every auction so far, and an
         * organizer whose room is misbehaving must be one tap from it — mid-lot, without
         * anybody editing a URL.
         */
        $html = $this->actingAs($ctx['manager'])
            ->get(route('team.auction.bidding.fast', $ctx['auction']))
            ->assertOk()
            ->getContent();

        // Read the boot blob rather than grepping the HTML: @json escapes the slashes in a URL,
        // so a plain string match would fail on a page that is perfectly correct.
        $boot = $this->bootBlob($html);

        $this->assertSame(
            route('team.auction.bidding.show', $ctx['auction']),
            $boot['urls']['classic'] ?? null
        );
    }

    /**
     * The JSON the server inlined for the client.
     *
     * @return array<string, mixed>
     */
    private function bootBlob(string $html): array
    {
        $this->assertMatchesRegularExpression(
            '/id="fast-auction-boot"[^>]*>(.+?)<\/script>/s',
            $html,
            'the page must inline its snapshot'
        );

        preg_match('/id="fast-auction-boot"[^>]*>(.+?)<\/script>/s', $html, $m);

        return json_decode(html_entity_decode($m[1]), true) ?? [];
    }
}

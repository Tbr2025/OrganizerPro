<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * The lean LED wall.
 *
 * The classic wall had already escaped the admin bundle — it is standalone with no `@vite` — so
 * what this buys is different: precompiled CSS instead of `cdn.tailwindcss.com`, which compiles
 * stylesheets in the browser on every load, on the venue PC driving the projector.
 *
 * The assertion that matters most here is the absence of `Set-Cookie` on the snapshot. Cloudflare
 * will not cache a response that carries one, and forcing it would hand one visitor's session to
 * every other visitor. Nothing in the suite asserted that for the three existing feeds either, so
 * this covers the pattern as well as the new endpoint.
 */
class FastAuctionWallTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function wallAuction(): array
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'status' => 'running',
            'bid_rules' => [['from' => 0, 'to' => 200_000_000, 'increment' => 100_000]],
        ]);
        $team = $this->makeTeam($org, 'Titans', $tournament);

        $this->makeAuctionPlayer($auction, ['status' => 'on_auction', 'current_price' => 1_200_000]);
        $this->makeAuctionPlayer($auction, [
            'status' => 'sold',
            'sold_to_team_id' => $team->id,
            'final_price' => 4_500_000,
        ]);

        return compact('org', 'tournament', 'auction', 'team');
    }

    #[Test]
    public function the_wall_needs_no_login(): void
    {
        $ctx = $this->wallAuction();

        // A projector in a hall does not log in, and neither does an OBS browser source.
        $this->get(route('public.auction.fast-wall', $ctx['auction']))
            ->assertOk()
            ->assertSee('data-screen="wall"', false)
            ->assertSee('id="fast-auction-boot"', false);
    }

    #[Test]
    public function the_snapshot_is_public_and_emits_no_session_cookie(): void
    {
        $ctx = $this->wallAuction();

        $response = $this->getJson(route('public.auction.fast-wall-snapshot', $ctx['auction']))
            ->assertOk();

        $response->assertJsonStructure(['active', 'sold', 'soldTotal']);

        /*
         * The whole reason this route sits inside the session-stripped group. A `Set-Cookie` here
         * makes the response uncacheable at the CDN — and every screen in the hall asks for it.
         */
        $this->assertNull(
            $response->headers->get('Set-Cookie'),
            'the wall snapshot must not start a session: Cloudflare will not cache a response '
            . 'carrying Set-Cookie, and every screen in the hall polls this'
        );
    }

    #[Test]
    public function the_wall_does_not_ship_the_admin_bundle_or_the_tailwind_cdn(): void
    {
        $ctx = $this->wallAuction();

        $html = $this->get(route('public.auction.fast-wall', $ctx['auction']))->assertOk()->getContent();

        $this->assertStringNotContainsString('resources/js/app.js', $html);
        $this->assertStringNotContainsString('resources/css/app.css', $html);

        // The classic wall's runtime Tailwind compile is the thing this screen exists to avoid.
        $this->assertStringNotContainsString('cdn.tailwindcss.com', $html);
        $this->assertStringContainsString('fast-auction', $html);
    }

    #[Test]
    public function the_classic_wall_is_untouched(): void
    {
        $ctx = $this->wallAuction();

        // The new URL is additive. The wall that has run every auction so far still answers.
        $this->get(route('public.auction.live', $ctx['auction']))->assertOk();
    }

    #[Test]
    public function the_sold_board_is_capped_but_says_the_real_total(): void
    {
        $ctx = $this->wallAuction();

        for ($i = 0; $i < 20; $i++) {
            $this->makeAuctionPlayer($ctx['auction'], [
                'status' => 'sold',
                'sold_to_team_id' => $ctx['team']->id,
                'final_price' => 100_000,
            ]);
        }

        $data = $this->getJson(route('public.auction.fast-wall-snapshot', $ctx['auction']))
            ->assertOk()
            ->json();

        // Capped for the wall, which shows the recent end — but the count is honest, so the
        // screen never implies the evening sold fewer players than it did.
        $this->assertLessThanOrEqual(12, count($data['sold']));
        $this->assertSame(21, $data['soldTotal']);
    }
}

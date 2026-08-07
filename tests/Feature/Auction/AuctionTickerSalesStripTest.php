<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * The broadcast ticker's Recent Sales strip.
 *
 * Two problems lived here. The marquee built its track as `html + html` unconditionally —
 * the second copy is what it scrolls into — but with only a handful of sales the doubled
 * content still fits on screen and every entry was simply visible twice, side by side. And
 * it rebuilt that DOM on every two-second poll, replacing the track mid-scroll.
 *
 * Separately, nothing on this page was escaped. Player names arrive through PUBLIC
 * tournament registration, so a name containing markup was stored XSS on a page that is open
 * to anyone and usually running unattended on a projector or an OBS source.
 */
class AuctionTickerSalesStripTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    private function ticker(int $auctionId): string
    {
        return (string) $this->get("/auction/{$auctionId}/ticker")->assertOk()->getContent();
    }

    #[Test]
    public function everything_interpolated_into_the_page_is_escaped(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);

        $html = $this->ticker($auction->id);

        $this->assertStringContainsString('function esc(', $html, 'the ticker needs an escape helper');

        /*
         * Named fields, not a general scan.
         *
         * Two more general approaches were tried and both were VACUOUS — they passed with
         * the escaping deliberately removed. Anchoring on `innerHTML =` missed the sales
         * strip, which composes rows into a `const html` first; widening to "template
         * literals containing HTML" failed because those literals nest backticks, so the
         * regex never matched the outer one. A whole-page scan for bare `${a.b}` is the
         * other extreme and flags numbers that only ever reach textContent.
         *
         * So this names the values that are actually attacker-controlled — player and team
         * names come straight from public tournament registration — and asserts each one is
         * never interpolated raw. Narrow, but it genuinely fails when the escaping is
         * removed, which the general versions did not.
         */
        $userControlled = [
            's.player_name', 's.team_name', 's.team_logo',
            't.name', 't.short_name', 't.logo',
        ];

        // Every ${...} whose body mentions one of those fields must run it through esc().
        // Matching the whole body rather than an exact `${field}` matters: the teams row
        // reads `${esc(t.short_name || t.name)}`, so an exact-match check sails past it.
        preg_match_all('/\$\{([^{}]*)\}/', $html, $bodies);

        $bare = [];
        foreach ($bodies[1] as $body) {
            foreach ($userControlled as $field) {
                if (str_contains($body, $field) && ! str_contains($body, 'esc(')) {
                    $bare[] = $field;
                }
            }
        }
        $bare = array_values(array_unique($bare));

        $this->assertSame(
            [],
            $bare,
            'unescaped values reach innerHTML on a public page: ' . implode(', ', $bare)
        );
    }

    #[Test]
    public function the_strip_only_duplicates_itself_when_it_actually_overflows(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);

        $html = $this->ticker($auction->id);

        // The unconditional double is what made two sales render as four.
        $this->assertStringNotContainsString(
            'track.innerHTML = html + html;' . "\n" . '        salesWidth',
            $html,
            'the track must not be doubled before measuring whether one copy overflows'
        );

        $this->assertStringContainsString('const oneCopy = track.scrollWidth;', $html);
        $this->assertStringContainsString('if (oneCopy > visible) {', $html);
    }

    #[Test]
    public function the_strip_is_not_rebuilt_when_nothing_has_been_sold_since(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);

        $html = $this->ticker($auction->id);

        // Rebuilding every 2s replaced the track mid-scroll, which is the stutter.
        $this->assertStringContainsString('if (signature === salesSignature) return;', $html);
    }

    #[Test]
    public function a_sale_reaches_the_feed_with_the_player_and_team(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id]);
        $team = $this->makeTeam($org, 'Alpha Strikers', $tournament);

        $player = $this->makePlayer($org, ['name' => 'Sold Sam']);
        $this->makeAuctionPlayer($auction, [
            'player' => $player,
            'status' => 'sold',
            'sold_to_team_id' => $team->id,
            'final_price' => 4_200_000,
        ]);

        $this->getJson("/auction/{$auction->id}/ticker-feed")
            ->assertOk()
            ->assertJsonPath('recent_sales.0.player_name', 'Sold Sam')
            ->assertJsonPath('recent_sales.0.team_name', 'Alpha Strikers')
            // The id is what the client fingerprints on to decide whether to rebuild.
            ->assertJsonStructure(['recent_sales' => [['id', 'player_name', 'team_name', 'price']]]);
    }
}

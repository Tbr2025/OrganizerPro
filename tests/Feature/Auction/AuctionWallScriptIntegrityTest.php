<?php

declare(strict_types=1);

namespace Tests\Feature\Auction;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesAuctionScenario;
use Tests\TestCase;

/**
 * The LED wall's inline script must not call a function that does not exist.
 *
 * This is not a hypothetical. `syncClock()` called `startClockTick()`, which was never
 * defined — a ReferenceError on every single poll. The poll's own `.catch()` swallowed it,
 * and because it threw AFTER the clock rendered but BEFORE the player-card branch, the wall
 * showed a live countdown over a "waiting for auction" screen and the player card could never
 * appear at all. The page still returned 200, the feed was correct, and every existing test
 * passed.
 *
 * There is no browser test layer here, so nothing executed that script. `node --check` would
 * not have helped either — the file is syntactically valid. This walks the declarations
 * instead, which is exactly the check that was missing.
 */
class AuctionWallScriptIntegrityTest extends TestCase
{
    use CreatesAuctionScenario;
    use RefreshDatabase;

    /**
     * Names the browser (or a CDN script this page loads) provides.
     *
     * Deliberately a generous allow-list: a false positive here fails a good build, so
     * anything not obviously ours is permitted.
     */
    private const PROVIDED = [
        // language / control flow that looks like a call
        'if', 'for', 'while', 'switch', 'catch', 'return', 'typeof', 'function', 'do', 'else',
        'new', 'await', 'case', 'in', 'of', 'delete', 'void', 'yield', 'super',
        // globals & builtins
        'console', 'document', 'window', 'setTimeout', 'setInterval', 'clearTimeout',
        'clearInterval', 'requestAnimationFrame', 'cancelAnimationFrame', 'fetch',
        'Number', 'String', 'Boolean', 'Array', 'Object', 'Math', 'JSON', 'Date', 'Promise',
        'parseInt', 'parseFloat', 'isNaN', 'isFinite', 'encodeURIComponent', 'decodeURIComponent',
        'Map', 'Set', 'WeakMap', 'RegExp', 'Error', 'Intl', 'localStorage', 'sessionStorage',
        'alert', 'confirm', 'getComputedStyle', 'matchMedia', 'CustomEvent', 'Event',
        'IntersectionObserver', 'ResizeObserver', 'MutationObserver', 'AbortController',
        'structuredClone', 'queueMicrotask', 'btoa', 'atob',
        // loaded from CDN by this page
        // html2canvas is loaded only on a ?card= page, where the download is done in the
        // browser rather than by a server-side Chrome; guarded by @isset($cardPayload).
        'Echo', 'Pusher', 'confetti', 'html2canvas',
    ];

    #[Test]
    public function every_function_the_wall_calls_is_defined(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'status' => 'running',
        ]);
        $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);

        $html = $this->get(route('public.auction.live', $auction))->assertOk()->getContent();

        $script = $this->inlineScript((string) $html);
        $this->assertNotSame('', $script, 'the wall must ship an inline script');

        $declared = $this->declaredNames($script);
        $called = $this->calledNames($script);

        $missing = array_values(array_diff(
            $called,
            $declared,
            self::PROVIDED
        ));

        // Method calls (obj.foo()) are excluded by the call pattern, so anything left is a
        // bare identifier this script is expected to own.
        $this->assertSame(
            [],
            $missing,
            "The LED wall calls function(s) that are never defined: " . implode(', ', $missing)
                . ". A ReferenceError here is swallowed by the poll's catch(), so the wall "
                . 'silently freezes on its waiting screen for the whole auction.'
        );
    }

    /** The page's own script block — the one carrying the poll, not a CDN <script src>. */
    private function inlineScript(string $html): string
    {
        preg_match_all('/<script(?![^>]*\bsrc=)[^>]*>(.*?)<\/script>/s', $html, $m);

        // The wall's logic is the largest inline block; smaller ones are config shims.
        $blocks = $m[1] ?? [];
        usort($blocks, fn ($a, $b) => strlen($b) <=> strlen($a));

        return $blocks[0] ?? '';
    }

    /** @return list<string> */
    private function declaredNames(string $js): array
    {
        $names = [];

        // function foo(...)  /  async function foo(...)
        preg_match_all('/\bfunction\s+([A-Za-z_$][\w$]*)\s*\(/', $js, $fn);
        $names = array_merge($names, $fn[1]);

        /*
         * Object-literal shorthand methods — `finish(playerData) { ... }`, as used by
         * shuffleController. Without this they are invisible as declarations while their own
         * declaration line reads as a call site, so every one of them is a false positive.
         */
        preg_match_all('/(?:^|[,{;])\s*(?:async\s+)?([A-Za-z_$][\w$]*)\s*\([^()]*\)\s*\{/m', $js, $methods);
        $names = array_merge($names, $methods[1]);

        // const/let/var foo = ... (arrow functions and everything else)
        preg_match_all('/\b(?:const|let|var)\s+([A-Za-z_$][\w$]*)\s*=/', $js, $vars);
        $names = array_merge($names, $vars[1]);

        // Destructured declarations: const { a, b } = ... / const [a, b] = ...
        preg_match_all('/\b(?:const|let|var)\s*[\{\[]([^\}\]]*)[\}\]]\s*=/', $js, $destr);
        foreach ($destr[1] as $group) {
            foreach (preg_split('/[,\s:]+/', $group) ?: [] as $piece) {
                if (preg_match('/^[A-Za-z_$][\w$]*$/', $piece)) {
                    $names[] = $piece;
                }
            }
        }

        // Named function parameters are in scope inside their body; treat them as declared
        // rather than trying to model scope, which would only add false positives.
        preg_match_all('/\(([^()]*)\)\s*(?:=>|\{)/', $js, $params);
        foreach ($params[1] as $group) {
            foreach (preg_split('/[,\s]+/', $group) ?: [] as $piece) {
                $piece = ltrim($piece, '.');
                if (preg_match('/^[A-Za-z_$][\w$]*$/', $piece)) {
                    $names[] = $piece;
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Bare-identifier call sites.
     *
     * `(?<![\w$.])` excludes `obj.method()` — a missing property is a different problem and
     * cannot be judged statically.
     *
     * @return list<string>
     */
    private function calledNames(string $js): array
    {
        // Strip comments and strings first, so prose and template literals cannot look like
        // calls. Order matters: comments before strings.
        $clean = preg_replace('#/\*.*?\*/#s', ' ', $js);
        $clean = preg_replace('#(?<![:\'"])//[^\n]*#', ' ', (string) $clean);
        $clean = preg_replace('/`(?:\\\\.|[^`\\\\])*`/s', '``', (string) $clean);
        $clean = preg_replace('/\'(?:\\\\.|[^\'\\\\])*\'/s', "''", (string) $clean);
        $clean = preg_replace('/"(?:\\\\.|[^"\\\\])*"/s', '""', (string) $clean);

        preg_match_all('/(?<![\w$.])([A-Za-z_$][\w$]*)\s*\(/', (string) $clean, $m);

        return array_values(array_unique($m[1] ?? []));
    }

    #[Test]
    public function the_previous_players_result_is_cleared_before_the_shuffle(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'status' => 'running',
        ]);
        $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);

        $html = $this->get(route('public.auction.live', $auction))->assertOk()->getContent();
        $js = $this->inlineScript((string) $html);

        /*
         * A timing bug with no visible seam in the code: on a new player the poll calls
         * shuffleController.start() and RETURNS, so updatePlayerCard() — which is where the
         * sold badge, the winning team's logo, the sold glow and the result banner are
         * cleared — does not run until reveal() finishes about four seconds later. For those
         * four seconds the previous player's result sat on top of the shuffle and then on
         * top of the next player.
         *
         * Asserted structurally because there is no browser layer here: clearOutcomeState()
         * must exist, and start() must call it. Anyone removing that call gets a failure
         * rather than a hall watching two players at once.
         */
        $this->assertStringContainsString(
            'function clearOutcomeState()',
            $js,
            'the wall needs a clear that works outside updatePlayerCard'
        );

        $start = strpos($js, 'start(playerData, namePool)');
        $this->assertNotFalse($start, 'shuffleController.start must still exist');

        /*
         * Early in start(), before any of the animation work. The window is generous
         * because the call carries a comment explaining why it is there — the point is
         * that the clear happens in start() at all, not that it lands on a given line.
         */
        $this->assertStringContainsString(
            'clearOutcomeState()',
            substr($js, $start, 1500),
            'start() must clear the previous result before covering the stage'
        );
    }

    #[Test]
    public function a_completed_auction_keeps_showing_the_winner(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, [
            'tournament_id' => $tournament->id,
            'status' => 'completed',
        ]);
        $team = $this->makeTeam($org, 'Squad of Cuba', $tournament);
        $player = $this->makePlayer($org, ['name' => 'Adil Rashid']);

        $this->makeAuctionPlayer($auction, [
            'player' => $player,
            'status' => 'sold',
            'sold_to_team_id' => $team->id,
            'final_price' => 40_000_000,
        ]);

        /*
         * The wall used to return early on a completed status and put up a full-screen
         * "auction complete", so the hall lost the player who had just been won while the
         * organizer was still finishing the sale on the panel. The feed must still carry the
         * last result for the card to hold.
         */
        $this->getJson("/auction/{$auction->id}/active-player")
            ->assertOk()
            ->assertJsonPath('auction_status', 'completed')
            ->assertJsonPath('lastActionPlayer.status', 'sold')
            ->assertJsonPath('lastActionPlayer.sold_to_team.name', 'Squad of Cuba');

        $html = (string) $this->get(route('public.auction.live', $auction))->assertOk()->getContent();

        // The completed screen is now reserved for having nothing at all to show.
        $this->assertStringContainsString("&& ! data?.auctionPlayer", $html);
        $this->assertStringContainsString("&& ! data?.lastActionPlayer", $html);
    }

    #[Test]
    public function the_card_never_shrinks_below_its_designed_canvas(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id, 'status' => 'running']);
        $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);

        $html = (string) $this->get(route('public.auction.live', $auction))->assertOk()->getContent();

        /*
         * body is a flex container, so the card is a flex ITEM — and a flex item's default
         * `flex-shrink: 1` let it collapse to the viewport width. At exactly 1601px nothing
         * shrank and the wall looked right, which is why this hid: zoom in and the box narrowed
         * while every element stayed pinned at its designed offset (up to left:1050px), so the
         * right-hand half was cut off by the overflow rule. The card must keep its layout and be
         * sized only by scaleLive()'s transform, which scales the artwork and the text together.
         */
        $this->assertStringContainsString('flex-shrink: 0;', $html);
        $this->assertMatchesRegularExpression('/min-width:\s*\d+px;/', $html);
    }

    #[Test]
    public function the_stats_header_reads_the_way_it_was_typed(): void
    {
        $org = $this->makeOrganization();
        $tournament = $this->makeTournament($org);
        $auction = $this->makeAuction($org, ['tournament_id' => $tournament->id, 'status' => 'running']);
        $this->makeAuctionPlayer($auction, ['status' => 'on_auction']);

        $html = (string) $this->get(route('public.auction.live', $auction))->assertOk()->getContent();

        /*
         * The wall had `text-transform: uppercase` hardcoded on the header cells, BELOW the rule
         * carrying the template's own transform — so "Matches / wkts / Runs" as typed in the
         * editor always came out "MATCHES / WKTS / RUNS", and no template could ask for anything
         * else. Asserted as an absence because that is what the bug was.
         */
        // Scoped to the header cells: the sold badge and the result banners are uppercase on
        // purpose, so asserting against the whole page would prove nothing.
        $start = strpos($html, '#stats-table-wrap th {');
        $this->assertNotFalse($start, 'the wall must style the stats header');
        $headerRule = substr($html, $start, strpos($html, '}', $start) - $start);

        // The DECLARATION, with its colon — the rule's own comment mentions the property by
        // name to explain why it is absent, and matching that would pass for the wrong reason.
        $this->assertStringNotContainsString('text-transform:', $headerRule);

        // Header and figures are template-driven, so they can be tuned without touching CSS.
        $this->assertStringContainsString('font-size: 0.72em;', $headerRule);
        $this->assertStringContainsString('font-size: 1.15em;', $html);
        $this->assertStringContainsString('font-weight: 700;', $html);
    }
}

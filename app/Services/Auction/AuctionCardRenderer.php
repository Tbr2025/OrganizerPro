<?php

declare(strict_types=1);

namespace App\Services\Auction;

use App\Models\Auction;
use App\Models\AuctionPlayer;
use App\Support\PdfBrowser;
use Illuminate\Support\Str;

/**
 * Renders a player's wall card to a PNG by screenshotting the wall itself.
 *
 * The card is not re-drawn here. The LED wall already lays a player out against the
 * auction's template — background, element positions, fonts, the sold badge — so this points
 * a headless Chrome at that same page in card mode and captures it. One renderer, so a
 * downloaded card and the screen in the hall cannot disagree.
 *
 * The alternative was drawing it with GD from `element_positions`, the way the tournament
 * posters work. That means a second implementation of the same layout, in a different
 * coordinate model (GD's top-left x/y against the template's CSS top/bottom/left), which then
 * has to be kept in agreement with the wall forever. Chrome is already installed and already
 * used for PDFs here, so the cheaper answer is to let the browser do what it is for.
 */
class AuctionCardRenderer
{
    /**
     * Chrome has to fetch the page over HTTP, because the wall pulls its own CSS and fonts
     * from CDNs and builds the card in JavaScript — rendering the HTML string offline would
     * capture an empty document.
     */
    public function render(Auction $auction, AuctionPlayer $auctionPlayer, bool $withResult): string
    {
        $url = route('public.auction.live', $auction) . '?' . http_build_query([
            'card' => $auctionPlayer->id,
            'result' => $withResult ? 1 : 0,
            // Suppress the page's own Download control — see the note beside it in the view.
            'noui' => 1,
        ]);

        $path = tempnam(sys_get_temp_dir(), 'auction-card-') . '.png';

        $canvasWidth = 1601;
        $canvasHeight = 910;

        try {
            PdfBrowser::url($url)
                ->windowSize($canvasWidth, $canvasHeight)
                /*
                 * Chrome fetches this page over HTTP from THIS application, so the app has to be
                 * able to serve a second request while it is still serving this one.
                 *
                 * nginx + PHP-FPM do that by default. `php artisan serve` does NOT — PHP's
                 * built-in server is single-threaded, so the download request occupies it, Chrome's
                 * fetch queues behind itself, and the navigation times out having never been
                 * served. The fix is on the dev server, not here:
                 *
                 *     PHP_CLI_SERVER_WORKERS=4 php artisan serve
                 *
                 * 120s rather than puppeteer's default 30, so a slow first paint (cold Chrome,
                 * remote fonts, a large background) is not mistaken for this deadlock.
                 */
                ->timeout(120)
            /*
             * Wait for the page to say it has finished painting rather than for a fixed delay.
             * The card is built in JS after the fonts and background load, so a timeout either
             * cuts the image short or adds seconds to every download; card mode sets
             * data-card-ready on <body> when it is done.
             */
                ->waitForFunction("document.body.getAttribute('data-card-ready') === '1'", null, 15000)
                /*
                 * Capture the CARD, not the viewport.
                 *
                 * A full-viewport shot included the page's own "Download PNG" control, which
                 * sits outside the card so that the browser-side capture excludes it — and
                 * that put a green button in the corner of every server-rendered card. Cropping
                 * to the element also means the file is exactly the canvas, whatever window
                 * size Chrome happened to open with.
                 */
                ->select('#card-container')
                ->save($path);
        } catch (\Throwable $e) {
            @unlink($path);

            /*
             * Say which of the two failures this is, because they look identical in the raw
             * puppeteer output and have completely different fixes: one is a dev server that
             * cannot answer a second request, the other is a card that will not paint.
             *
             * Any timeout counts, not just a navigation one. The deadlock actually surfaces as
             * `TimeoutError: Waiting failed: 15000ms exceeded` from waitForFunction — Chrome DOES
             * reach the page, it just never gets served, so data-card-ready never appears. Only
             * 'Navigation timeout' was matched, so the one message that explains this never fired
             * and every operator got a bare "could not be rendered" with the cause sitting
             * unexplained in a log.
             */
            $message = $e->getMessage();
            $timedOut = str_contains($message, 'Navigation timeout')
                || str_contains($message, 'TimeoutError')
                || str_contains($message, 'Waiting failed');

            $hint = $timedOut
                ? ' The card page could not be loaded in time. If this is a local `php artisan serve`,'
                    . ' it serves one request at a time and cannot answer Chrome while it is busy'
                    . ' answering this one — restart it as `PHP_CLI_SERVER_WORKERS=4 php artisan serve`.'
                : '';

            throw new \RuntimeException('Could not render the card for ' . ($auctionPlayer->player->name ?? 'this player') . '.' . $hint, 0, $e);
        }

        return $path;
    }

    /**
     * A filename a person can read in a folder of two hundred of them.
     *
     * The outcome suffix comes from the PLAYER, not from $withResult.
     *
     * $withResult is a property of the request — "draw the result overlay" — and taking the
     * suffix from it named every file in a mixed export `-sold`, unsold players included. In a
     * zip of two hundred posters the filename is the only thing distinguishing them, so that
     * was not a cosmetic slip: it made the export unsortable and quietly wrong.
     *
     * A player who has not been called yet gets no suffix at all rather than `-unsold`, which
     * would claim an outcome the auction has not reached.
     */
    public function filename(AuctionPlayer $auctionPlayer, bool $withResult): string
    {
        /*
         * player-team-pool-id.png
         *
         * A zip of three hundred of these is opened in a file manager and sorted by name, so
         * every part somebody would search for has to be IN the name: who it is, which team ended
         * up with them, and which pool they came out of. It was `042-lungi-ngidi-sold.png`, which
         * put the lot number first — an ordering nobody looks things up by — and named the team
         * nowhere at all.
         *
         * The player id closes it rather than a random four digits: two players can share a name,
         * and a random suffix would make the same player's card a different file on every export,
         * so a re-run could not overwrite the old one.
         */
        $slug = fn (?string $value, string $fallback) => Str::slug((string) $value) ?: $fallback;

        $name = $slug($auctionPlayer->player->name ?? null, 'player');

        // The buying team, or the outcome when there is no team to name.
        $team = $auctionPlayer->soldToTeam
            ? $slug($auctionPlayer->soldToTeam->name, 'team')
            : (in_array($auctionPlayer->status, ['unsold', 'passed', 'skipped'], true) ? 'unsold' : 'unassigned');

        /*
         * The pool they came FROM. An unsold player has been moved to the shared pile, so `pool`
         * would read "unsold" for all of them and lose the one thing that distinguishes them —
         * `source_pool_id` is what remembers.
         */
        $pool = $slug(
            $auctionPlayer->sourcePool?->name ?? $auctionPlayer->pool?->name ?? null,
            'no-pool'
        );

        return sprintf('%s-%s-%s-%d.png', $name, $team, $pool, $auctionPlayer->player_id);
    }
}

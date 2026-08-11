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
             */
            $hint = str_contains($e->getMessage(), 'Navigation timeout')
                ? ' The card page could not be loaded. If this is a local `php artisan serve`,'
                    . ' it serves one request at a time and cannot answer Chrome while it is busy'
                    . ' answering this one — restart it as `PHP_CLI_SERVER_WORKERS=4 php artisan serve`.'
                : '';

            throw new \RuntimeException('Could not render the card for ' . ($auctionPlayer->player->name ?? 'this player') . '.' . $hint, 0, $e);
        }

        return $path;
    }

    /** A filename a person can read in a folder of two hundred of them. */
    public function filename(AuctionPlayer $auctionPlayer, bool $withResult): string
    {
        $name = Str::slug($auctionPlayer->player->name ?? 'player') ?: 'player';
        $lot = $auctionPlayer->lot_number ? str_pad((string) $auctionPlayer->lot_number, 3, '0', STR_PAD_LEFT) : 'x';

        return sprintf('%s-%s%s.png', $lot, $name, $withResult ? '-sold' : '');
    }
}

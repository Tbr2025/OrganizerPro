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

        PdfBrowser::url($url)
            ->windowSize($canvasWidth, $canvasHeight)
            /*
             * Wait for the page to say it has finished painting rather than for a fixed delay.
             * The card is built in JS after the fonts and background load, so a timeout either
             * cuts the image short or adds seconds to every download; card mode sets
             * data-card-ready on <body> when it is done.
             */
            ->waitForFunction("document.body.getAttribute('data-card-ready') === '1'", null, 15000)
            ->save($path);

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

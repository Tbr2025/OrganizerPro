<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\AuctionTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Fast Auction's public display — the LED wall, on its own URL.
 *
 * The existing wall (`resources/views/public/auction/live.blade.php`, 5,907 lines) is untouched
 * and stays the default. It had already escaped the admin bundle — it is standalone, with no
 * `@vite` — so the win here is not bundle size. It is two other things:
 *
 *   - It pulls Tailwind from `cdn.tailwindcss.com`, which **compiles CSS in the browser on every
 *     load**. On the venue PC driving a projector that is real work before anything paints. This
 *     ships 10 KB of precompiled CSS instead.
 *   - It is 267 KB of HTML. This is a few KB plus a 28 KB bundle it shares with the bidding
 *     screen, already cached if that screen has been opened.
 *
 * Both endpoints are deliberately UNAUTHENTICATED and the snapshot is session-stripped, for the
 * same reasons the three existing feeds are (see the comment above them in routes/web.php): every
 * screen in the hall polls them, they are identical for every viewer, and a response carrying
 * `Set-Cookie` cannot be cached at the CDN.
 */
class FastAuctionPublicController extends Controller
{
    public function wall(Auction $auction): View
    {
        /*
         * The SAME template the classic wall uses, resolved the same way — the organizer's own
         * override, then the auction's explicit pick, then one bound to it, then the default.
         *
         * This is the whole point of the design layer: the wall the organizer laid out in the
         * editor is the wall that appears, at their coordinates, not at a layout invented here.
         * The element keys below are the classic wall's keys, so an existing live_display
         * template positions this screen identically without being touched.
         *
         * An HTML-mode template owns the whole document and is a different thing entirely; those
         * are left to the classic wall, which already renders them under their own CSP.
         */
        $template = AuctionTemplate::overrideFor($auction, 'live_display', request('template'))
            ?? AuctionTemplate::resolveFor($auction, 'live_display');

        $htmlMode = (bool) $template?->isHtmlMode();

        return view('fast-auction.wall', [
            'boot' => [
                'screen' => 'wall',
                'auctionId' => $auction->id,
                'auctionName' => $auction->name,
                'tournamentName' => $auction->tournament->name ?? null,
                'amountUnit' => $auction->amountUnitConfig(),
                'snapshot' => $this->payload($auction),
                'design' => [
                    // Absolute pixels on the template's own canvas. The client scales the whole
                    // canvas to the viewport rather than re-flowing anything, so a design holds
                    // its proportions on a 1080p projector and a laptop alike.
                    'positions' => $htmlMode ? [] : ($template?->element_positions ?? AuctionTemplate::getDefaultPositions()),
                    'canvasWidth' => $template?->canvas_width ?? 1601,
                    'canvasHeight' => $template?->canvas_height ?? 910,
                    'background' => $template
                        ? $template->background_url
                        : ($auction->background_image_url ?? asset('images/player-card.jpeg')),
                    'soldBadge' => $template?->sold_badge_url,
                    // An HTML template cannot be honoured here; say so rather than silently
                    // showing a different design from the one the organizer chose.
                    'htmlMode' => $htmlMode,
                ],
                'urls' => [
                    'snapshot' => route('public.auction.fast-wall-snapshot', $auction),
                    // The wall that has run every auction so far, one click away.
                    'classic' => route('public.auction.live', $auction),
                ],
            ],
        ]);
    }

    public function snapshot(Auction $auction): JsonResponse
    {
        return response()->json($this->payload($auction));
    }

    /**
     * Everything the wall draws, composed from the existing public feeds.
     *
     * Both of those are served through `cachedFeed()` on a one-second TTL shared by every viewer,
     * so if the classic wall or the ticker has already asked in this second, this costs two cache
     * reads. Not a third copy of the payload logic — the feeds stay the single source, which is
     * what stops the two walls from ever showing different prices.
     *
     * @return array<string, mixed>
     */
    private function payload(Auction $auction): array
    {
        $public = app(PublicAuctionController::class);

        $active = $public->activePlayer($auction)->getData(true);
        $sold = $public->soldPlayers($auction)->getData(true);

        return [
            'active' => $active,
            // The board grows all evening and the wall wants the recent end of it; the full list
            // stays available on the classic feed for anything that needs every row.
            'sold' => array_slice($sold['soldPlayers'] ?? [], 0, 12),
            'soldTotal' => count($sold['soldPlayers'] ?? []),
            'tournamentLogo' => $sold['tournamentLogo'] ?? null,
        ];
    }
}

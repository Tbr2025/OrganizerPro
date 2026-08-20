<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Auction;
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
        return view('fast-auction.wall', [
            'boot' => [
                'screen' => 'wall',
                'auctionId' => $auction->id,
                'auctionName' => $auction->name,
                'tournamentName' => $auction->tournament->name ?? null,
                'amountUnit' => $auction->amountUnitConfig(),
                'snapshot' => $this->payload($auction),
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

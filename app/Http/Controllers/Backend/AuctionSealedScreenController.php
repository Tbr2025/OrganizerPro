<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * What the LED wall shows while bidding is private.
 *
 * A sealed round is the longest single thing a hall looks at: the price freezes, the chips go
 * quiet, and the wall carries one screen for as long as the teams take to submit. That screen was
 * built from whatever logos the auction already had and two fixed English sentences — a sensible
 * default and a poor ceiling for an organizer running a branded evening, or one not running in
 * English.
 *
 * Deliberately three fields. Anything more would be a layout editor, and this application already
 * has one of those for the card itself.
 */
class AuctionSealedScreenController extends Controller
{
    public function index(Auction $auction): View
    {
        $this->authorize('auction.edit');

        return view('backend.pages.auctions.sealed-screen', [
            'auction' => $auction,
            'breadcrumbs' => [
                'title' => __('Sealed Bid Screen'),
                'items' => [
                    ['label' => __('Auctions'), 'url' => route('admin.auctions.index')],
                    ['label' => $auction->name, 'url' => route('admin.auctions.show', $auction)],
                ],
            ],
        ]);
    }

    public function update(Request $request, Auction $auction): RedirectResponse
    {
        $this->authorize('auction.edit');

        $data = $request->validate([
            /*
             * 4MB, like the sponsor artwork: this is drawn at 170px tall across a wall, so a
             * compressed thumbnail looks worse here than almost anywhere else — but a 20MB export
             * would be pulled by every screen on the venue's uplink.
             */
            'sealed_logo' => 'nullable|image|max:4096',
            'sealed_heading' => 'nullable|string|max:80',
            'sealed_message' => 'nullable|string|max:160',
        ]);

        $attributes = [
            /*
             * Blank means "use the default", not "show nothing".
             *
             * An empty headline on the one screen a hall stares at for minutes is never what
             * somebody meant by clearing a box — the accessors fall back to the built-in wording,
             * and null is how they know to.
             */
            'sealed_heading' => trim((string) ($data['sealed_heading'] ?? '')) ?: null,
            'sealed_message' => trim((string) ($data['sealed_message'] ?? '')) ?: null,
        ];

        if ($request->hasFile('sealed_logo')) {
            // The old file goes with it — an auction that has had three logos should not be
            // keeping three of them on disk.
            $this->forgetLogo($auction);

            $attributes['sealed_logo'] = $request->file('sealed_logo')->store('auction-sealed', 'public');
        }

        $auction->update($attributes);

        return back()->with('success', __('Sealed bid screen updated.'));
    }

    /** Remove the uploaded mark and fall back to the auction's own logo. */
    public function removeLogo(Auction $auction): RedirectResponse
    {
        $this->authorize('auction.edit');

        $this->forgetLogo($auction);
        $auction->update(['sealed_logo' => null]);

        return back()->with('success', __('Logo removed — the auction or tournament logo will be used.'));
    }

    private function forgetLogo(Auction $auction): void
    {
        if ($auction->sealed_logo) {
            Storage::disk('public')->delete($auction->sealed_logo);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\AuctionAd;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Sponsor artwork for an auction's public screens.
 *
 * Deliberately a plain upload-and-order screen rather than an editor. The artwork arrives from a
 * designer as a finished image; anything this could offer to change about it would be a second,
 * worse version of the tool it was made in.
 */
class AuctionAdController extends Controller
{
    public function index(Auction $auction): View
    {
        $this->authorize('auction.edit');

        return view('backend.pages.auctions.ads', [
            'auction' => $auction,
            'slides' => $auction->ads()->where('kind', AuctionAd::KIND_SLIDE)->get(),
            'sponsors' => $auction->ads()->where('kind', AuctionAd::KIND_SPONSOR)->get(),
            'breadcrumbs' => [
                'title' => __('Ads & Sponsors'),
                'items' => [
                    ['label' => __('Auctions'), 'url' => route('admin.auctions.index')],
                    ['label' => $auction->name, 'url' => route('admin.auctions.show', $auction)],
                ],
            ],
        ]);
    }

    public function store(Request $request, Auction $auction): RedirectResponse
    {
        $this->authorize('auction.edit');

        $data = $request->validate([
            'kind' => 'required|in:' . implode(',', AuctionAd::kinds()),
            /*
             * Several at once, because sponsors arrive as a folder rather than one at a time.
             * 4MB each: these are drawn across a wall, so a compressed thumbnail looks worse
             * there than anywhere else — but a 20MB export would be pulled by every screen on
             * the venue's uplink, which is the constraint that decides it.
             */
            'images' => 'required|array|min:1|max:20',
            'images.*' => 'image|max:4096',
            'caption' => 'nullable|string|max:120',
        ]);

        // Appended, not prepended: an organizer adding a sponsor mid-event expects them at the
        // end of the rotation, not jumped to the front of artwork already agreed.
        $next = (int) $auction->ads()->where('kind', $data['kind'])->max('sort_order');

        foreach ($request->file('images') as $image) {
            $auction->ads()->create([
                'kind' => $data['kind'],
                'image_path' => $image->store('auction-ads', 'public'),
                'caption' => $data['caption'] ?? null,
                'sort_order' => ++$next,
                'is_active' => true,
            ]);
        }

        return back()->with('success', __('Artwork uploaded.'));
    }

    /** Reorder, rename or switch one off without deleting it. */
    public function update(Request $request, Auction $auction, AuctionAd $ad): RedirectResponse
    {
        $this->authorize('auction.edit');
        abort_unless($ad->auction_id === $auction->id, 404);

        $ad->update($request->validate([
            'caption' => 'nullable|string|max:120',
            'sort_order' => 'nullable|integer|min:0|max:999',
            'is_active' => 'nullable|boolean',
        ]));

        return back()->with('success', __('Updated.'));
    }

    public function destroy(Auction $auction, AuctionAd $ad): RedirectResponse
    {
        $this->authorize('auction.edit');
        abort_unless($ad->auction_id === $auction->id, 404);

        // The file goes with the row. Nothing else points at it — each upload is stored under
        // its own generated name — so leaving it would be litter nobody can find again.
        if ($ad->image_path) {
            Storage::disk('public')->delete($ad->image_path);
        }

        $ad->delete();

        return back()->with('success', __('Removed.'));
    }
}

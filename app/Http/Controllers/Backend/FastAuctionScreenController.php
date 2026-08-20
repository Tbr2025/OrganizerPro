<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ActualTeam;
use App\Models\Auction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Fast Auction — the lean screens.
 *
 * These shadow the existing auction screens on their own URLs and change nothing about them. The
 * old ones stay exactly as they are, keep working, and remain the fallback: an organizer whose
 * room is misbehaving should be one link away from the screen that has run every auction so far.
 *
 * What is actually different is the client, not the server. The team bidding page extends
 * `backend.layouts.app` and so ships 1.3 MB of Javascript and 384 KB of CSS to a manager's phone
 * on a venue connection, to render one card and a button. These screens ship their own small
 * bundle, and the first snapshot is inlined into the HTML so the first paint costs no requests.
 *
 * Deliberately NOT a second read API. The snapshot delegates to
 * `AuctionBiddingController::tick()`, which is already the composed one-request payload — the
 * player on the block from the shared one-second cache, the team's own squad, its purse and its
 * sealed state. One source means the old screen and the new one cannot drift into disagreeing
 * about what is true, which is the failure that would actually matter mid-auction.
 */
class FastAuctionScreenController extends Controller
{
    public function teamBidding(Request $request, Auction $auction): View
    {
        $snapshot = $this->teamSnapshot($request, $auction);

        // tick() answers 403 when the viewer holds no team in this tournament. Let that stand
        // rather than rendering a shell that will only fail again on its first fetch.
        if ($snapshot->getStatusCode() !== 200) {
            abort(403, $snapshot->getData(true)['error'] ?? 'You are not assigned to a team in this tournament.');
        }

        $payload = $snapshot->getData(true);
        $team = $this->teamFrom($payload);

        return view('fast-auction.team-bidding', [
            'boot' => [
                'screen' => 'team-bidding',
                'auctionId' => $auction->id,
                'auctionName' => $auction->name,
                'teamName' => $team?->name ?? 'Your team',
                'amountUnit' => $auction->amountUnitConfig(),
                'snapshot' => $payload,
                'urls' => [
                    'snapshot' => route('team.auction.bidding.fast-snapshot', $auction),
                    'placeBid' => route('team.auction.bidding.api.place-bid', $auction),
                    // Always present, never conditional: the way back to the screen that works.
                    'classic' => route('team.auction.bidding.show', $auction),
                ],
            ],
        ]);
    }

    /**
     * The screen's whole state, in one request.
     *
     * A thin delegation on purpose — see the class docblock. `tick()` already resolves the team
     * (honouring `?team_id=` for an admin previewing), and already shares the wall's one-second
     * cache for the player on the block.
     */
    public function teamSnapshot(Request $request, Auction $auction): JsonResponse
    {
        return app(AuctionBiddingController::class)->tick($request, $auction);
    }

    /**
     * The viewing team, for the header.
     *
     * Read back out of the snapshot rather than resolved a second time: `tick()` is the authority
     * on which team a viewer is acting as, and asking twice is how the two answers start to
     * differ in admin preview.
     */
    private function teamFrom(array $payload): ?ActualTeam
    {
        $teamId = $payload['purse']['team_id'] ?? null;

        return $teamId ? ActualTeam::find($teamId) : null;
    }
}

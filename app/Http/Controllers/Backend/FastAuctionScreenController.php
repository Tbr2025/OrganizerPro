<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ActualTeam;
use App\Models\Auction;
use App\Models\AuctionOperator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    /**
     * The organizer / auctioneer panel.
     *
     * The classic panel (6,511 lines) stays the complete surface — ads, templates, card exports,
     * pool management, the offline bid desk. This covers the part somebody actually touches while
     * a lot is running: who is on the block, what the bids are, the purses, and sell / pass /
     * next / undo. Anything not here is one click away on the classic panel, and that link is
     * permanent.
     */
    public function organizerPanel(Request $request, Auction $auction): View
    {
        $state = $this->panelState($auction);

        return view('fast-auction.panel', [
            'boot' => [
                'screen' => 'panel',
                'auctionId' => $auction->id,
                'auctionName' => $auction->name,
                'amountUnit' => $auction->amountUnitConfig(),
                'snapshot' => $state,
                // The queue is the largest part of the payload and barely changes, so it is sent
                // once here rather than on every reconcile.
                'queue' => array_slice($state['available_players'] ?? [], 0, 40),
                'can' => $this->abilities($auction),
                'urls' => [
                    'snapshot' => route('admin.auction.organizer.api.fast-state', $auction),
                    'sell' => route('admin.auction.organizer.api.player.sell', $auction),
                    'pass' => route('admin.auction.organizer.api.player.pass', $auction),
                    'onBid' => route('admin.auction.organizer.api.player.onbid', $auction),
                    'togglePause' => route('admin.auction.organizer.api.toggle-pause', $auction),
                    'classic' => route('admin.auction.organizer.panel', $auction),
                ],
            ],
        ]);
    }

    /** The panel's live state — trimmed for the wire, not rebuilt. */
    public function fastState(Auction $auction): JsonResponse
    {
        $state = $this->panelState($auction);

        // The queue rides in the boot blob instead: biggest single part of this payload, and it
        // does not change between lots.
        unset($state['available_players']);

        return response()->json($state);
    }

    /**
     * Delegated to `pollState()` and trimmed, rather than reassembled.
     *
     * Rebuilding this with its own column lists would fix the server-side cost — the un-projected
     * `with(\'player\')` loads that drag ~90 columns and a longText per player — but it would also
     * mean a second copy of a large assembly that has to keep agreeing with the first about the
     * timer, the undo stack and the sealed threshold. Those are control state: a panel acting on a
     * divergent version of them is a correctness bug, not a stale pixel.
     *
     * So the source stays single, and the client win comes from asking far less often — a
     * reconcile every 15 s against the classic panel\'s 2 s. Fixing the projection belongs IN
     * pollState(), where it makes both panels cheaper; that is still outstanding and is the
     * largest single server-side win left in the auction.
     *
     * @return array<string, mixed>
     */
    private function panelState(Auction $auction): array
    {
        $full = app(AuctionOrganizerController::class)->pollState($auction)->getData(true);

        $keep = [
            'auction_status', 'restarting', 'restart_seconds', 'stats', 'teams',
            'open_bid_mode', 'bid_type', 'next_bid_amount', 'max_bid_reached', 'quick_bid_steps',
            'sealed_threshold_pending', 'sealed_threshold_leader', 'sealed_threshold_amount',
            'can_undo', 'next_undo', 'next_undo_notes', 'active_pool', 'next_pool',
            'timer_enabled', 'timer_seconds_remaining', 'timer_expired', 'timer_paused',
            'bid_timer_seconds', 'final_call', 'server_time', 'amount_unit', 'available_players',
        ];

        $state = array_intersect_key($full, array_flip($keep));

        $state['current_player'] = $this->trimCurrent($full['current_player'] ?? null);
        // The board grows all evening; the panel renders a scrolling list, not 400 rows.
        $state['sold_players'] = array_map(fn ($p) => [
            'id' => $p['id'] ?? null,
            'name' => $p['player']['name'] ?? null,
            'team' => $p['sold_to_team']['name'] ?? null,
            'price' => $p['final_price'] ?? null,
        ], array_slice($full['sold_players'] ?? [], 0, 15));

        return $state;
    }

    /**
     * The player on the block, with the last few bids.
     *
     * @param  array<string, mixed>|null  $cp
     * @return array<string, mixed>|null
     */
    private function trimCurrent(?array $cp): ?array
    {
        if (! $cp) {
            return null;
        }

        $bids = array_slice(array_reverse($cp['bids'] ?? []), 0, 8);

        return [
            'id' => $cp['id'] ?? null,
            'name' => $cp['player']['name'] ?? null,
            'image_path' => $cp['player']['image_path'] ?? null,
            'player_type' => $cp['player']['player_type']['type'] ?? null,
            'base_price' => $cp['base_price'] ?? null,
            'current_price' => $cp['current_price'] ?? null,
            'lot_number' => $cp['lot_number'] ?? null,
            'leader' => $cp['current_bid_team']['name'] ?? null,
            'bids' => array_map(fn ($b) => [
                'id' => $b['id'] ?? null,
                'amount' => $b['amount'] ?? null,
                'team' => $b['team']['name'] ?? null,
            ], $bids),
        ];
    }

    /**
     * What this operator may actually do, mirroring the route middleware exactly.
     *
     * The UI must not offer a button that will 403 — which is the flaw the bidding screen
     * inherited from the classic one. Writes need BOTH the permission
     * (`auction.control|auction.edit`) and the per-auction ability from `auction_operators`, and
     * the middleware deliberately stands aside for admins, organizers and superadmins, and for
     * anyone neither named on an auction nor holding the Auctioneer role. Reproduced here so an
     * observe-only auctioneer gets a read-only panel instead of a row of refusals.
     *
     * @return array<string, bool>
     */
    private function abilities(Auction $auction): array
    {
        $user = Auth::user();
        $mayWrite = (bool) $user?->canAny(['auction.control', 'auction.edit']);

        $ability = function (string $ability) use ($user, $auction): bool {
            if ($user?->hasAnyRole(['Superadmin', 'Admin', 'Organizer'])) {
                return true;
            }

            $named = AuctionOperator::where('user_id', $user?->id)->exists();

            if (! $named && ! $user?->hasRole('Auctioneer')) {
                return true;
            }

            return (bool) AuctionOperator::where('auction_id', $auction->id)
                ->where('user_id', $user?->id)
                ->first()?->can($ability);
        };

        return [
            'sell' => $mayWrite && $ability(AuctionOperator::ABILITY_SELL),
            'control' => $mayWrite && $ability(AuctionOperator::ABILITY_CONTROL),
        ];
    }
}

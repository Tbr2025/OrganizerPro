<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ActualTeam;
use App\Models\Auction;
use App\Models\AuctionActionLog;
use App\Models\AuctionBid;
use App\Models\AuctionPlayer;
use App\Services\Auction\AuctionPoolService;
use App\Services\Auction\AuctionSaleService;
use App\Services\Auction\AuctionUndoService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Final allotment of unsold players once the bidding is over.
 *
 * Players nobody bid on are held in an unsold pool per source pool (see
 * AuctionPoolService::unsoldPoolFor). This screen assigns them to teams that are still
 * short of a legal squad, at base price, without going back through bidding.
 *
 * Budget rule here is deliberately different from bidding: allotment checks the *total*
 * purse, not the squad reserve. The reserve exists to guarantee the remaining slots stay
 * affordable, so enforcing it at allotment would block the very purchases it was held
 * back for.
 */
class AuctionAllotmentController extends Controller
{
    public function __construct(
        private readonly AuctionPoolService $pools,
        private readonly AuctionSaleService $sales,
        private readonly AuctionUndoService $undo,
    ) {
    }

    public function index(Auction $auction): View
    {
        $this->authorize('auction.view');

        $auction->load('tournament');

        $groups = $this->pools->allotmentGroups($auction);
        $teams = $this->pools->allotmentTeams($auction);

        $totalUnsold = $groups->sum(fn (array $g) => $g['players']->count());
        $totalShortfall = $teams->sum('slots_short');

        $breadcrumbs = [
            'title' => __('Final Allotment'),
            'items' => [
                ['label' => __('Auctions'), 'url' => route('admin.auctions.index')],
                ['label' => $auction->name, 'url' => route('admin.auctions.show', $auction)],
            ],
        ];

        return view('backend.pages.auctions.allotment', compact(
            'auction',
            'groups',
            'teams',
            'totalUnsold',
            'totalShortfall',
            'breadcrumbs'
        ));
    }

    /**
     * Allot one unsold player to a team.
     */
    public function allot(Request $request, Auction $auction)
    {
        $this->authorize('auction.edit');

        $validated = $request->validate([
            'auction_player_id' => 'required|integer|exists:auction_players,id',
            'team_id' => 'required|integer|exists:actual_teams,id',
            // Blank falls back to the player's base price.
            'price' => 'nullable|numeric|min:0',
        ]);

        $auctionPlayer = AuctionPlayer::where('auction_id', $auction->id)
            ->findOrFail($validated['auction_player_id']);

        // Only a player who went unsold is up for allotment — never one still waiting,
        // on the block, or already sold.
        if (! in_array($auctionPlayer->status, ['unsold', 'skipped'], true)) {
            return $this->respond(
                $request,
                false,
                sprintf('That player is %s and is not available for allotment.', $auctionPlayer->status),
                $auction
            );
        }

        $team = ActualTeam::forTournament($auction->tournament_id)->find($validated['team_id']);
        if (! $team) {
            return $this->respond($request, false, 'That team is not part of this tournament.', $auction);
        }

        $price = (float) ($validated['price'] ?? $auctionPlayer->base_price);

        $check = $this->pools->canAllot($auction, $team->id, $price);
        if (! $check['allowed']) {
            return $this->respond($request, false, $team->name . ': ' . $check['reason'], $auction);
        }

        $this->applyAllotment($auction, $auctionPlayer, $team, $price);

        $this->nudgeScreens($auction);

        return $this->respond(
            $request,
            true,
            sprintf(
                '%s allotted to %s for %s.',
                $auctionPlayer->player->name ?? 'Player',
                $team->name,
                format_points($price)
            ),
            $auction
        );
    }

    /**
     * Preview an even distribution of every unsold player across the teams that are
     * short of a squad. Writes nothing.
     */
    public function preview(Request $request, Auction $auction)
    {
        $this->authorize('auction.view');

        $players = $this->unsoldPlayers($auction);
        $plan = $this->pools->proposeAllotment($auction, $players);

        return response()->json([
            'success' => true,
            'proposals' => $plan['proposals'],
            'unassigned' => $plan['unassigned'],
        ]);
    }

    /**
     * Apply the proposed distribution.
     *
     * Recomputed server-side rather than trusting a posted plan, so a stale preview
     * cannot push a team past its purse.
     */
    public function autoDistribute(Request $request, Auction $auction)
    {
        $this->authorize('auction.edit');

        $players = $this->unsoldPlayers($auction);
        $plan = $this->pools->proposeAllotment($auction, $players);

        if (empty($plan['proposals'])) {
            return $this->respond($request, false, 'Nothing could be allotted — no team both needs a player and can afford one.', $auction);
        }

        $allotted = 0;
        foreach ($plan['proposals'] as $proposal) {
            $auctionPlayer = AuctionPlayer::where('auction_id', $auction->id)->find($proposal['auction_player_id']);
            $team = ActualTeam::find($proposal['team_id']);

            if (! $auctionPlayer || ! $team || ! in_array($auctionPlayer->status, ['unsold', 'skipped'], true)) {
                continue;
            }

            // Re-check against live figures as we go: each allotment shrinks the purse.
            if (! $this->pools->canAllot($auction, $team->id, (float) $proposal['price'])['allowed']) {
                continue;
            }

            $this->applyAllotment($auction, $auctionPlayer, $team, (float) $proposal['price']);
            $allotted++;
        }

        $skipped = count($plan['unassigned']) + (count($plan['proposals']) - $allotted);

        // ONE nudge for the whole run, not one per player: a bulk distribution can place
        // dozens, and a broadcast each would have every screen in the hall refetch dozens of
        // times for a single press.
        if ($allotted > 0) {
            $this->nudgeScreens($auction);
        }

        return $this->respond(
            $request,
            true,
            sprintf(
                '%d player(s) allotted.%s',
                $allotted,
                $skipped > 0 ? sprintf(' %d left unallotted — no team needs them or can afford them.', $skipped) : ''
            ),
            $auction
        );
    }

    /**
     * Every unsold/skipped player in the auction, oldest first.
     *
     * @return \Illuminate\Support\Collection<int, AuctionPlayer>
     */
    private function unsoldPlayers(Auction $auction)
    {
        return $auction->auctionPlayers()
            ->whereIn('status', ['unsold', 'skipped'])
            ->with('player:id,name')
            ->orderBy('id')
            ->get();
    }

    /**
     * Write the allotment: the same sale path as SELL, plus an audit bid and an undo
     * entry, so an allotment behaves exactly like any other acquisition.
     */
    /**
     * Tell the public screens an allotment happened.
     *
     * Allotment writes a real sale — the player joins a squad, the price comes off a purse — and
     * it broadcast nothing at all. The wall and the ticker only learn about a sale from an event,
     * so an allotted player stayed unsold on the strip, missing from Recent Sales and from the
     * team's figures, until something unrelated caused a refetch. In the end-of-auction window
     * where allotment runs, nothing unrelated happens.
     *
     * `AuctionStatusUpdate` rather than a sold event, deliberately: it means "something changed,
     * come and look", and every public screen already listens for it. A sold event would also
     * fire the wall's sale celebration, which is wrong for a clerical placement made after the
     * bidding has finished — and unbearable in a bulk run of thirty.
     */
    private function nudgeScreens(Auction $auction): void
    {
        try {
            broadcast(new \App\Events\AuctionStatusUpdate($auction->id, $auction->fresh()->status));
        } catch (\Throwable $e) {
            // A screen that misses this recovers on its next poll or reconnect. A failed
            // broadcast must never fail the allotment that has already been written.
            \Log::warning('Allotment screen nudge failed: ' . $e->getMessage(), ['auction_id' => $auction->id]);
        }
    }

    private function applyAllotment(Auction $auction, AuctionPlayer $auctionPlayer, ActualTeam $team, float $price): void
    {
        $auditBid = AuctionBid::create([
            'auction_id' => $auction->id,
            'auction_player_id' => $auctionPlayer->id,
            'player_id' => $auctionPlayer->player_id,
            'team_id' => $team->id,
            'user_id' => auth()->id(),
            'amount' => $price,
            'bid_source' => 'offline',
        ]);

        $snapshot = $this->sales->applySale($auctionPlayer, $team, $price, 'allot');

        $this->undo->record(
            $auction,
            AuctionActionLog::ACTION_ALLOT,
            $auctionPlayer,
            $snapshot + [
                'amount' => $price,
                'team_id' => $team->id,
                'team_name' => $team->name,
                'audit_bid_id' => $auditBid->id,
            ],
            sprintf('Allotted to %s for %s', $team->name, format_points($price))
        );
    }

    /** JSON for fetch callers, a redirect for plain form posts. */
    private function respond(Request $request, bool $success, string $message, Auction $auction)
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => $success, 'message' => $message], $success ? 200 : 422);
        }

        return redirect()
            ->route('admin.auctions.allotment', $auction)
            ->with($success ? 'success' : 'error', $message);
    }
}

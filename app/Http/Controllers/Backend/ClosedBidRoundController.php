<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\AuctionClosedBidEntry;
use App\Models\AuctionPlayer;
use App\Services\Auction\ClosedBidService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The organizer's view of a sealed round.
 *
 * Thin: every decision lives in ClosedBidService, so the team-facing endpoints and these
 * cannot drift apart on what a round permits.
 *
 * Routes sit in the organizer group, which already carries
 * ['auth', 'permission:auction.edit', 'organizer.access'] — and because {auction} is
 * model-bound, EnsureOrganizerCanAccess already confines a pure Organizer to their own
 * tournaments.
 */
class ClosedBidRoundController extends Controller
{
    public function __construct(private readonly ClosedBidService $closedBids)
    {
    }

    /**
     * Current sealed-round state for the player on the block.
     *
     * Amounts are withheld until the round is revealed — see
     * ClosedBidService::stateForOrganizer(). The panel is routinely on a projector, so
     * "the organizer can see it" is not the same as "only the organizer can see it".
     */
    public function state(Request $request, Auction $auction): JsonResponse
    {
        $auctionPlayer = $this->resolvePlayer($request, $auction);

        return response()->json([
            'success' => true,
            'closed_bid' => $this->closedBids->stateForOrganizer($auction, $auctionPlayer),
        ]);
    }

    /**
     * The organizer's answer to "the price has reached the sealed threshold — go sealed?".
     *
     * Crossing the threshold no longer tips the room into a sealed round by itself (see
     * Auction::applyAutoPhase); it raises a question, and this is the yes. Answering no
     * needs nothing here — the organizer sells to the leading team through the ordinary
     * sell button, and the player leaving the block ends the question.
     *
     * Idempotent: a second press while a round already exists returns that round rather
     * than an error, because two panels are routinely open on the same auction.
     */
    public function confirmThreshold(Request $request, Auction $auction): JsonResponse
    {
        /*
         * "Keep open bidding" has to be remembered by the SERVER.
         *
         * It was only ever remembered in the browser, so a refresh — or a second panel, or
         * a laptop waking up — asked again immediately, and again on every raise after
         * that. In a room past the threshold the dialog came back over and over.
         *
         * Recording it as a manual override is not a new concept: it is exactly what
         * `bid_type_manually_overridden` has always meant, and what pressing the panel's
         * Open button already does. The organizer has taken charge of the phase, so the
         * automatic rule stays out of the way. Going sealed later is then a deliberate act
         * — the Closed button — rather than something the price does on its own.
         */
        if ($request->input('decision') === 'keep') {
            $auction->update([
                'bid_type' => 'open',
                'bid_type_manually_overridden' => true,
            ]);

            return response()->json([
                'success' => true,
                'handled' => true,
                'message' => 'Open bidding continues. Use the Closed button to start a sealed round.',
            ]);
        }

        $auctionPlayer = $this->resolvePlayer($request, $auction);

        if (! $auctionPlayer || $auctionPlayer->status !== 'on_auction') {
            return response()->json(['success' => false, 'message' => 'No player is on the block.'], 422);
        }

        if ($existing = $this->closedBids->currentRound($auctionPlayer)) {
            return response()->json([
                'success' => true,
                'handled' => false,
                'message' => 'That player is already in a sealed round.',
                'closed_bid' => $this->closedBids->stateForOrganizer($auction, $auctionPlayer),
            ]);
        }

        if (! $auction->sealedThresholdPendingFor($auctionPlayer)) {
            return response()->json([
                'success' => false,
                'message' => 'The price has not reached the sealed threshold.',
            ], 422);
        }

        // Confirmed, so the phase rule may now apply. Both steps or neither: a bid_type of
        // `closed` with no round is the state where the panel offers a sealed board that
        // does not exist, and openRoundFor() refuses to build one while bid_type is `open`.
        $auction->applyAutoPhase((float) $auctionPlayer->current_price, sealedConfirmed: true);
        $auction = $auction->fresh();

        $round = $this->closedBids->openRoundFor($auctionPlayer->fresh(), $auction);

        if (! $round) {
            return response()->json(['success' => false, 'message' => 'Could not open a sealed round.'], 422);
        }

        return response()->json([
            'success' => true,
            'handled' => true,
            'message' => 'Sealed round opened.',
            'closed_bid' => $this->closedBids->stateForOrganizer($auction, $auctionPlayer->fresh()),
        ]);
    }

    /**
     * Invite the teams and show them the conditions.
     *
     * Optionally scoped to `team_ids` — the organizer's pre-selected subset for this
     * round. Omitting it invites everyone eligible, the pre-existing default.
     */
    public function openEntry(Request $request, Auction $auction): JsonResponse
    {
        $validated = $request->validate([
            'team_ids' => 'nullable|array',
            'team_ids.*' => 'integer',
        ]);

        return $this->run($request, $auction, fn ($round) => $this->closedBids->openEntry(
            $round,
            auth()->user(),
            $validated['team_ids'] ?? null
        ));
    }

    /** Start collecting sealed amounts, and start the round's clock. */
    public function start(Request $request, Auction $auction): JsonResponse
    {
        return $this->run($request, $auction, fn ($round) => $this->closedBids->start($round, auth()->user()));
    }

    /** Close submissions and reveal the board. */
    public function lock(Request $request, Auction $auction): JsonResponse
    {
        return $this->run($request, $auction, fn ($round) => $this->closedBids->lockAndReveal($round, auth()->user()));
    }

    /**
     * Hand the player to the winner.
     *
     * Takes no team and no amount: the winner is derived from the standing entries. The
     * old sealed award accepted both from the request and verified neither against a bid
     * that had actually been placed.
     */
    public function award(Request $request, Auction $auction): JsonResponse
    {
        return $this->run($request, $auction, fn ($round) => $this->closedBids->award($round, auth()->user()));
    }

    /** Open the next round of a tie. */
    public function startRebid(Request $request, Auction $auction): JsonResponse
    {
        return $this->run($request, $auction, fn ($round) => $this->closedBids->startRebid($round, auth()->user()));
    }

    /**
     * Draw a lot between the tied teams.
     *
     * Takes no seed, no index and no winner. Anything the client sent would be a way of
     * choosing the outcome, which is the one thing a drawn lot must not allow.
     */
    public function drawLot(Request $request, Auction $auction): JsonResponse
    {
        return $this->run($request, $auction, fn ($round) => $this->closedBids->drawLot($round, auth()->user()));
    }

    /** Resolve a tie by decision instead of by draw. A reason is required. */
    public function resolveManual(Request $request, Auction $auction): JsonResponse
    {
        $validated = $request->validate([
            'team_id' => 'required|integer|exists:actual_teams,id',
            // An override nobody has to explain is indistinguishable from an arbitrary one.
            'reason' => 'required|string|min:3|max:500',
        ]);

        return $this->run($request, $auction, fn ($round) => $this->closedBids->resolveManual(
            $round,
            (int) $validated['team_id'],
            $validated['reason'],
            auth()->user()
        ));
    }

    /** Settle a round nobody entered: award the standing leader, or send to unsold. */
    public function noEntriesDecision(Request $request, Auction $auction): JsonResponse
    {
        $validated = $request->validate(['choice' => 'required|in:award_leader,unsold']);

        return $this->run($request, $auction, fn ($round) => $this->closedBids->resolveNoEntries(
            $round,
            $validated['choice'],
            auth()->user()
        ));
    }

    /** Nudge one team's sealed amount: + / - one step, or a custom figure. */
    public function adjustEntry(Request $request, Auction $auction, AuctionClosedBidEntry $entry): JsonResponse
    {
        $validated = $request->validate([
            'direction' => 'nullable|in:up,down',
            'amount' => 'nullable|numeric|min:0',
        ]);

        return $this->entryAction($auction, $entry, fn () => $this->closedBids->adjust(
            $entry,
            isset($validated['amount']) ? (float) $validated['amount'] : null,
            $validated['direction'] ?? '',
            auth()->user()
        ));
    }

    /** Take a team out of the round from the organizer's desk. */
    public function withdrawEntry(Request $request, Auction $auction, AuctionClosedBidEntry $entry): JsonResponse
    {
        return $this->entryAction($auction, $entry, fn () => $this->closedBids->withdraw(
            $entry,
            auth()->user(),
            AuctionClosedBidEntry::ROLE_ADMIN
        ));
    }

    /** Put a withdrawn team back in. */
    public function reinstateEntry(Request $request, Auction $auction, AuctionClosedBidEntry $entry): JsonResponse
    {
        return $this->entryAction($auction, $entry, fn () => $this->closedBids->reinstate(
            $entry,
            auth()->user(),
            AuctionClosedBidEntry::ROLE_ADMIN
        ));
    }

    /**
     * Act on a single entry.
     *
     * The ownership check is the important part: route-model binding hands over whatever
     * id is in the URL, and {entry} is not one of the types EnsureOrganizerCanAccess
     * inspects — so this is the only thing standing between an entry id and cross-auction
     * access.
     */
    private function entryAction(Auction $auction, AuctionClosedBidEntry $entry, callable $do): JsonResponse
    {
        if ((int) $entry->auction_id !== (int) $auction->id) {
            abort(404);
        }

        $result = $do();

        return response()->json([
            'success' => true,
            'handled' => (bool) ($result['handled'] ?? false),
            'message' => $result['message'] ?? null,
            'closed_bid' => $this->closedBids->stateForOrganizer($auction, $entry->round->auctionPlayer->fresh()),
        ]);
    }

    /**
     * Resolve a round, reporting a no-op as a 200 rather than an error.
     *
     * Two organizer panels both pressing Lock is ordinary operation — the second press
     * must not raise a red toast.
     */
    private function run(Request $request, Auction $auction, callable $do): JsonResponse
    {
        $auctionPlayer = $this->resolvePlayer($request, $auction);
        $round = $auctionPlayer ? $this->closedBids->currentRound($auctionPlayer) : null;

        if (! $round) {
            return response()->json(['success' => false, 'message' => 'No sealed round for this player.'], 422);
        }

        $result = $do($round);

        return response()->json([
            'success' => true,
            'handled' => (bool) ($result['handled'] ?? false),
            'message' => $result['message'] ?? null,
            'closed_bid' => $this->closedBids->stateForOrganizer($auction, $auctionPlayer->fresh()),
        ]);
    }

    /**
     * The player a sealed round is being read for.
     *
     * An explicit id must still belong to this auction: route-model binding would happily
     * hand over any id in the URL, and nothing else on this route scopes it.
     */
    private function resolvePlayer(Request $request, Auction $auction): ?AuctionPlayer
    {
        if ($id = $request->query('auction_player_id')) {
            return AuctionPlayer::where('auction_id', $auction->id)->find($id);
        }

        return AuctionPlayer::where('auction_id', $auction->id)
            ->where('status', 'on_auction')
            ->first();
    }
}

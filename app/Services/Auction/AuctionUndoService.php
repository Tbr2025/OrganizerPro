<?php

declare(strict_types=1);

namespace App\Services\Auction;

use App\Models\Auction;
use App\Models\AuctionActionLog;
use App\Models\AuctionBid;
use App\Models\AuctionClosedBidEntry;
use App\Models\AuctionClosedBidRound;
use App\Models\AuctionPlayer;
use Illuminate\Support\Facades\DB;

/**
 * Records every reversible auction action and unwinds them newest-first.
 *
 * This exists because a mis-click during a live auction used to be
 * unrecoverable: bids were stored with updateOrCreate() so each raise
 * overwrote the previous one, leaving no history. Bids are now appended and
 * every action carries a snapshot of what it replaced, so the organizer can step
 * back through the stack.
 */
class AuctionUndoService
{
    public function __construct(
        private readonly AuctionSaleService $sales,
    ) {
    }

    /**
     * Append an action to the log.
     *
     * @param  array<string, mixed>  $payload  Snapshot of the state this action replaced
     */
    public function record(
        Auction $auction,
        string $action,
        ?AuctionPlayer $auctionPlayer,
        array $payload = [],
        ?string $description = null
    ): AuctionActionLog {
        return AuctionActionLog::create([
            'auction_id' => $auction->id,
            'auction_player_id' => $auctionPlayer?->id,
            'action' => $action,
            'payload' => $payload,
            'description' => $description,
            'user_id' => auth()->id(),
        ]);
    }

    /** The action Undo would reverse next, or null when the stack is empty. */
    public function nextUndoable(Auction $auction): ?AuctionActionLog
    {
        return AuctionActionLog::where('auction_id', $auction->id)
            ->pending()
            ->whereIn('action', AuctionActionLog::REVERSIBLE)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Reverse the most recent un-undone action.
     *
     * @return array{success: bool, message: string, action?: string, auction_player_id?: int|null}
     */
    public function undoLast(Auction $auction): array
    {
        $log = $this->nextUndoable($auction);

        if (! $log) {
            return ['success' => false, 'message' => 'Nothing left to undo.'];
        }

        return DB::transaction(function () use ($auction, $log) {
            // Re-read under a lock so two open panels can't undo the same action.
            $locked = AuctionActionLog::whereKey($log->id)->lockForUpdate()->first();

            if (! $locked || $locked->undone_at !== null) {
                return ['success' => false, 'message' => 'That action was already undone.'];
            }

            $auctionPlayer = $locked->auction_player_id
                ? AuctionPlayer::where('auction_id', $auction->id)
                    ->lockForUpdate()
                    ->find($locked->auction_player_id)
                : null;

            $result = match ($locked->action) {
                AuctionActionLog::ACTION_BID => $this->undoBid($locked, $auctionPlayer),
                AuctionActionLog::ACTION_SELL,
                AuctionActionLog::ACTION_ALLOT => $this->undoSale($locked, $auctionPlayer),
                AuctionActionLog::ACTION_PASS,
                AuctionActionLog::ACTION_SKIP => $this->undoStatusChange($locked, $auctionPlayer),
                AuctionActionLog::ACTION_CLOSED_BID,
                AuctionActionLog::ACTION_CLOSED_ADJUST,
                AuctionActionLog::ACTION_CLOSED_WITHDRAW => $this->undoClosedEntryChange($locked),
                AuctionActionLog::ACTION_CLOSED_LOT => $this->undoClosedLot($locked, $auctionPlayer),
                AuctionActionLog::ACTION_CLOSED_REVEAL => $this->undoClosedReveal($locked),
                default => ['success' => false, 'message' => 'That action cannot be undone.'],
            };

            if ($result['success']) {
                $locked->update([
                    'undone_at' => now(),
                    'undone_by' => auth()->id(),
                ]);
            }

            return $result + [
                'action' => $locked->action,
                'auction_player_id' => $locked->auction_player_id,
            ];
        });
    }

    /**
     * Reverse a change to one team's sealed entry.
     *
     * One method for a submission, an admin adjustment and a withdrawal, because all
     * three restore the same thing: the entry's prior amount and state.
     *
     * Refused once the round has been revealed. Putting an amount back onto a board
     * everybody has already seen is rewriting history rather than correcting a slip; the
     * tool at that point is a recorded manual resolution.
     *
     * @return array{success: bool, message: string}
     */
    /**
     * Put a revealed sealed round back to collecting.
     *
     * This is what makes a revealed round steppable at all. Undoing a sealed bid under a
     * revealed board is refused — the winner on screen was derived from the amounts being
     * changed, and silently leaving it there would show a result the bids no longer
     * support. With the reveal itself on the stack, UNDO takes the reveal off first and
     * the bids beneath it then step back one at a time, each re-revealed by pressing Lock
     * again rather than recomputed behind the organizer's back.
     *
     * The award is not undone here: a sale is its own recorded step, so it is newer on the
     * stack and comes off first. The guard is for the case where the two ever disagree.
     */
    private function undoClosedReveal(AuctionActionLog $log): array
    {
        $payload = $log->payload ?? [];

        $round = isset($payload['round_id'])
            ? AuctionClosedBidRound::lockForUpdate()->find($payload['round_id'])
            : null;

        if (! $round) {
            return ['success' => false, 'message' => 'That sealed round no longer exists.'];
        }

        if ($round->state === AuctionClosedBidRound::STATE_AWARDED) {
            return ['success' => false, 'message' => 'That player has been sold — undo the sale first.'];
        }

        $round->update([
            'state' => $payload['state'] ?? AuctionClosedBidRound::STATE_COLLECTING,
            'locked_at' => $payload['locked_at'] ?? null,
            'revealed_at' => $payload['revealed_at'] ?? null,
            'winner_team_id' => $payload['winner_team_id'] ?? null,
            'winning_amount' => $payload['winning_amount'] ?? null,
            'resolution' => $payload['resolution'] ?? null,
            'tie_amount' => $payload['tie_amount'] ?? null,
            'tied_team_ids' => $payload['tied_team_ids'] ?? null,

            /*
             * A fresh clock, not the one that had already run out.
             *
             * Collection is genuinely open again, and both submitting and adjusting are
             * refused once a round's timer has expired — restoring the old start time
             * would hand back a round in which nothing could be entered or corrected,
             * which is the whole reason for stepping back to it.
             */
            'timer_started_at' => now(),
        ]);

        // Teams marked as having missed a required re-bid are put back as they were.
        foreach ($payload['no_entry_entries'] ?? [] as $snapshot) {
            if (isset($snapshot['id'], $snapshot['state'])) {
                AuctionClosedBidEntry::whereKey($snapshot['id'])->update(['state' => $snapshot['state']]);
            }
        }

        return ['success' => true, 'message' => 'Reveal undone — the round is collecting again.'];
    }

    private function undoClosedEntryChange(AuctionActionLog $log): array
    {
        $payload = $log->payload ?? [];
        $entry = isset($payload['entry_id'])
            ? \App\Models\AuctionClosedBidEntry::lockForUpdate()->find($payload['entry_id'])
            : null;

        if (! $entry) {
            return ['success' => false, 'message' => 'That sealed entry no longer exists.'];
        }

        $round = $entry->round;

        if ($round?->isRevealed()) {
            return ['success' => false, 'message' => 'That round has been revealed — resolve it instead of undoing a bid.'];
        }

        // A withdrawal and a reinstatement are the same action type; the payload says
        // which way it went.
        if (($payload['action'] ?? null) === 'withdraw') {
            // Same contradiction guarded on the way back in: a team with no amount cannot
            // be restored as having submitted one, and the default here is 'submitted'.
            $restored = $payload['previous_state'] ?? \App\Models\AuctionClosedBidEntry::STATE_SUBMITTED;

            if ($entry->amount === null && $restored === \App\Models\AuctionClosedBidEntry::STATE_SUBMITTED) {
                $restored = \App\Models\AuctionClosedBidEntry::STATE_ACCEPTED;
            }

            $entry->update([
                'state' => $restored,
                'withdrawn_at' => null,
                'withdrawn_by' => null,
                'withdrawn_by_role' => null,
            ]);

            return ['success' => true, 'message' => 'Withdrawal reversed.'];
        }

        if (($payload['action'] ?? null) === 'reinstate') {
            $entry->update([
                'state' => \App\Models\AuctionClosedBidEntry::STATE_WITHDRAWN,
                'withdrawn_at' => now(),
                'reinstated_at' => null,
                'reinstated_by' => null,
            ]);

            return ['success' => true, 'message' => 'Reinstatement reversed.'];
        }

        $previous = $payload['previous_amount'] ?? null;

        // Drop the adjustment this action appended, so the durable trail on the entry
        // matches what actually stands.
        $adjustments = $entry->adjustments ?? [];
        if ($log->action === AuctionActionLog::ACTION_CLOSED_ADJUST && $adjustments !== []) {
            array_pop($adjustments);
        }

        /*
         * With no amount to restore, the entry must land in a state that means "has not
         * bid" — whatever the payload says.
         *
         * previous_state was recorded after the save for a while, so logs already written
         * claim 'submitted' for an entry that was only invited. Restoring that verbatim
         * produced submitted-with-no-amount, which counted as a live sealed bid and
         * wedged the round. Those logs are still on disk and still undoable, so the
         * contradiction is refused here as well as at the source.
         */
        $restoredState = $payload['previous_state'] ?? \App\Models\AuctionClosedBidEntry::STATE_ACCEPTED;

        if ($previous === null && $restoredState === \App\Models\AuctionClosedBidEntry::STATE_SUBMITTED) {
            $restoredState = \App\Models\AuctionClosedBidEntry::STATE_ACCEPTED;
        }

        $entry->update([
            'amount' => $previous,
            'state' => $previous !== null
                ? \App\Models\AuctionClosedBidEntry::STATE_SUBMITTED
                : $restoredState,
            'submitted_at' => $previous !== null ? $entry->submitted_at : null,
            'adjustments' => $adjustments,
            'adjusted_count' => max(0, (int) $entry->adjusted_count - ($log->action === AuctionActionLog::ACTION_CLOSED_ADJUST ? 1 : 0)),
        ]);

        return [
            'success' => true,
            'message' => $previous !== null
                ? 'Sealed bid rolled back to ' . format_points((float) $previous) . '.'
                : 'Sealed bid removed.',
        ];
    }

    /**
     * Reverse a drawn lot or a manual tie resolution.
     *
     * The sale it produced is undone through the normal sale path, so the roster pivot,
     * the team-user row and the Spatie roles all unwind correctly rather than through a
     * second, bespoke implementation.
     *
     * @return array{success: bool, message: string}
     */
    private function undoClosedLot(AuctionActionLog $log, ?AuctionPlayer $auctionPlayer): array
    {
        $payload = $log->payload ?? [];
        $round = isset($payload['round_id'])
            ? \App\Models\AuctionClosedBidRound::lockForUpdate()->find($payload['round_id'])
            : null;

        if (! $round) {
            return ['success' => false, 'message' => 'That sealed round no longer exists.'];
        }

        if ($auctionPlayer && $auctionPlayer->status === 'sold') {
            return [
                'success' => false,
                'message' => 'The player has been sold — undo the sale first, then the draw.',
            ];
        }

        $round->update([
            'state' => \App\Models\AuctionClosedBidRound::STATE_AWAITING_LOT,
            'resolution' => null,
            'winner_team_id' => null,
            'winning_amount' => null,
            'resolved_at' => null,
            'resolved_by' => null,
            'lot_algorithm' => null,
            'lot_seed' => null,
            'lot_candidates' => null,
            'lot_winner_team_id' => null,
            'lot_drawn_at' => null,
        ]);

        return ['success' => true, 'message' => 'The draw has been cleared — it can be run again.'];
    }

    /**
     * Void the bid this action created and roll the price back to whatever the
     * previous live bid left behind.
     *
     * @return array{success: bool, message: string}
     */
    private function undoBid(AuctionActionLog $log, ?AuctionPlayer $auctionPlayer): array
    {
        if (! $auctionPlayer) {
            return ['success' => false, 'message' => 'The player for that bid no longer exists.'];
        }

        $bidId = $log->payload['bid_id'] ?? null;

        if ($bidId) {
            AuctionBid::whereKey($bidId)->update([
                'is_void' => true,
                'voided_by' => auth()->id(),
                'voided_at' => now(),
            ]);
        }

        // Fall back to the previous live bid; if there is none, the player goes
        // back to their opening price with no leading team.
        $previous = AuctionBid::where('auction_player_id', $auctionPlayer->id)
            ->live()
            ->orderByDesc('id')
            ->first();

        $newPrice = $previous->amount ?? $auctionPlayer->base_price;

        $auctionPlayer->update([
            'current_price' => $newPrice,
            'current_bid_team_id' => $previous->team_id ?? null,
            'final_price' => $previous->amount ?? null,
        ]);

        $teamName = $log->payload['team_name'] ?? 'that team';
        $amount = $log->payload['amount'] ?? null;

        $reverted = $this->reopenIfUndoneBelowThreshold($auctionPlayer->fresh(), (float) $newPrice);

        // Still sealed, but cheaper than it was: the floor follows the price down.
        if (! $reverted) {
            $this->lowerFloorToPrice($auctionPlayer->fresh(), (float) $newPrice);
        }

        return [
            'success' => true,
            'message' => ($amount !== null
                ? sprintf('Undid %s bid of %s.', $teamName, format_points($amount))
                : sprintf('Undid %s bid.', $teamName))
                . ($reverted ? ' Back below the sealed threshold — open bidding resumed.' : ''),
        ];
    }

    /**
     * Bring an open round's floor down when an undo lowers the price under it.
     *
     * The floor is worked out once, from the price at the moment the round opens. Undoing
     * the raises that opened it left the board demanding 10M for a player back at 8M — a
     * gap created purely by the undo, and a floor that reads as though the bidding had
     * gone higher than it now has.
     *
     * Deliberately one-way. A floor that rose again as the price recovered would move
     * under teams mid-round, and only ever falls to what the price justifies:
     *
     *  - Nothing submitted yet. Lowering the bar under a team that has already bid
     *    against the published floor would change the terms it bid on.
     *  - First rounds only. A tie-break round's floor comes from the tied amount, not
     *    the price, so re-deriving it from the price would be wrong.
     *  - Before the reveal. Once amounts are out the floor is part of the record.
     */
    private function lowerFloorToPrice(AuctionPlayer $auctionPlayer, float $newPrice): void
    {
        $auction = $auctionPlayer->auction;

        if (! $auction || $auction->bid_type !== 'closed') {
            return;
        }

        $round = AuctionClosedBidRound::where('auction_player_id', $auctionPlayer->id)
            ->whereIn('state', [
                AuctionClosedBidRound::STATE_PENDING,
                AuctionClosedBidRound::STATE_ENTRY_OPEN,
                AuctionClosedBidRound::STATE_COLLECTING,
            ])
            ->whereNull('parent_round_id')
            ->latest('id')
            ->first();

        if (! $round || $round->locked_at !== null) {
            return;
        }

        if (AuctionClosedBidEntry::where('auction_closed_bid_round_id', $round->id)->standing()->exists()) {
            return;
        }

        $threshold = $auction->closed_bid_starts_at !== null
            ? (float) $auction->closed_bid_starts_at
            : 0.0;

        // The same derivation as ClosedBidService::floorFor(), which cannot be called
        // from here — that service depends on this one.
        $floor = app(BidIncrementService::class)->snapUpToStep($auction, max($threshold, $newPrice));

        if ($floor < (float) $round->floor) {
            $round->update(['floor' => $floor]);
        }
    }

    /**
     * If undoing a bid takes the price back below the sealed threshold, take the round
     * down with it.
     *
     * "Closed" used to be one-way once the price fell — deliberately, because a price that
     * genuinely rises and falls during open bidding should not repeatedly flip the phase.
     * But this is not that: undoing the very bid that crossed the threshold is undoing the
     * reason the round exists at all, and the live panel showed a sealed board — floor 8M,
     * a full team list, controls — for a player sitting at 6M with nothing anyone can
     * safely act on, and no way back to ordinary bidding except editing the auction.
     *
     * Two guards keep this from undoing more than the one thing it should:
     *
     *  - `bid_type_manually_overridden` must be false. A press of the Closed button sets
     *    it and means the organizer chose sealed regardless of price — undoing an unrelated
     *    bid must not silently reverse that choice.
     *  - The round must have no STANDING entries. If a team has already submitted a real
     *    sealed amount, abandoning the round on the way out would discard their bid; the
     *    round is left in place and the phase stays closed.
     *
     * @return bool  Whether the round was abandoned and the phase reverted.
     */
    private function reopenIfUndoneBelowThreshold(AuctionPlayer $auctionPlayer, float $newPrice): bool
    {
        $auction = $auctionPlayer->auction;

        if (! $auction
            || $auction->bid_type !== 'closed'
            || $auction->bid_type_manually_overridden
            || $auction->closed_bid_starts_at === null
            || $newPrice >= (float) $auction->closed_bid_starts_at) {
            return false;
        }

        $round = AuctionClosedBidRound::where('auction_player_id', $auctionPlayer->id)
            ->open()
            ->latest('id')
            ->first();

        if (! $round) {
            return false;
        }

        if (AuctionClosedBidEntry::where('auction_closed_bid_round_id', $round->id)->standing()->exists()) {
            return false;
        }

        /*
         * The same abandonment ClosedBidService::abandonRoundsFor() performs — done inline
         * rather than by injecting that service, which depends on THIS one (undo has to be
         * able to reverse a sealed-round action) and would deadlock the container in a
         * constructor cycle.
         */
        AuctionClosedBidRound::where('auction_player_id', $auctionPlayer->id)
            ->open()
            ->update([
                'state' => AuctionClosedBidRound::STATE_ABANDONED,
                'resolution' => AuctionClosedBidRound::RESOLUTION_ABANDONED,
                'abandoned_at' => now(),
            ]);

        $auctionPlayer->update(['closed_bid_round_id' => null]);
        $auction->update(['bid_type' => 'open']);

        return true;
    }

    /**
     * Un-sell a player: restore the auction row and every downstream store the
     * sale touched, and void the bid the sale was awarded on.
     *
     * @return array{success: bool, message: string}
     */
    private function undoSale(AuctionActionLog $log, ?AuctionPlayer $auctionPlayer): array
    {
        if (! $auctionPlayer) {
            return ['success' => false, 'message' => 'The sold player no longer exists.'];
        }

        $this->sales->revert($auctionPlayer, $log->payload ?? []);

        // An allotment/sealed award writes its own audit bid; void it so it stops
        // counting toward the team's spend.
        if ($auditBidId = ($log->payload['audit_bid_id'] ?? null)) {
            AuctionBid::whereKey($auditBidId)->update([
                'is_void' => true,
                'voided_by' => auth()->id(),
                'voided_at' => now(),
            ]);
        }

        $teamName = $log->payload['team_name'] ?? 'the team';
        $amount = $log->payload['amount'] ?? null;

        return [
            'success' => true,
            'message' => $amount !== null
                ? sprintf('Undid sale to %s for %s. Player is back on the block.', $teamName, format_points($amount))
                : sprintf('Undid sale to %s. Player is back on the block.', $teamName),
        ];
    }

    /**
     * Put a passed or skipped player back on the block with their bid stack
     * intact.
     *
     * @return array{success: bool, message: string}
     */
    private function undoStatusChange(AuctionActionLog $log, ?AuctionPlayer $auctionPlayer): array
    {
        if (! $auctionPlayer) {
            return ['success' => false, 'message' => 'That player no longer exists.'];
        }

        // Only one player may be on the block at a time.
        $liveElsewhere = AuctionPlayer::where('auction_id', $auctionPlayer->auction_id)
            ->where('status', 'on_auction')
            ->where('id', '!=', $auctionPlayer->id)
            ->exists();

        if ($liveElsewhere) {
            return [
                'success' => false,
                'message' => 'Another player is on the block. Finish or undo that one first.',
            ];
        }

        $before = $log->payload['auction_player'] ?? [];

        $restore = [
            'status' => $before['status'] ?? 'on_auction',
            'current_price' => $before['current_price'] ?? $auctionPlayer->base_price,
            'current_bid_team_id' => $before['current_bid_team_id'] ?? null,
            'final_price' => $before['final_price'] ?? null,
        ];

        // A pass moves the player into their pool's unsold holding pool; undoing it has
        // to put them back where they were, lot number included.
        if (array_key_exists('auction_pool_id', $before)) {
            $restore['auction_pool_id'] = $before['auction_pool_id'];
            $restore['lot_number'] = $before['lot_number'] ?? null;
        }

        $auctionPlayer->update($restore);

        $verb = $log->action === AuctionActionLog::ACTION_SKIP ? 'skip' : 'pass';

        return [
            'success' => true,
            'message' => sprintf('Undid %s — player is back on the block.', $verb),
        ];
    }

    /**
     * Recent actions for the panel's undo stack display.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recentActions(Auction $auction, int $limit = 15): array
    {
        return AuctionActionLog::where('auction_id', $auction->id)
            ->with('auctionPlayer.player:id,name')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (AuctionActionLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'player_name' => $log->auctionPlayer?->player?->name,
                'undone' => $log->undone_at !== null,
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->all();
    }
}

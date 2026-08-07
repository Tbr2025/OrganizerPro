<?php

declare(strict_types=1);

namespace App\Services\Auction;

use App\Models\Auction;
use App\Models\AuctionActionLog;
use App\Models\AuctionBid;
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
            $entry->update([
                'state' => $payload['previous_state'] ?? \App\Models\AuctionClosedBidEntry::STATE_SUBMITTED,
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

        $entry->update([
            'amount' => $previous,
            'state' => $previous !== null
                ? \App\Models\AuctionClosedBidEntry::STATE_SUBMITTED
                : ($payload['previous_state'] ?? \App\Models\AuctionClosedBidEntry::STATE_ACCEPTED),
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

        $auctionPlayer->update([
            'current_price' => $previous->amount ?? $auctionPlayer->base_price,
            'current_bid_team_id' => $previous->team_id ?? null,
            'final_price' => $previous->amount ?? null,
        ]);

        $teamName = $log->payload['team_name'] ?? 'that team';
        $amount = $log->payload['amount'] ?? null;

        return [
            'success' => true,
            'message' => $amount !== null
                ? sprintf('Undid %s bid of %s.', $teamName, format_points($amount))
                : sprintf('Undid %s bid.', $teamName),
        ];
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

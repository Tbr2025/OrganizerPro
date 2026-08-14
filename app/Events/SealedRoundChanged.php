<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\AuctionClosedBidRound;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * A sealed round changed state — opened, locked, revealed, tied, drawn or awarded.
 *
 * The sealed path broadcast NOTHING. Every other screen learned that the room had gone to a
 * sealed bid only from its own poll, sitting behind a one-second feed cache — so the wall kept
 * showing open bidding until someone reloaded it, and the tie-break draw appeared after the fact
 * rather than as it happened. In a hall the sealed round is the moment everyone is watching, and
 * it was the one moment nothing was pushed.
 *
 * **This payload carries no amounts and no team-to-amount mapping**, and it must never be given
 * any. The whole point of a sealed round is that the figures are private until the reveal, and
 * this is a public channel — the same one the open-bid price travels on, which is safe precisely
 * because that price is already on the wall. A listener is told THAT the state changed and which
 * player it concerns; it then re-reads the feed, which applies the same disclosure rules it
 * always has (ClosedBidService::stateForPublic()).
 *
 * ShouldBroadcastNow, not ShouldBroadcast: production runs `queue:work --sleep=3`, so a queued
 * broadcast can arrive later than the poll it exists to beat.
 */
class SealedRoundChanged implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $auctionId,
        public ?int $auctionPlayerId,
        /** The round's state — `collecting`, `revealed`, `tie`, `awaiting_lot`, `awarded`… */
        public ?string $state,
        /** Which round of a re-bid sequence, so a screen can announce "round 2" as it opens. */
        public ?int $roundNumber = null,
    ) {
    }

    /**
     * Announce a change from wherever the round moves.
     *
     * Failures are logged and swallowed: the round is already committed by the time this runs,
     * and an unreachable Pusher must not turn a successful lock or award into an error in the
     * room. The polls still carry the update — this only makes it immediate.
     */
    public static function announce(?AuctionClosedBidRound $round): void
    {
        if (! $round) {
            return;
        }

        try {
            broadcast(new self(
                auctionId: (int) $round->auction_id,
                auctionPlayerId: $round->auction_player_id !== null ? (int) $round->auction_player_id : null,
                state: $round->state,
                roundNumber: $round->round_number !== null ? (int) $round->round_number : null,
            ));
        } catch (\Throwable $e) {
            Log::warning('SealedRoundChanged broadcast failed: ' . $e->getMessage(), [
                'round_id' => $round->id,
            ]);
        }
    }

    public function broadcastOn(): Channel
    {
        return new Channel('auction.' . $this->auctionId);
    }

    public function broadcastAs(): string
    {
        return 'sealed.changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'auction_player_id' => $this->auctionPlayerId,
            'state' => $this->state,
            'round_number' => $this->roundNumber,
        ];
    }
}

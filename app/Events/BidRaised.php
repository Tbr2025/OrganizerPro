<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\AuctionPlayer;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * A raise on the player currently on the block.
 *
 * This is the event that did not exist. Bidding reached other screens only by a 2-second
 * poll sitting behind a 1-second feed cache, so a team could be looking at a price roughly
 * three seconds old and bid against it — in a room where the clock is running, that is a
 * business problem rather than a cosmetic one. The two events that were meant to carry a
 * raise were dead code: mismatched constructors, in controllers with no routes, so nothing
 * was ever published. They have been deleted rather than left beside this one.
 *
 * ShouldBroadcastNow, NOT ShouldBroadcast. Production runs
 * `queue:work database --sleep=3`, so a queued broadcast can wait up to three seconds
 * before it is published — slower than the polling it exists to beat. Local
 * QUEUE_CONNECTION=sync broadcasts inline, so that delay cannot be observed in
 * development; it has to be right by construction.
 *
 * The payload is flat and explicit rather than a serialised model. Two existing events
 * publish the same `player.onbid` name on this channel with different shapes, and every
 * listener then has to guess which it received.
 */
class BidRaised implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /**
     * @param  int  $bidId  Monotonic, and the reason listeners can be safe: socket frames
     *                      are not ordered, so a client applies a payload only when this is
     *                      higher than the last one it applied. Without it a late frame
     *                      drags a price back down.
     */
    /**
     * @param  array<string, mixed>  $timerState  Auction::timerStateFor() output, so a
     *                                            listener can re-seed its countdown from the
     *                                            same figures the poll would have handed it
     *                                            instead of keeping a second clock.
     */
    public function __construct(
        public int $auctionId,
        public int $auctionPlayerId,
        public float $currentPrice,
        public ?int $currentBidTeamId,
        public ?string $teamName,
        public int $bidId,
        public array $timerState = [],
    ) {
    }

    /**
     * Announce a raise from anywhere the price changes.
     *
     * One place builds the payload, because four call sites building it independently is
     * exactly how PlayerOnBid and PlayerOnBidEvent ended up publishing the same event name
     * with two different shapes.
     *
     * Failures are logged and swallowed. A bid is committed by the time this runs, and an
     * unreachable Pusher must not turn a successful bid into an error in the room — the
     * polls still carry the update. AuctionSaleService guards its own broadcast the same
     * way.
     *
     * @param  int  $bidId  The AuctionBid id, used by listeners as the ordering token.
     */
    public static function announce(AuctionPlayer $auctionPlayer, int $bidId, ?string $teamName = null): void
    {
        try {
            $player = $auctionPlayer->fresh();

            if (! $player) {
                return;
            }

            $auction = $player->auction;

            broadcast(new self(
                auctionId: (int) $player->auction_id,
                auctionPlayerId: (int) $player->id,
                currentPrice: (float) $player->current_price,
                currentBidTeamId: $player->current_bid_team_id !== null ? (int) $player->current_bid_team_id : null,
                teamName: $teamName ?? $player->currentBidTeam?->name,
                bidId: $bidId,
                // The one canonical clock builder, the same one the poll payload uses. A
                // raise restarts the clock server-side, and a listener handed only a start
                // timestamp would have no `timer_seconds_remaining` to re-seed from.
                timerState: $auction ? $auction->fresh()->timerStateFor($player) : [],
            ));
        } catch (\Throwable $e) {
            Log::warning('BidRaised broadcast failed: ' . $e->getMessage(), [
                'auction_player_id' => $auctionPlayer->id,
                'bid_id' => $bidId,
            ]);
        }
    }

    /**
     * Public, matching PlayerOnBid and PlayerSoldEvent: an open-bid price is already on the
     * hall wall, so there is nothing here the room does not see anyway.
     *
     * Sealed-round amounts must never travel on this channel. They are withheld even from
     * the organizer's own board until the reveal, because that board is routinely on a
     * projector — see ClosedBidService::stateForOrganizer().
     */
    public function broadcastOn(): Channel
    {
        return new Channel('auction.' . $this->auctionId);
    }

    public function broadcastAs(): string
    {
        return 'bid.raised';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'auction_player_id' => $this->auctionPlayerId,
            'current_price' => $this->currentPrice,
            'current_bid_team_id' => $this->currentBidTeamId,
            'team_name' => $this->teamName,
            'bid_id' => $this->bidId,
            'server_time' => now()->timestamp,

            /*
             * Timer fields named exactly as the poll payload names them
             * (AuctionOrganizerController::pollState, PublicAuctionController), so a client
             * can hand this frame straight to its existing syncTimerFromServer() instead of
             * growing a second code path for the pushed case.
             */
            'timer_enabled' => $this->timerState['applies'] ?? true,
            'timer_seconds_remaining' => $this->timerState['remaining'] ?? null,
            'bid_timer_seconds' => $this->timerState['limit'] ?? null,
            'timer_expired' => $this->timerState['expired'] ?? false,
            'final_call_stages' => $this->timerState['final_call_stages'] ?? null,
        ];
    }
}

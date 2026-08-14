<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * The organizer changed what the public screens are showing — a board, or back to the live card.
 *
 * Pushed as well as stored, because the point of the button is that the hall's screens change
 * when it is pressed — a two-second poll is visible as hesitation on a wall the whole room is
 * looking at. The stored column (`auctions.public_board`) is what a screen opened or reloaded
 * afterwards reads; this is what changes the ones already watching.
 *
 * ShouldBroadcastNow: production runs `queue:work --sleep=3`, so a queued broadcast could
 * arrive later than the poll it exists to beat.
 */
class SoldBoardToggled implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $auctionId,
        /** `sold`, `highlights`, or null for the live card. */
        public ?string $board,
    ) {
    }

    /** Failures are logged and swallowed — the flag is already saved and the polls carry it. */
    public static function announce(int $auctionId, ?string $board): void
    {
        try {
            broadcast(new self($auctionId, $board));
        } catch (\Throwable $e) {
            Log::warning('SoldBoardToggled broadcast failed: ' . $e->getMessage(), [
                'auction_id' => $auctionId,
            ]);
        }
    }

    /** `auction.X`, with the raises and sales — not the `auction.public.X` of status updates. */
    public function broadcastOn(): Channel
    {
        return new Channel('auction.' . $this->auctionId);
    }

    public function broadcastAs(): string
    {
        return 'board.changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['board' => $this->board];
    }
}

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

        /**
         * Which screens it is for: `wall`, `ticker` or `both`.
         *
         * The event carried only the board name, so every screen applied it — a reel targeted at
         * the wall appeared on the ticker too, and then vanished on the ticker's next feed read,
         * which DOES respect the target. One flash on, one flash off, on a screen the organizer
         * had deliberately left alone.
         */
        public ?string $target = 'both',

        /*
         * The artwork switches, sent with the board.
         *
         * The screens refetch only when the BOARD changes, so unticking "Ad slides" or "Sponsor
         * strip" and pressing Apply changed nothing on the wall until some unrelated event
         * happened to cause a refetch. The feed has always honoured the flags; the screens simply
         * never went back to ask.
         */
        public bool $adSlides = true,
        public bool $adSponsors = true,
    ) {
    }

    /** Failures are logged and swallowed — the flag is already saved and the polls carry it. */
    public static function announce(
        int $auctionId,
        ?string $board,
        ?string $target = 'both',
        bool $adSlides = true,
        bool $adSponsors = true
    ): void {
        try {
            broadcast(new self($auctionId, $board, $target, $adSlides, $adSponsors));
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

    /**
     * @return array<string, mixed>
     *
     * Everything the dialog can change, not just the board.
     *
     * This returned `['board' => …]` alone while the constructor had grown a target and two
     * artwork switches — and a broadcastWith() replaces the payload entirely, so those three
     * were dropped on the wire even though both screens were written to read them. The effect
     * on the wall and the ticker was that Apply did nothing: `target` arrived undefined and fell
     * back to "both", and the artwork pair arrived undefined every time, so the comparison that
     * decides whether to refetch never saw a change. Pressing Apply with only a checkbox or a
     * target touched left the hall's screens exactly as they were until some unrelated event
     * happened to refresh them.
     *
     * Key names match what the screens read (`adSlides`, `adSponsors`) rather than being
     * snake_cased — these are read straight off the event object in Javascript.
     */
    public function broadcastWith(): array
    {
        return [
            'board' => $this->board,
            'target' => $this->target,
            'adSlides' => $this->adSlides,
            'adSponsors' => $this->adSponsors,
        ];
    }
}

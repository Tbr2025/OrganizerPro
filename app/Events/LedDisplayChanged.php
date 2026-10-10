<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A NUDGE to every screen and console on an LED display: refetch. Carries no
 * data — the screen reads its state from the token-checked endpoint, so the
 * public channel leaks nothing.
 *
 * ShouldBroadcastNow for the same reason as BidRaised: the queue worker sleeps
 * up to 3 s, slower than the poll this is meant to beat.
 */
class LedDisplayChanged implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public int $displayId) {}

    public static function announce(int $displayId): void
    {
        try {
            event(new self($displayId));
        } catch (Throwable $e) {
            // A wall that misses a nudge still polls; a failed push must not fail the action.
            Log::warning('LED display broadcast failed: ' . $e->getMessage());
        }
    }

    public function broadcastOn(): Channel
    {
        return new Channel('led.' . $this->displayId);
    }

    public function broadcastAs(): string
    {
        return 'led.changed';
    }

    public function broadcastWith(): array
    {
        return ['display' => $this->displayId];
    }
}

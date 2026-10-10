<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The score of a match changed (ball, undo, innings switch, toss). A nudge with
 * the new score_version: a screen already at that version ignores it, anything
 * older refetches the public state.
 */
class MatchScoreUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public int $matchId, public int $version) {}

    public static function announce(int $matchId, int $version): void
    {
        try {
            event(new self($matchId, $version));
        } catch (Throwable $e) {
            Log::warning('Match score broadcast failed: ' . $e->getMessage());
        }
    }

    public function broadcastOn(): Channel
    {
        return new Channel('match.' . $this->matchId);
    }

    public function broadcastAs(): string
    {
        return 'score.updated';
    }

    public function broadcastWith(): array
    {
        return ['match' => $this->matchId, 'version' => $this->version];
    }
}

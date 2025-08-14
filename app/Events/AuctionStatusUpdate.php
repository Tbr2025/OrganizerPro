<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AuctionStatusUpdate implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;
    public $auctionId;
    public $status;
    public function __construct(int $auctionId, string $status)
    {
        $this->auctionId = $auctionId;
        $this->status = $status;
    }
    public function broadcastOn(): Channel
    {
        return new Channel('auction.public.' . $this->auctionId);
    }
    public function broadcastAs(): string
    {
        return 'auction.status';
    }
}

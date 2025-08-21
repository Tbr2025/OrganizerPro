<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AuctionEndedEvent implements ShouldBroadcast
{
    public $auctionId;
    public function __construct($auctionId) {
        $this->auctionId = $auctionId;
    }
    public function broadcastOn() {
        return new PrivateChannel('auction.' . $this->auctionId);
    }
    public function broadcastAs() {
        return 'auction-ended';
    }
}
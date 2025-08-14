<?php

namespace App\Events;

use App\Models\AuctionBid;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewBidPlaced implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;
    public $bid;
    public function __construct(AuctionBid $bid)
    {
        $this->bid = $bid->load(['team', 'user']);
    }
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('auction.private.' . $this->bid->auction_id);
    }
    public function broadcastAs(): string
    {
        return 'bid.new';
    }
}

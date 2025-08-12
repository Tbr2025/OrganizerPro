<?php

namespace App\Events;

use App\Models\AuctionBid;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class BidPlaced implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public $auctionId;
    public $bid;

    public function __construct($auctionId, AuctionBid $bid)
    {
        $this->auctionId = $auctionId;
        $this->bid = $bid;
    }

    public function broadcastOn()
    {
        return new Channel('auction.' . $this->auctionId);
    }

    public function broadcastWith()
    {
        return [
            'team_id' => $this->bid->team_id,
            'bid_amount' => $this->bid->bid_amount,
            'player_id' => $this->bid->auction_player_id,
        ];
    }
}

<?php

namespace App\Events;

use App\Models\AuctionPlayer;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlayerOnBid implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;
    public $auctionPlayer;
    public function __construct(AuctionPlayer $auctionPlayer)
    {
        $this->auctionPlayer = $auctionPlayer->load(['player.playerType', 'player.battingProfile', 'player.bowlingProfile', 'player.location', 'player.kitSize']);
    }
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('auction.private.' . $this->auctionPlayer->auction_id);
    }
    public function broadcastAs(): string
    {
        return 'player.onbid';
    }
}

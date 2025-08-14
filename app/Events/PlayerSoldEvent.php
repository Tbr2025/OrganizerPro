<?php

namespace App\Events;

use App\Models\AuctionPlayer;
use App\Models\ActualTeam;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlayerSoldEvent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;
    public $auctionPlayer;
    public $winningTeam;
    public function __construct(AuctionPlayer $auctionPlayer, ActualTeam $winningTeam = null)
    {
        $this->auctionPlayer = $auctionPlayer->load(['player']);
        $this->winningTeam = $winningTeam;
    }
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('auction.private.' . $this->auctionPlayer->auction_id);
    }
    public function broadcastAs(): string
    {
        return 'player.sold';
    }
}

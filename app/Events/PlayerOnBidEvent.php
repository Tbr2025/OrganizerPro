<?php

namespace App\Events;

use App\Models\ActualTeam;
use App\Models\AuctionPlayer;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlayerOnBidEvent implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $auctionPlayer;
    public $team;

    public function __construct(AuctionPlayer $auctionPlayer, ?ActualTeam $team = null)
    {
        // Ensure relationships are loaded
        $this->auctionPlayer = $auctionPlayer->load([
            'player.player_type',       // Ensure Player model has belongsTo relation
            'player.batting_profile',
            'player.bowling_profile',
        ]);

        $this->team = $team;
    }

    // Use PrivateChannel if you need authentication
    public function broadcastOn()
    {
        return new Channel('auction.' . $this->auctionPlayer->auction_id);
    }
    public function broadcastWith()
    {
        return [
            'id' => $this->auctionPlayer->id,
            'player' => $this->auctionPlayer->player,
            'current_price' => $this->auctionPlayer->final_price,
            'status' => $this->auctionPlayer->status,

        ];
    }
    public function broadcastAs()
    {
        return 'player-on-bid';
    }
}

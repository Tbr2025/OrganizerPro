<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\AuctionPlayer;
use App\Models\ActualTeam;

class PlayerSoldEvent implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $auctionPlayer;
    public $winningTeam;

    public function __construct(AuctionPlayer $auctionPlayer, ?ActualTeam $team = null)
    {
        $this->auctionPlayer = $auctionPlayer->load([
            'player.playerType',
            'player.battingProfile',
            'player.bowlingProfile',
            'soldToTeam'
        ]);
        $this->winningTeam = $team;
    }

    public function broadcastOn()
    {
        return new Channel('auction.' . $this->auctionPlayer->auction_id);
    }

    public function broadcastWith()
    {
        return [
            'auctionPlayer' => $this->auctionPlayer,
            'winningTeam' => $this->winningTeam ? [
                'id' => $this->winningTeam->id,
                'name' => $this->winningTeam->name,
                'logo_path' => $this->winningTeam->logo_path,
            ] : null,
        ];
    }

    public function broadcastAs()
    {
        return 'player-on-sold';
    }
}

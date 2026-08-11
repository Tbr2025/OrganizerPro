<?php

namespace App\Events;

use App\Models\ActualTeam;
use App\Models\AuctionPlayer;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlayerOnBidEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

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
    /**
     * Shaped as `auctionPlayer`, because that is what both listeners read.
     *
     * This used to be a FLAT payload — {id, player, current_price, status} — while the LED
     * wall and the auction detail page both read `e.auctionPlayer.…`. So even once the name
     * matched, every handler took `event.auctionPlayer`, got undefined and returned.
     *
     * And `current_price` was read from `final_price`, which is only written when a player
     * is SOLD. During bidding — the entire point of this event — it is null.
     */
    public function broadcastWith()
    {
        return [
            'auctionPlayer' => [
                'id' => $this->auctionPlayer->id,
                'player' => $this->auctionPlayer->player,
                'status' => $this->auctionPlayer->status,
                'base_price' => $this->auctionPlayer->base_price,
                // The live figure, not the sale figure.
                'current_price' => $this->auctionPlayer->current_price,
                'current_bid_team_id' => $this->auctionPlayer->current_bid_team_id,
                'current_bid_team' => $this->team,
            ],
        ];
    }

    /**
     * `player.onbid`, matching the two listeners that exist.
     *
     * It returned `player-on-bid` while every listener asked for `.player.onbid`. A leading
     * dot in Echo means "this exact name", so the two never met: no bid has ever reached a
     * screen by broadcast, and every price change on the LED wall waited for the next
     * two-second poll. PlayerSoldEvent got this right, which is why sales appeared at once
     * and bids did not.
     */
    public function broadcastAs()
    {
        return 'player.onbid';
    }
}

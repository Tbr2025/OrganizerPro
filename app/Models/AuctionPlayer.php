<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuctionPlayer extends Model
{
    protected $fillable = ['auction_id', 'player_id', 'base_price', 'status', 'current_price', 'current_bid_team_id'];
    public function auction()
    {
        return $this->belongsTo(Auction::class);
    }
    public function player()
    {
        return $this->belongsTo(Player::class);
    }
    public function bids()
    {
        return $this->hasMany(Bid::class);
    }
}

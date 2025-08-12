<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bid extends Model
{
    protected $fillable = ['auction_id', 'auction_player_id', 'team_id', 'user_id', 'amount'];
    public function auctionPlayer()
    {
        return $this->belongsTo(AuctionPlayer::class);
    }
    public function team()
    {
        return $this->belongsTo(Team::class);
    }
}

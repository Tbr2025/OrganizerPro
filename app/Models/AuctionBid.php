<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuctionBid extends Model
{
    use HasFactory;
    protected $fillable = ['auction_id', 'auction_player_id', 'team_id', 'user_id', 'amount'];
    protected $casts = ['amount' => 'decimal:2'];
    public function auction()
    {
        return $this->belongsTo(Auction::class);
    }
    public function auctionPlayer()
    {
        return $this->belongsTo(AuctionPlayer::class);
    }
    protected $table = 'auction_bids';

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuctionPlayer extends Model
{
    use HasFactory;
    protected $fillable = ['auction_id', 'player_id', 'organization_id', 'team_id', 'base_price', 'starting_price', 'retained_price', 'status', 'current_price', 'current_bid_team_id', 'sold_to_team_id', 'final_price', 'player_status'];
    protected $casts = ['current_price' => 'decimal:2', 'final_price' => 'decimal:2'];
    public function auction()
    {
        return $this->belongsTo(Auction::class);
    }
    public function player()
    {
        return $this->belongsTo(Player::class);
    }
    public function team()
    {
        return $this->belongsTo(ActualTeam::class, 'team_id');
    }
    public function currentBidTeam()
    {
        return $this->belongsTo(ActualTeam::class, 'current_bid_team_id');
    }
    public function soldToTeam()
    {
        return $this->belongsTo(ActualTeam::class, 'sold_to_team_id');
    }
    public function bids()
    {
        return $this->hasMany(AuctionBid::class);
    }
}

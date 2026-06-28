<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToOrganization;

class AuctionPlayer extends Model
{
    use HasFactory, BelongsToOrganization;
    protected $fillable = ['auction_id', 'auction_pool_id', 'lot_number', 'player_id', 'organization_id', 'team_id', 'base_price', 'starting_price', 'retained_price', 'status', 'current_price', 'current_bid_team_id', 'sold_to_team_id', 'final_price', 'player_status'];
    protected $casts = ['current_price' => 'decimal:2', 'final_price' => 'decimal:2', 'lot_number' => 'integer'];
    public function auction()
    {
        return $this->belongsTo(Auction::class);
    }
    public function pool()
    {
        return $this->belongsTo(AuctionPool::class, 'auction_pool_id');
    }

    /** Order players by their pool sequence then lot number (nulls last). */
    public function scopeInLotOrder($query)
    {
        return $query
            ->leftJoin('auction_pools', 'auction_pools.id', '=', 'auction_players.auction_pool_id')
            ->orderByRaw('auction_pools.sequence IS NULL, auction_pools.sequence')
            ->orderByRaw('auction_players.lot_number IS NULL, auction_players.lot_number')
            ->select('auction_players.*');
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
        return $this->hasMany(AuctionBid::class, 'auction_player_id', 'id');
    }

    
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Auction extends Model
{

    protected $fillable = [
         'name',
        'organization_id',
        'tournament_id',
        'start_at',
        'end_at',
        'status',
        'base_price',
        'max_bid_per_player',
        'max_budget_per_team',
        'bid_rules', 
    ];
    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'bid_rules' => 'array'
    ];

    public function players()
    {
        return $this->hasMany(AuctionPlayer::class);
    }
    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
    public function auctionPlayers()
    {
        return $this->hasMany(AuctionPlayer::class);
    }
    public function bids()
    {
        return $this->hasMany(AuctionBid::class);
    }
}

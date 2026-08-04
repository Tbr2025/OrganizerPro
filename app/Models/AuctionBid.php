<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuctionBid extends Model
{
    use HasFactory;
    protected $fillable = [
        'auction_id',
        'auction_player_id',
        'player_id',
        'team_id',
        'user_id',
        'amount',
        'bid_source',
        'is_void',
        'voided_by',
        'voided_at',
    ];
    protected $casts = [
        'amount' => 'decimal:2',
        'is_void' => 'boolean',
        'voided_at' => 'datetime',
    ];
    public function auction()
    {
        return $this->belongsTo(Auction::class);
    }
    public function auctionPlayer()
    {
        return $this->belongsTo(AuctionPlayer::class);
    }
    protected $table = 'auction_bids';

    /**
     * Live bids only. The log is append-only, so a retracted (undone) bid stays
     * in the table flagged as void and must be excluded from prices and totals.
     */
    public function scopeLive($query)
    {
        return $query->where('is_void', false);
    }

    public function team()
    {
        return $this->belongsTo(ActualTeam::class, 'team_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

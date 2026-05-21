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
        'bid_type',
        'bid_timer_seconds',
        'bid_timer_reset_seconds',
        'open_bid_mode',
        'online_bid_limit_from',
        'online_bid_limit_to',
        'mode_manually_overridden',
        'closed_bid_starts_at',
    ];
    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'bid_rules' => 'array',
        'bid_timer_seconds' => 'integer',
        'bid_timer_reset_seconds' => 'integer',
        'online_bid_limit_from' => 'decimal:2',
        'online_bid_limit_to' => 'decimal:2',
        'mode_manually_overridden' => 'boolean',
        'closed_bid_starts_at' => 'decimal:2',
    ];

    /**
     * Check if this auction has online/offline mode configured.
     */
    public function hasOnlineOfflineMode(): bool
    {
        return $this->online_bid_limit_from !== null
            && $this->online_bid_limit_to !== null;
    }

    /**
     * Check if this auction has auto phase transition (open → closed → offline) configured.
     */
    public function hasAutoPhaseTransition(): bool
    {
        return $this->closed_bid_starts_at !== null;
    }

    public function isOnlineMode(): bool
    {
        return $this->open_bid_mode === 'online';
    }

    public function isOfflineMode(): bool
    {
        return $this->open_bid_mode === 'offline';
    }

    /**
     * Determine the expected bid phase and mode based on the current price.
     */
    public function getExpectedBidPhase(float $price): array
    {
        $bidType = 'open';
        $mode = 'online';

        if ($this->closed_bid_starts_at !== null && $price >= (float) $this->closed_bid_starts_at) {
            $bidType = 'closed';
        }
        if ($this->hasOnlineOfflineMode() && $price > (float) $this->online_bid_limit_to) {
            $mode = 'offline';
        }

        return ['bid_type' => $bidType, 'open_bid_mode' => $mode];
    }

    /**
     * Determine the expected bid mode based on the current price.
     */
    public function getExpectedBidMode(float $price): string
    {
        if (!$this->hasOnlineOfflineMode()) {
            return 'online';
        }

        if ($price > (float) $this->online_bid_limit_to) {
            return 'offline';
        }

        return 'online';
    }

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

    /**
     * Get all players in this auction through AuctionPlayer
     */
    public function allPlayers()
    {
        return $this->hasManyThrough(
            Player::class,
            AuctionPlayer::class,
            'auction_id',
            'id',
            'id',
            'player_id'
        );
    }
}

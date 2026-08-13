<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToOrganization;

class AuctionPlayer extends Model
{
    use HasFactory;
    use BelongsToOrganization;
    protected $fillable = ['auction_id', 'auction_pool_id', 'source_pool_id', 'lot_number', 'player_id', 'organization_id', 'team_id', 'base_price', 'starting_price', 'retained_price', 'status', 'is_retained', 'current_price', 'current_bid_team_id', 'sold_to_team_id', 'final_price', 'closed_bid_round_id'];
    // retained_price/base_price/starting_price were left uncast and came back as raw
    // strings, unlike the two money columns beside them.
    protected $casts = ['current_price' => 'decimal:2', 'final_price' => 'decimal:2', 'base_price' => 'decimal:2', 'starting_price' => 'decimal:2', 'retained_price' => 'decimal:2', 'lot_number' => 'integer', 'is_retained' => 'boolean'];
    public function auction()
    {
        return $this->belongsTo(Auction::class);
    }

    /**
     * The sealed round currently in play for this player, if any.
     *
     * Deliberately not eager-loaded anywhere and never included in a broadcast payload:
     * the round's entries carry sealed amounts.
     */
    public function closedBidRound()
    {
        return $this->belongsTo(AuctionClosedBidRound::class, 'closed_bid_round_id');
    }

    public function closedBidRounds()
    {
        return $this->hasMany(AuctionClosedBidRound::class);
    }
    public function pool()
    {
        return $this->belongsTo(AuctionPool::class, 'auction_pool_id');
    }

    /**
     * The pool this player was in when nobody bid on them.
     *
     * Unsold players share one pile per auction, so `pool` answers "where are they now" and
     * this answers "where did they come from" — which is what re-auction needs to put them
     * back somewhere biddable. Null for anyone who has never gone unsold.
     */
    public function sourcePool()
    {
        return $this->belongsTo(AuctionPool::class, 'source_pool_id');
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

    /**
     * Bids that still stand. The bid log is append-only so Undo can walk it, and
     * retracted bids remain in the table flagged void — anything deciding a
     * price, a winner or a spend total must use this, not bids().
     */
    public function liveBids()
    {
        return $this->hasMany(AuctionBid::class, 'auction_player_id', 'id')
            ->where('is_void', false);
    }

}

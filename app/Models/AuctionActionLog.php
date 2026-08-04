<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per reversible auction action, holding a snapshot of the state that
 * action replaced. Undo pops the newest un-undone row and restores its payload.
 */
class AuctionActionLog extends Model
{
    public const ACTION_BID = 'bid';
    public const ACTION_SELL = 'sell';
    public const ACTION_PASS = 'pass';
    public const ACTION_SKIP = 'skip';
    public const ACTION_ALLOT = 'allot';

    /** Actions Undo knows how to reverse. */
    public const REVERSIBLE = [
        self::ACTION_BID,
        self::ACTION_SELL,
        self::ACTION_PASS,
        self::ACTION_SKIP,
        self::ACTION_ALLOT,
    ];

    protected $fillable = [
        'auction_id',
        'auction_player_id',
        'action',
        'payload',
        'description',
        'user_id',
        'undone_by',
        'undone_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'undone_at' => 'datetime',
    ];

    public function auction()
    {
        return $this->belongsTo(Auction::class);
    }

    public function auctionPlayer()
    {
        return $this->belongsTo(AuctionPlayer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Actions that have not yet been undone. */
    public function scopePending($query)
    {
        return $query->whereNull('undone_at');
    }
}

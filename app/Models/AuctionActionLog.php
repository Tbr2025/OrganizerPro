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

    // Sealed rounds. Each must appear in REVERSIBLE below as well, or nextUndoable()
    // walks straight past it and the action silently cannot be undone.
    public const ACTION_CLOSED_BID = 'closed_bid';
    public const ACTION_CLOSED_ADJUST = 'closed_adjust';
    public const ACTION_CLOSED_WITHDRAW = 'closed_withdraw';
    public const ACTION_CLOSED_LOT = 'closed_lot';

    /*
     * Lock & Reveal, so it can be stepped back like anything else.
     *
     * It used not to be recorded at all, which meant UNDO skipped straight over it to the
     * last sealed bid — and undoing a bid under a revealed board is refused, because the
     * winner on screen was worked out from the amounts being changed. So a revealed round
     * could not be walked back at all: the one action that needed reversing was the one
     * the stack did not know about.
     */
    public const ACTION_CLOSED_REVEAL = 'closed_reveal';

    /** Actions Undo knows how to reverse. */
    public const REVERSIBLE = [
        self::ACTION_BID,
        self::ACTION_SELL,
        self::ACTION_PASS,
        self::ACTION_SKIP,
        self::ACTION_ALLOT,
        self::ACTION_CLOSED_BID,
        self::ACTION_CLOSED_ADJUST,
        self::ACTION_CLOSED_WITHDRAW,
        self::ACTION_CLOSED_LOT,
        self::ACTION_CLOSED_REVEAL,
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

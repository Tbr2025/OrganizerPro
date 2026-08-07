<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One sealed-bid round for one player.
 *
 * The player stays `on_auction` throughout; the sealed phase is this row's `state`.
 *
 * ## Termination
 *
 * `round_number` runs 1 … 1 + `closed_bid_max_rebid_rounds`. A tie at the last round
 * goes to the lot, so the ladder always ends. Two rules make that guarantee hold:
 *
 *  - A team that was required to re-bid and did not submit before the lock is treated as
 *    having left the tie. Its previous amount is deliberately NOT carried forward —
 *    doing so would recreate the same tie every round and the ladder would never finish.
 *  - If EVERY required team fails to submit and no optional team submitted either, the
 *    round goes straight to `awaiting_lot` with the parent's tied set as candidates,
 *    rather than sending a genuinely contested player to unsold.
 */
class AuctionClosedBidRound extends Model
{
    public const STATE_PENDING = 'pending';
    public const STATE_ENTRY_OPEN = 'entry_open';
    public const STATE_COLLECTING = 'collecting';
    public const STATE_LOCKED = 'locked';
    public const STATE_REVEALED = 'revealed';
    public const STATE_TIE = 'tie';
    public const STATE_AWAITING_LOT = 'awaiting_lot';
    public const STATE_NO_ENTRIES = 'no_entries';
    public const STATE_AWARDED = 'awarded';
    public const STATE_UNSOLD = 'unsold';
    public const STATE_ABANDONED = 'abandoned';

    /** States in which the round is finished and must not be mutated further. */
    public const TERMINAL_STATES = [
        self::STATE_AWARDED,
        self::STATE_UNSOLD,
        self::STATE_ABANDONED,
    ];

    public const RESOLUTION_HIGHEST = 'highest';
    public const RESOLUTION_LOT = 'lot';
    public const RESOLUTION_MANUAL = 'manual';
    public const RESOLUTION_LEADER_AT_THRESHOLD = 'leader_at_threshold';
    public const RESOLUTION_UNSOLD = 'unsold';
    public const RESOLUTION_ABANDONED = 'abandoned';

    /** The algorithm string recorded with a draw, so an old record stays interpretable. */
    public const LOT_ALGORITHM = 'hmac-sha256-mod-v1';

    protected $fillable = [
        'auction_id',
        'auction_player_id',
        'attempt_no',
        'round_number',
        'parent_round_id',
        'state',
        'floor',
        'step',
        'max_pct_of_budget',
        'leader_team_id',
        'leader_amount',
        'timer_seconds',
        'timer_started_at',
        'opened_at',
        'locked_at',
        'revealed_at',
        'resolved_at',
        'abandoned_at',
        'opened_by',
        'locked_by',
        'resolved_by',
        'tie_amount',
        'tied_team_ids',
        'resolution',
        'lot_algorithm',
        'lot_seed',
        'lot_candidates',
        'lot_winner_team_id',
        'lot_drawn_at',
        'winner_team_id',
        'winning_amount',
    ];

    protected $casts = [
        'attempt_no' => 'integer',
        'round_number' => 'integer',
        'floor' => 'decimal:2',
        'step' => 'decimal:2',
        'max_pct_of_budget' => 'decimal:2',
        'leader_amount' => 'decimal:2',
        'tie_amount' => 'decimal:2',
        'winning_amount' => 'decimal:2',
        'timer_seconds' => 'integer',
        'timer_started_at' => 'datetime',
        'opened_at' => 'datetime',
        'locked_at' => 'datetime',
        'revealed_at' => 'datetime',
        'resolved_at' => 'datetime',
        'abandoned_at' => 'datetime',
        'lot_drawn_at' => 'datetime',
        'tied_team_ids' => 'array',
        'lot_candidates' => 'array',
    ];

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public function auctionPlayer(): BelongsTo
    {
        return $this->belongsTo(AuctionPlayer::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(AuctionClosedBidEntry::class, 'auction_closed_bid_round_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_round_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_round_id');
    }

    public function leaderTeam(): BelongsTo
    {
        return $this->belongsTo(ActualTeam::class, 'leader_team_id');
    }

    public function winnerTeam(): BelongsTo
    {
        return $this->belongsTo(ActualTeam::class, 'winner_team_id');
    }

    /** Rounds still in play. */
    public function scopeOpen($query)
    {
        return $query->whereNotIn('state', self::TERMINAL_STATES);
    }

    public function isTerminal(): bool
    {
        return in_array($this->state, self::TERMINAL_STATES, true);
    }

    /** Are teams able to submit right now? */
    public function isCollecting(): bool
    {
        return $this->state === self::STATE_COLLECTING && $this->locked_at === null;
    }

    /** Have the amounts been made visible? Nothing may be adjusted after this. */
    public function isRevealed(): bool
    {
        return $this->revealed_at !== null;
    }

    /** The last round the ladder allows before the lot. */
    public function isFinalRound(): bool
    {
        $max = 1 + ($this->auction?->closedBidMaxRebidRounds() ?? Auction::CLOSED_BID_MAX_REBIDS);

        return $this->round_number >= $max;
    }

    /**
     * The bids that count.
     *
     * Every winner and tie computation goes through this, so there is exactly one
     * definition of "a standing bid" — and a withdrawal needs no re-award code path at
     * all, because the same query simply returns a different row.
     */
    public function standingEntries()
    {
        return $this->entries()->standing();
    }
}

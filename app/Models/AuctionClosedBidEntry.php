<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One team's participation in one sealed round.
 *
 * `amount` is null until the team submits — "has not bid" and "bid zero" are different
 * facts and the column keeps them apart.
 */
class AuctionClosedBidEntry extends Model
{
    public const STATE_INVITED = 'invited';
    public const STATE_ACCEPTED = 'accepted';
    public const STATE_DECLINED = 'declined';
    public const STATE_SUBMITTED = 'submitted';
    public const STATE_WITHDRAWN = 'withdrawn';
    public const STATE_MUST_REBID = 'must_rebid';
    public const STATE_MAY_OPT_IN = 'may_opt_in';
    public const STATE_NO_ENTRY = 'no_entry';

    public const ROLE_TEAM = 'team';
    public const ROLE_ADMIN = 'admin';

    protected $fillable = [
        'auction_closed_bid_round_id',
        'auction_id',
        'actual_team_id',
        'state',
        'amount',
        'ceiling_at_entry',
        'per_player_cap_at_entry',
        'reserve_at_entry',
        'slots_remaining_at_entry',
        'required',
        'accepted_at',
        'declined_at',
        'submitted_at',
        'submitted_by',
        'withdrawn_at',
        'withdrawn_by',
        'withdrawn_by_role',
        'reinstated_at',
        'reinstated_by',
        'adjustments',
        'adjusted_count',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'ceiling_at_entry' => 'decimal:2',
        'per_player_cap_at_entry' => 'decimal:2',
        'reserve_at_entry' => 'decimal:2',
        'slots_remaining_at_entry' => 'integer',
        'required' => 'boolean',
        'accepted_at' => 'datetime',
        'declined_at' => 'datetime',
        'submitted_at' => 'datetime',
        'withdrawn_at' => 'datetime',
        'reinstated_at' => 'datetime',
        'adjustments' => 'array',
        'adjusted_count' => 'integer',
    ];

    public function round(): BelongsTo
    {
        return $this->belongsTo(AuctionClosedBidRound::class, 'auction_closed_bid_round_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(ActualTeam::class, 'actual_team_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * A bid that counts: submitted, and not withdrawn.
     *
     * This is the single definition of a standing bid. Because the winner is
     * `standing()->orderBy(...)->first()`, "if the highest bidder withdraws the player
     * goes to the next highest" needs no re-award branch — the same query returns a
     * different row.
     */
    public function scopeStanding($query)
    {
        return $query->where('state', self::STATE_SUBMITTED)->whereNull('withdrawn_at');
    }

    /** Teams obliged to bid again in a tie-break round. */
    public function scopeRequired($query)
    {
        return $query->where('required', true);
    }

    public function isStanding(): bool
    {
        return $this->state === self::STATE_SUBMITTED && $this->withdrawn_at === null;
    }

    public function isWithdrawn(): bool
    {
        return $this->withdrawn_at !== null;
    }

    /** An admin withdrawal is a correction; a team's own is a decision to leave. */
    public function wasWithdrawnByAdmin(): bool
    {
        return $this->isWithdrawn() && $this->withdrawn_by_role === self::ROLE_ADMIN;
    }
}

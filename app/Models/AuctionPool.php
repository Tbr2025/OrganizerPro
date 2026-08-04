<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuctionPool extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    public const MODE_SEQUENTIAL = 'sequential';
    public const MODE_RANDOM = 'random';
    public const MODE_ODD_EVEN = 'odd_even';
    public const MODE_MANUAL = 'manual';

    /** Pool lifecycle. Exactly one pool per auction may be `active`. */
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'auction_id',
        'organization_id',
        'name',
        'capacity',
        'base_price',
        'category',
        'order_mode',
        'sequence',
        'status',
        'is_unsold_pool',
        'parent_pool_id',
        'is_enabled',
        'times_used',
        'activated_at',
        'completed_at',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'base_price' => 'decimal:2',
        'sequence' => 'integer',
        'is_unsold_pool' => 'boolean',
        'is_enabled' => 'boolean',
        'times_used' => 'integer',
        'activated_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function players(): HasMany
    {
        return $this->hasMany(AuctionPlayer::class, 'auction_pool_id');
    }

    /** Players in their drawn lot order. */
    public function playersInLotOrder(): HasMany
    {
        return $this->players()->orderByRaw('lot_number IS NULL, lot_number');
    }

    public function isFull(): bool
    {
        return $this->capacity !== null && $this->players()->count() >= $this->capacity;
    }

    /** Players still to be auctioned from this pool (retained members excluded). */
    public function waitingPlayers(): HasMany
    {
        return $this->players()
            ->where('status', 'waiting')
            ->where('is_retained', false);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /** A disabled pool is kept for the record but never served to the auction. */
    public function isEnabled(): bool
    {
        return (bool) ($this->is_enabled ?? true);
    }

    /** Nothing left to auction from this pool. */
    public function isExhausted(): bool
    {
        return $this->waitingPlayers()->count() === 0;
    }

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    /** Pools that hold players still to be auctioned (i.e. not unsold holding pools). */
    public function scopeBiddable($query)
    {
        return $query->where('is_unsold_pool', false);
    }

    /** The pool this unsold holding pool collects players for. */
    public function parentPool(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_pool_id');
    }

    /** Companion pool collecting the players this pool failed to sell. */
    public function unsoldPool(): HasMany
    {
        return $this->hasMany(self::class, 'parent_pool_id')->where('is_unsold_pool', true);
    }

    public function isUnsoldPool(): bool
    {
        return (bool) ($this->is_unsold_pool ?? false);
    }
}

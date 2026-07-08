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
    use HasFactory, BelongsToOrganization;

    public const MODE_SEQUENTIAL = 'sequential';
    public const MODE_RANDOM = 'random';
    public const MODE_ODD_EVEN = 'odd_even';
    public const MODE_MANUAL = 'manual';

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
    ];

    protected $casts = [
        'capacity' => 'integer',
        'base_price' => 'decimal:2',
        'sequence' => 'integer',
        'is_unsold_pool' => 'boolean',
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
}

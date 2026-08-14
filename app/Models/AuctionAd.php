<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A piece of sponsor artwork on the public screens.
 *
 * Two kinds in one table: a `slide` takes a whole turn of the reel, a `sponsor` rides the strip
 * along the bottom of every slide. Same upload, same ordering, same switch — see the migration
 * for why they are not two tables.
 */
class AuctionAd extends Model
{
    use HasFactory;

    /** A whole turn of the reel. */
    public const KIND_SLIDE = 'slide';

    /** The strip along the bottom of every slide. */
    public const KIND_SPONSOR = 'sponsor';

    protected $fillable = [
        'auction_id',
        'kind',
        'image_path',
        'caption',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** @return list<string> */
    public static function kinds(): array
    {
        return [self::KIND_SLIDE, self::KIND_SPONSOR];
    }

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    /**
     * A URL the public screens can load.
     *
     * Absolute, because the wall is often opened on a different host from the one that uploaded
     * the file — an OBS source, a phone, a projector on the venue's own network.
     */
    public function getUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailTemplate extends Model
{
    public const TYPE_UNDER_REVIEW = 'under_review';
    public const TYPE_APPROVED = 'approved';
    public const TYPE_WELCOME_CARD = 'welcome_card';
    public const TYPE_RETAINED_WELCOME_CARD = 'retained_welcome_card';

    /**
     * A player sold at auction, told who bought them and for how much.
     *
     * Separate from the registration welcome, which is what an auction sale used to borrow: that
     * email says "welcome aboard, complete your profile" and belongs to somebody joining a
     * tournament. A player who has just been bought in front of a hall is not being welcomed to
     * the tournament — they were already in it — and has no profile to complete.
     */
    public const TYPE_AUCTION_SOLD = 'auction_sold';

    public const TYPES = [
        self::TYPE_UNDER_REVIEW,
        self::TYPE_APPROVED,
        self::TYPE_WELCOME_CARD,
        self::TYPE_RETAINED_WELCOME_CARD,
        self::TYPE_AUCTION_SOLD,
    ];

    protected $fillable = [
        'tournament_id',
        'type',
        'subject',
        'body_html',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }
}

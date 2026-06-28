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

    public const TYPES = [
        self::TYPE_UNDER_REVIEW,
        self::TYPE_APPROVED,
        self::TYPE_WELCOME_CARD,
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

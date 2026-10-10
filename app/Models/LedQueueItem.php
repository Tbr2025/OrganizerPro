<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One panel waiting for, on, or done with the LED wall.
 *
 * queued → showing → shown, or skipped. "held" waits for the operator.
 * The payload is frozen when the item is queued, so what the operator
 * checks in the console is exactly what airs.
 */
class LedQueueItem extends Model
{
    protected $table = 'led_display_queue';

    public const WAITING = ['queued', 'held'];

    protected $fillable = [
        'led_display_id', 'panel', 'payload', 'summary', 'status', 'source', 'pinned', 'position',
        'show_seconds', 'air_after', 'ball_id', 'approved_by', 'shown_at', 'ends_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'pinned' => 'boolean',
        'position' => 'integer',
        'show_seconds' => 'integer',
        'air_after' => 'datetime',
        'shown_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function display(): BelongsTo
    {
        return $this->belongsTo(LedDisplay::class, 'led_display_id');
    }
}

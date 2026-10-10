<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One LED wall: a private link that any number of screens open, showing the
 * same panel at the same time. See App\Services\LedWall\LedQueueService.
 */
class LedDisplay extends Model
{
    /** Panels that fill the gaps between balls, in rotation order. */
    public const FILLERS = ['top_batting', 'top_bowling', 'partnership', 'tournament_batting', 'tournament_bowling', 'sponsor'];

    /** Panels the operator can put on screen by hand. */
    public const PANELS = [
        'scoreboard' => 'Live scoreboard',
        'batter_card' => 'Batter card',
        'bowler_card' => 'Bowler card',
        'last_over' => 'Last over',
        'partnership' => 'Partnership',
        'innings_summary' => 'Innings summary',
        'top_batting' => 'Top batting (match)',
        'top_bowling' => 'Top bowling (match)',
        'tournament_batting' => 'Top batting (tournament)',
        'tournament_bowling' => 'Top bowling (tournament)',
        'wicket' => 'Wicket',
        'boundary' => 'Boundary',
        'result' => 'Result',
        'sponsor' => 'Sponsors',
        'prematch' => 'Teams & toss',
    ];

    public const DEFAULT_SETTINGS = [
        // Seconds the live scoreboard stays up between balls before a filler.
        'rotation_seconds' => 20,
        // Seconds a filler panel stays up.
        'panel_seconds' => 8,
        // Seconds a match moment (wicket, boundary, over, result) stays up.
        'event_seconds' => 10,
        // Seconds a queued item waits before airing — the operator's window to skip it.
        'air_delay_seconds' => 5,
        // When true, match moments wait in "held" until the operator approves them.
        'require_approval' => false,
        'fillers' => self::FILLERS,
        'theme' => 'dark',
        'reduced_motion' => false,
    ];

    protected $fillable = [
        'tournament_id', 'match_id', 'name', 'token', 'mode', 'frozen', 'settings', 'revoked_at', 'created_by',
    ];

    protected $casts = [
        'frozen' => 'boolean',
        'settings' => 'array',
        'revoked_at' => 'datetime',
    ];

    protected $hidden = ['token'];

    public static function newToken(): string
    {
        return Str::random(40);
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(Matches::class, 'match_id');
    }

    public function queue(): HasMany
    {
        return $this->hasMany(LedQueueItem::class, 'led_display_id');
    }

    /** Settings with every default filled in. */
    public function setting(string $key): mixed
    {
        return ($this->settings ?? [])[$key] ?? self::DEFAULT_SETTINGS[$key] ?? null;
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One outbound auction email waiting to be sent.
 *
 * Held rather than sent so a live auction never blocks on SMTP, and so a rehearsal can
 * be run without mailing real players.
 */
class AuctionPendingEmail extends Model
{
    public const TYPE_WELCOME_CARD = 'welcome_card';
    public const TYPE_SOLD = 'sold';
    public const TYPE_UNSOLD = 'unsold';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'auction_id',
        'auction_player_id',
        'player_id',
        'actual_team_id',
        'type',
        'payload',
        'status',
        'error',
        'sent_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'sent_at' => 'datetime',
    ];

    public function auction()
    {
        return $this->belongsTo(Auction::class);
    }

    public function auctionPlayer()
    {
        return $this->belongsTo(AuctionPlayer::class);
    }

    public function player()
    {
        return $this->belongsTo(Player::class);
    }

    public function team()
    {
        return $this->belongsTo(ActualTeam::class, 'actual_team_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /** Human label for the outbox listing. */
    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_WELCOME_CARD => 'Welcome to team',
            self::TYPE_SOLD => 'Sold notification',
            self::TYPE_UNSOLD => 'Unsold notification',
            default => ucfirst(str_replace('_', ' ', $this->type)),
        };
    }
}

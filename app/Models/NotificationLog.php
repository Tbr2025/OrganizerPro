<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NotificationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'notifiable_type',
        'notifiable_id',
        'type',
        'channel',
        'recipient',
        'image_path',
        'status',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public const TYPE_WELCOME_CARD = 'welcome_card';
    public const TYPE_FLYER = 'flyer';
    public const TYPE_MATCH_POSTER = 'match_poster';
    public const TYPE_MATCH_SUMMARY = 'match_summary';
    public const TYPE_AWARD_POSTER = 'award_poster';
    public const TYPE_REGISTRATION_STATUS = 'registration_status';

    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_WHATSAPP = 'whatsapp_link';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    // Relationships
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeSent($query)
    {
        return $query->where('status', self::STATUS_SENT);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeViaEmail($query)
    {
        return $query->where('channel', self::CHANNEL_EMAIL);
    }

    public function scopeViaWhatsApp($query)
    {
        return $query->where('channel', self::CHANNEL_WHATSAPP);
    }

    public function scopeForTournament($query, int $tournamentId)
    {
        return $query->where('tournament_id', $tournamentId);
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Helpers
    public function markAsSent(): void
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
        ]);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function getTypeDisplayAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_WELCOME_CARD => 'Welcome Card',
            self::TYPE_FLYER => 'Tournament Flyer',
            self::TYPE_MATCH_POSTER => 'Match Poster',
            self::TYPE_MATCH_SUMMARY => 'Match Summary',
            self::TYPE_AWARD_POSTER => 'Award Poster',
            self::TYPE_REGISTRATION_STATUS => 'Registration Status',
            default => ucfirst(str_replace('_', ' ', $this->type)),
        };
    }

    public function getChannelDisplayAttribute(): string
    {
        return match ($this->channel) {
            self::CHANNEL_EMAIL => 'Email',
            self::CHANNEL_WHATSAPP => 'WhatsApp',
            default => ucfirst($this->channel),
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'bg-yellow-100 text-yellow-800',
            self::STATUS_SENT => 'bg-green-100 text-green-800',
            self::STATUS_FAILED => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? asset('storage/' . $this->image_path) : null;
    }

    /**
     * Create a log entry for a notification
     */
    public static function log(
        Tournament $tournament,
        Model $notifiable,
        string $type,
        string $channel,
        string $recipient,
        ?string $imagePath = null,
        string $status = self::STATUS_PENDING
    ): self {
        return self::create([
            'tournament_id' => $tournament->id,
            'notifiable_type' => get_class($notifiable),
            'notifiable_id' => $notifiable->id,
            'type' => $type,
            'channel' => $channel,
            'recipient' => $recipient,
            'image_path' => $imagePath,
            'status' => $status,
        ]);
    }
}

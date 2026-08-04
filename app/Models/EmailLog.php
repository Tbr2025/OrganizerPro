<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_BOUNCED = 'bounced';

    protected $fillable = [
        'to',
        'subject',
        'mailable_class',
        'status',
        'error_message',
        'sent_at',
        'body_html',
        'retry_count',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'retry_count' => 'integer',
    ];

    public function scopeSent($query)
    {
        return $query->where('status', self::STATUS_SENT);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeBounced($query)
    {
        return $query->where('status', self::STATUS_BOUNCED);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function isRetryable(): bool
    {
        return in_array($this->status, [self::STATUS_FAILED, self::STATUS_BOUNCED])
            && ! empty($this->body_html)
            && $this->retry_count < 3;
    }

    public function getMailableShortNameAttribute(): ?string
    {
        return $this->mailable_class ? class_basename($this->mailable_class) : null;
    }
}

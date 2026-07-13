<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BouncedEmail extends Model
{
    protected $fillable = [
        'email',
        'bounce_type',
        'bounce_subtype',
        'source',
        'bounced_at',
    ];

    protected $casts = [
        'bounced_at' => 'datetime',
    ];

    /**
     * Check if an email address has a permanent bounce on record.
     */
    public static function isBounced(string $email): bool
    {
        return static::where('email', strtolower($email))
            ->where('bounce_type', 'Permanent')
            ->exists();
    }
}

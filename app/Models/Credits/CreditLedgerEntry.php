<?php

declare(strict_types=1);

namespace App\Models\Credits;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One credit movement. Rows are written once and never edited or deleted:
 * a mistake is corrected by a new reversal or adjustment row.
 */
class CreditLedgerEntry extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'credit_ledger';

    protected $fillable = [
        'wallet_id', 'type', 'amount', 'balance_after', 'feature_key', 'reference_type', 'reference_id',
        'idempotency_key', 'discount_id', 'description', 'meta', 'created_by',
    ];

    protected $casts = ['amount' => 'integer', 'balance_after' => 'integer', 'meta' => 'array'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Credit ledger rows are immutable.'));
        static::deleting(fn () => throw new \LogicException('Credit ledger rows are immutable.'));
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(CreditWallet::class, 'wallet_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

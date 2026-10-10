<?php

declare(strict_types=1);

namespace App\Models\Credits;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An organization's credit balance. Only CreditService moves it. */
class CreditWallet extends Model
{
    protected $fillable = ['organization_id', 'balance'];

    protected $casts = ['balance' => 'integer'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function ledger(): HasMany
    {
        return $this->hasMany(CreditLedgerEntry::class, 'wallet_id');
    }
}

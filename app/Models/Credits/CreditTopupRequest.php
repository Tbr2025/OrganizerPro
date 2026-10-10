<?php

declare(strict_types=1);

namespace App\Models\Credits;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An organization asking for credits after paying offline; Super Admin approves. */
class CreditTopupRequest extends Model
{
    protected $fillable = [
        'organization_id', 'credits', 'price', 'currency', 'rate', 'discount_id', 'payment_reference', 'note',
        'status', 'requested_by', 'processed_by', 'processed_at', 'ledger_id', 'admin_note',
    ];

    protected $casts = ['credits' => 'integer', 'price' => 'decimal:2', 'rate' => 'decimal:4', 'processed_at' => 'datetime'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Credits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A discount on feature charges or on top-ups. With a code the organization
 * enters it; without one it applies automatically to everyone it targets.
 */
class Discount extends Model
{
    protected $fillable = [
        'name', 'code', 'type', 'value', 'applies_to', 'organization_ids', 'starts_at', 'ends_at',
        'max_uses', 'per_org_limit', 'is_active',
    ];

    protected $casts = [
        'organization_ids' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
        'value' => 'integer',
        'max_uses' => 'integer',
        'per_org_limit' => 'integer',
    ];

    public function redemptions(): HasMany
    {
        return $this->hasMany(DiscountRedemption::class);
    }

    /** Usable right now by this organization for this purpose ('topup' or a feature key)? */
    public function appliesTo(int $organizationId, string $purpose): bool
    {
        $now = now();
        if (! $this->is_active || ($this->starts_at && $this->starts_at->gt($now)) || ($this->ends_at && $this->ends_at->lt($now))) {
            return false;
        }
        $target = $purpose === 'topup' ? 'topup' : 'features';
        if ($this->applies_to !== $target && $this->applies_to !== 'feature:' . $purpose) {
            return false;
        }
        if ($this->organization_ids && ! in_array($organizationId, array_map('intval', $this->organization_ids), true)) {
            return false;
        }
        if ($this->max_uses !== null && $this->redemptions()->count() >= $this->max_uses) {
            return false;
        }
        if ($this->per_org_limit !== null && $this->redemptions()->where('organization_id', $organizationId)->count() >= $this->per_org_limit) {
            return false;
        }

        return true;
    }

    /** The reduction on an amount (credits or money), never more than the amount. */
    public function reduction(int|float $amount): int|float
    {
        $off = $this->type === 'percent' ? $amount * min(100, $this->value) / 100 : $this->value;

        return is_int($amount) ? (int) min($amount, floor($off)) : min($amount, round($off, 2));
    }
}

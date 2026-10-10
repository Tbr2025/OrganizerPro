<?php

declare(strict_types=1);

namespace App\Models\Credits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** A one-time unlock: this feature, for this organization, on this tournament. */
class CreditFeatureUnlock extends Model
{
    protected $fillable = ['organization_id', 'feature_key', 'scope_type', 'scope_id', 'ledger_id', 'unlocked_by'];

    public function scope(): MorphTo
    {
        return $this->morphTo();
    }
}

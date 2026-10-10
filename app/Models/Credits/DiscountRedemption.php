<?php

declare(strict_types=1);

namespace App\Models\Credits;

use Illuminate\Database\Eloquent\Model;

class DiscountRedemption extends Model
{
    protected $fillable = ['discount_id', 'organization_id', 'ledger_id', 'topup_request_id', 'saved'];
}

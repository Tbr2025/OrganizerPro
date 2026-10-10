<?php

declare(strict_types=1);

namespace App\Models\Credits;

use Illuminate\Database\Eloquent\Model;

/** One value of a credit setting; history is kept, the latest effective row wins. */
class CreditSetting extends Model
{
    protected $fillable = ['key', 'value', 'effective_from', 'changed_by'];

    protected $casts = ['effective_from' => 'datetime'];
}

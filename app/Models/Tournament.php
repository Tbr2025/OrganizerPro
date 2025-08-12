<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tournament extends Model
{
    protected $fillable = [
        'name',
        'start_date',
        'organization_id',
        'end_date',
        'location',
    ];
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];
}

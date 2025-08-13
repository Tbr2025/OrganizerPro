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

    public function organization()
    {
        // This line tells Laravel:
        // "This Tournament model belongs to an Organization model,
        // and you can find the correct one by matching the 'organization_id' on this tournament
        // with the 'id' on the organizations table."
        return $this->belongsTo(Organization::class, 'organization_id');
    }
}

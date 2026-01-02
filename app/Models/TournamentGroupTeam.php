<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentGroupTeam extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_group_id',
        'actual_team_id',
        'order',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(TournamentGroup::class, 'tournament_group_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(ActualTeam::class, 'actual_team_id');
    }
}

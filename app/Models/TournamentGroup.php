<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TournamentGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'name',
        'order',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(ActualTeam::class, 'tournament_group_teams')
            ->withPivot('order')
            ->withTimestamps()
            ->orderByPivot('order');
    }

    public function groupTeams(): HasMany
    {
        return $this->hasMany(TournamentGroupTeam::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(Matches::class, 'tournament_group_id');
    }

    public function pointTableEntries(): HasMany
    {
        return $this->hasMany(PointTableEntry::class);
    }

    public function getTeamCountAttribute(): int
    {
        return $this->teams()->count();
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** The seasons of one competition. Each season is its own Tournament. */
class TournamentSeries extends Model
{
    protected $table = 'tournament_series';

    protected $fillable = ['organization_id', 'name'];

    public function seasons(): HasMany
    {
        return $this->hasMany(Tournament::class, 'series_id')->orderBy('season_number');
    }
}

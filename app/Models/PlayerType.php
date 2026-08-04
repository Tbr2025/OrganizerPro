<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerType extends Model
{
    /**
     * The column is `type` — `$fillable` previously listed a `name` column that has
     * never existed, so any mass-assignment through this model failed outright.
     */
    protected $fillable = ['type'];

    /**
     * Read `name` as an alias for `type`.
     *
     * Much of the app reaches for `playerType->name`, which silently resolved to null —
     * so "Auto-assign by type" grouped every player into one "Uncategorized" pool, and
     * the player role came out blank on the control panel, the sold feed and the stream
     * ticker. Aliasing here fixes all of those at once, and the defensive
     * `?->type ?? ?->name` call sites elsewhere keep working unchanged.
     */
    public function getNameAttribute(): ?string
    {
        return $this->attributes['type'] ?? null;
    }
}

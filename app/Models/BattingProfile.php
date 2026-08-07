<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BattingProfile extends Model
{
    // The column is `style` (see create_player_related_tables). `$fillable = ['name']`
    // named a column that has never existed, so nothing was ever mass-assignable and
    // `battingProfile->name` read null on every screen that showed a batting style.
    protected $fillable = ['style'];

    /**
     * Alias so `->name` works alongside `->style`.
     *
     * Mirrors PlayerType, which had the same mismatch. Callers across the auction
     * panels and the public displays already ask for `->name`.
     */
    public function getNameAttribute(): ?string
    {
        return $this->attributes['style'] ?? null;
    }
}

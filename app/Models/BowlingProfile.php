<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BowlingProfile extends Model
{
    // The column is `style`, not `name` — same mismatch as BattingProfile.
    protected $fillable = ['style'];

    /**
     * Alias so `->name` works alongside `->style`.
     */
    public function getNameAttribute(): ?string
    {
        return $this->attributes['style'] ?? null;
    }
}

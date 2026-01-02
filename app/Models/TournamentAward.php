<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TournamentAward extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'name',
        'slug',
        'icon',
        'is_match_level',
        'is_active',
        'order',
        'template_image',
    ];

    protected $casts = [
        'is_match_level' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($award) {
            if (empty($award->slug)) {
                $award->slug = Str::slug($award->name);
            }
        });
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function matchAwards(): HasMany
    {
        return $this->hasMany(MatchAward::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeMatchLevel($query)
    {
        return $query->where('is_match_level', true);
    }

    public function scopeTournamentLevel($query)
    {
        return $query->where('is_match_level', false);
    }

    public function getTemplateImageUrlAttribute(): ?string
    {
        return $this->template_image ? asset('storage/' . $this->template_image) : null;
    }
}

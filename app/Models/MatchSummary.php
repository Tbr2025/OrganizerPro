<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        'highlights',
        'commentary',
        'summary_poster',
        'poster_template',
        'poster_sent',
        'poster_sent_at',
    ];

    protected $casts = [
        'highlights' => 'array',
        'poster_sent' => 'boolean',
        'poster_sent_at' => 'datetime',
    ];

    // Relationships
    public function match(): BelongsTo
    {
        return $this->belongsTo(Matches::class, 'match_id');
    }

    // Scopes
    public function scopePosterNotSent($query)
    {
        return $query->where('poster_sent', false);
    }

    public function scopePosterSent($query)
    {
        return $query->where('poster_sent', true);
    }

    public function scopeWithPoster($query)
    {
        return $query->whereNotNull('summary_poster');
    }

    // Helpers
    public function markPosterSent(): void
    {
        $this->update([
            'poster_sent' => true,
            'poster_sent_at' => now(),
        ]);
    }

    public function getPosterUrlAttribute(): ?string
    {
        return $this->summary_poster ? asset('storage/' . $this->summary_poster) : null;
    }

    public function addHighlight(string $highlight): void
    {
        $highlights = $this->highlights ?? [];
        $highlights[] = $highlight;
        $this->update(['highlights' => $highlights]);
    }

    public function removeHighlight(int $index): void
    {
        $highlights = $this->highlights ?? [];
        if (isset($highlights[$index])) {
            array_splice($highlights, $index, 1);
            $this->update(['highlights' => array_values($highlights)]);
        }
    }

    public function clearHighlights(): void
    {
        $this->update(['highlights' => []]);
    }

    public function hasHighlights(): bool
    {
        return !empty($this->highlights);
    }

    public function getHighlightsCountAttribute(): int
    {
        return count($this->highlights ?? []);
    }

    /**
     * Get or create summary for a match
     */
    public static function getOrCreate(Matches $match): self
    {
        return self::firstOrCreate(
            ['match_id' => $match->id],
            ['highlights' => [], 'commentary' => null]
        );
    }
}

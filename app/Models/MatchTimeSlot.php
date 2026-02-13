<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class MatchTimeSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'ground_id',
        'slot_date',
        'start_time',
        'end_time',
        'is_available',
        'match_id',
        'notes',
    ];

    protected $casts = [
        'slot_date' => 'date',
        'is_available' => 'boolean',
    ];

    // Relationships
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function ground(): BelongsTo
    {
        return $this->belongsTo(Ground::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(Matches::class, 'match_id');
    }

    // Scopes
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true)->whereNull('match_id');
    }

    public function scopeOccupied($query)
    {
        return $query->where('is_available', false)->orWhereNotNull('match_id');
    }

    public function scopeForTournament($query, int $tournamentId)
    {
        return $query->where('tournament_id', $tournamentId);
    }

    public function scopeForGround($query, int $groundId)
    {
        return $query->where('ground_id', $groundId);
    }

    public function scopeOnDate($query, $date)
    {
        return $query->whereDate('slot_date', $date);
    }

    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('slot_date', [$startDate, $endDate]);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('slot_date', '>=', now()->toDateString());
    }

    public function scopeOrderByDateTime($query)
    {
        return $query->orderBy('slot_date')->orderBy('start_time');
    }

    // Helpers
    public function isAvailable(): bool
    {
        return $this->is_available && $this->match_id === null;
    }

    public function isOccupied(): bool
    {
        return !$this->isAvailable();
    }

    public function assignMatch(Matches $match): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        $this->update([
            'match_id' => $match->id,
            'is_available' => false,
        ]);

        $match->update([
            'match_date' => $this->slot_date,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'ground_id' => $this->ground_id,
        ]);

        return true;
    }

    public function releaseMatch(): bool
    {
        if (!$this->match_id) {
            return false;
        }

        $match = $this->match;

        $this->update([
            'match_id' => null,
            'is_available' => true,
        ]);

        if ($match) {
            $match->update([
                'match_date' => null,
                'start_time' => null,
                'end_time' => null,
            ]);
        }

        return true;
    }

    public function getTimeRangeAttribute(): string
    {
        $start = Carbon::parse($this->start_time)->format('h:i A');
        $end = Carbon::parse($this->end_time)->format('h:i A');
        return "{$start} - {$end}";
    }

    public function getDisplayDateAttribute(): string
    {
        return $this->slot_date->format('D, M d, Y');
    }

    public function getDurationInMinutesAttribute(): int
    {
        $start = Carbon::parse($this->start_time);
        $end = Carbon::parse($this->end_time);
        return $start->diffInMinutes($end);
    }

    /**
     * Check if slot conflicts with another slot on same ground
     */
    public function conflictsWith(MatchTimeSlot $other): bool
    {
        if ($this->ground_id !== $other->ground_id) {
            return false;
        }

        if (!$this->slot_date->isSameDay($other->slot_date)) {
            return false;
        }

        $thisStart = Carbon::parse($this->start_time);
        $thisEnd = Carbon::parse($this->end_time);
        $otherStart = Carbon::parse($other->start_time);
        $otherEnd = Carbon::parse($other->end_time);

        return $thisStart < $otherEnd && $thisEnd > $otherStart;
    }
}

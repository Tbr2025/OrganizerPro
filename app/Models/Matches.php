<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Matches extends Model
{
    protected $fillable = [
        'tournament_id',
        'tournament_group_id',
        'name',
        'slug',
        'overs',
        'team_a_id',
        'team_b_id',
        'match_date',
        'venue',
        'ground_id',
        'status',
        'stage',
        'match_number',
        'start_time',
        'end_time',
        'winner_team_id',
        'poster_image',
        'poster_sent',
        'poster_sent_at',
        'is_cancelled',
        'cancellation_reason',
    ];

    protected $casts = [
        'match_date' => 'date',
        'poster_sent' => 'boolean',
        'poster_sent_at' => 'datetime',
        'is_cancelled' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($match) {
            if (empty($match->slug)) {
                $match->slug = Str::slug($match->name) . '-' . Str::random(6);
            }
        });
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TournamentGroup::class, 'tournament_group_id');
    }

    public function ground(): BelongsTo
    {
        return $this->belongsTo(Ground::class);
    }

    public function teamA(): BelongsTo
    {
        return $this->belongsTo(ActualTeam::class, 'team_a_id');
    }

    public function teamB(): BelongsTo
    {
        return $this->belongsTo(ActualTeam::class, 'team_b_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(ActualTeam::class, 'winner_team_id');
    }

    public function result(): HasOne
    {
        return $this->hasOne(MatchResult::class, 'match_id');
    }

    public function summary(): HasOne
    {
        return $this->hasOne(MatchSummary::class, 'match_id');
    }

    public function timeSlot(): HasOne
    {
        return $this->hasOne(MatchTimeSlot::class, 'match_id');
    }

    public function matchAwards(): HasMany
    {
        return $this->hasMany(MatchAward::class, 'match_id');
    }

    public function appreciations(): HasMany
    {
        return $this->hasMany(MatchAppreciation::class, 'match_id');
    }

    public function balls(): HasMany
    {
        return $this->hasMany(Ball::class, 'match_id');
    }

    // Scopes
    public function scopeUpcoming($query)
    {
        return $query->where('status', 'upcoming')->where('is_cancelled', false);
    }

    public function scopeLive($query)
    {
        return $query->where('status', 'live');
    }

    public function scopeCompletedMatches($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeNotCancelled($query)
    {
        return $query->where('is_cancelled', false);
    }

    public function scopeForGroup($query, $groupId)
    {
        return $query->where('tournament_group_id', $groupId);
    }

    public function scopeGroupStage($query)
    {
        return $query->where('stage', 'group');
    }

    public function scopeKnockoutStage($query)
    {
        return $query->whereIn('stage', ['quarter_final', 'semi_final', 'final', 'third_place']);
    }

    public function scopeNeedsPosterSending($query, $daysBefore = 3)
    {
        return $query->where('poster_sent', false)
            ->where('is_cancelled', false)
            ->where('status', 'upcoming')
            ->whereDate('match_date', '<=', now()->addDays($daysBefore))
            ->whereDate('match_date', '>=', now());
    }

    // Helpers
    public function isUpcoming(): bool
    {
        return $this->status === 'upcoming' && !$this->is_cancelled;
    }

    public function isLive(): bool
    {
        return $this->status === 'live';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->is_cancelled;
    }

    public function isGroupStage(): bool
    {
        return $this->stage === 'group';
    }

    public function isKnockoutStage(): bool
    {
        return in_array($this->stage, ['quarter_final', 'semi_final', 'final', 'third_place']);
    }

    public function isFinal(): bool
    {
        return $this->stage === 'final';
    }

    public function isSemiFinal(): bool
    {
        return $this->stage === 'semi_final';
    }

    public function getPublicUrlAttribute(): string
    {
        return route('public.match.show', $this->slug);
    }

    public function getPosterImageUrlAttribute(): ?string
    {
        return $this->poster_image ? asset('storage/' . $this->poster_image) : null;
    }

    public function getStageDisplayAttribute(): string
    {
        return match ($this->stage) {
            'group' => 'Group Stage',
            'league' => 'League',
            'quarter_final' => 'Quarter Final',
            'semi_final' => 'Semi Final',
            'final' => 'Final',
            'third_place' => '3rd Place Playoff',
            default => ucfirst($this->stage),
        };
    }

    public function getMatchTitleAttribute(): string
    {
        $teamA = $this->teamA?->name ?? 'TBD';
        $teamB = $this->teamB?->name ?? 'TBD';
        return "{$teamA} vs {$teamB}";
    }

    public function markPosterSent(): void
    {
        $this->update([
            'poster_sent' => true,
            'poster_sent_at' => now(),
        ]);
    }

    public function cancel(?string $reason = null): void
    {
        $this->update([
            'is_cancelled' => true,
            'cancellation_reason' => $reason,
        ]);

        // Release time slot if assigned
        if ($this->timeSlot) {
            $this->timeSlot->releaseMatch();
        }
    }

    /**
     * Get or create summary for this match
     */
    public function getOrCreateSummary(): MatchSummary
    {
        return MatchSummary::getOrCreate($this);
    }

    /**
     * Check if this is a knockout stage match (semi-final or final)
     */
    public function isHighStakes(): bool
    {
        return in_array($this->stage, ['semi_final', 'final', 'third_place']);
    }

    /**
     * Get all players from both teams
     */
    public function getAllPlayers()
    {
        $players = collect();

        if ($this->teamA) {
            $players = $players->merge($this->teamA->players);
        }

        if ($this->teamB) {
            $players = $players->merge($this->teamB->players);
        }

        return $players;
    }

    /**
     * Get all emails from both teams
     */
    public function getAllTeamEmails(): array
    {
        $emails = [];

        foreach ([$this->teamA, $this->teamB] as $team) {
            if (!$team) continue;

            foreach ($team->users as $user) {
                if ($user->email) {
                    $emails[] = $user->email;
                }
            }
        }

        return array_unique($emails);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Tournament extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'start_date',
        'organization_id',
        'end_date',
        'location',
        'status',
        'champion_team_id',
        'runner_up_team_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tournament) {
            if (empty($tournament->slug)) {
                $tournament->slug = Str::slug($tournament->name) . '-' . Str::random(6);
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function settings(): HasOne
    {
        return $this->hasOne(TournamentSetting::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(TournamentGroup::class)->orderBy('order');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(TournamentRegistration::class);
    }

    public function awards(): HasMany
    {
        return $this->hasMany(TournamentAward::class)->orderBy('order');
    }

    public function pointTableEntries(): HasMany
    {
        return $this->hasMany(PointTableEntry::class);
    }

    public function playerStatistics(): HasMany
    {
        return $this->hasMany(PlayerStatistic::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(Matches::class);
    }

    public function actualTeams(): HasMany
    {
        return $this->hasMany(ActualTeam::class);
    }

    public function champion(): BelongsTo
    {
        return $this->belongsTo(ActualTeam::class, 'champion_team_id');
    }

    public function runnerUp(): BelongsTo
    {
        return $this->belongsTo(ActualTeam::class, 'runner_up_team_id');
    }

    // Scopes
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeRegistration($query)
    {
        return $query->where('status', 'registration');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeForOrganization($query, $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    // Helpers
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isInRegistration(): bool
    {
        return $this->status === 'registration';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function getPublicUrlAttribute(): string
    {
        return route('public.tournament.show', $this->slug);
    }

    public function getPlayerRegistrationUrlAttribute(): string
    {
        return route('public.tournament.register.player', $this->slug);
    }

    public function getTeamRegistrationUrlAttribute(): string
    {
        return route('public.tournament.register.team', $this->slug);
    }

    public function getSettingsOrCreateAttribute(): TournamentSetting
    {
        return $this->settings ?? $this->settings()->create([]);
    }

    public function getPendingRegistrationsCountAttribute(): int
    {
        return $this->registrations()->pending()->count();
    }

    public function getApprovedTeamsCountAttribute(): int
    {
        return $this->registrations()->teams()->approved()->count();
    }

    public function getApprovedPlayersCountAttribute(): int
    {
        return $this->registrations()->players()->approved()->count();
    }
}

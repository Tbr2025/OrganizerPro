<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentRegistration extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'type',
        // Player registration
        'player_id',
        // Team registration
        'team_name',
        'team_short_name',
        'team_logo',
        'captain_name',
        'captain_email',
        'captain_phone',
        'vice_captain_name',
        'vice_captain_phone',
        'team_description',
        // Common
        'status',
        'remarks',
        'processed_at',
        'processed_by',
        'actual_team_id',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function actualTeam(): BelongsTo
    {
        return $this->belongsTo(ActualTeam::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopePlayers($query)
    {
        return $query->where('type', 'player');
    }

    public function scopeTeams($query)
    {
        return $query->where('type', 'team');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isPlayerRegistration(): bool
    {
        return $this->type === 'player';
    }

    public function isTeamRegistration(): bool
    {
        return $this->type === 'team';
    }

    public function getTeamLogoUrlAttribute(): ?string
    {
        return $this->team_logo ? asset('storage/' . $this->team_logo) : null;
    }
}

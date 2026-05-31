<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MatchAward extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        'tournament_award_id',
        'player_id',
        'custom_player_name',
        'custom_player_image',
        'remarks',
        'poster_image',
        'poster_sent',
        'poster_sent_at',
    ];

    protected $casts = [
        'poster_sent' => 'boolean',
        'poster_sent_at' => 'datetime',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(Matches::class, 'match_id');
    }

    public function tournamentAward(): BelongsTo
    {
        return $this->belongsTo(TournamentAward::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->player?->name ?? $this->custom_player_name ?? 'Unknown';
    }

    public function getDisplayImageAttribute(): ?string
    {
        return $this->player?->image_path ?? $this->custom_player_image;
    }

    public function getCustomPlayerImageUrlAttribute(): ?string
    {
        return $this->custom_player_image ? asset('storage/' . $this->custom_player_image) : null;
    }

    public function getPosterImageUrlAttribute(): ?string
    {
        return $this->poster_image ? asset('storage/' . $this->poster_image) : null;
    }

    public function markPosterSent(): void
    {
        $this->update([
            'poster_sent' => true,
            'poster_sent_at' => now(),
        ]);
    }
}

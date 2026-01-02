<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchAward extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        'tournament_award_id',
        'player_id',
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

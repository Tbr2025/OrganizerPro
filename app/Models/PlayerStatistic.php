<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerStatistic extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'player_id',
        'actual_team_id',
        // Batting
        'matches',
        'innings_batted',
        'runs',
        'balls_faced',
        'fours',
        'sixes',
        'highest_score',
        'highest_not_out',
        'fifties',
        'hundreds',
        'not_outs',
        'ducks',
        // Bowling
        'innings_bowled',
        'overs_bowled',
        'runs_conceded',
        'wickets',
        'maidens',
        'best_bowling',
        'four_wickets',
        'five_wickets',
        'wides',
        'no_balls',
        // Fielding
        'catches',
        'stumpings',
        'run_outs',
    ];

    protected $casts = [
        'overs_bowled' => 'decimal:1',
        'highest_not_out' => 'boolean',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(ActualTeam::class, 'actual_team_id');
    }

    // Batting Calculations
    public function getBattingAverageAttribute(): ?float
    {
        $dismissals = $this->innings_batted - $this->not_outs;
        if ($dismissals <= 0) {
            return $this->runs > 0 ? null : 0; // null indicates not out
        }
        return round($this->runs / $dismissals, 2);
    }

    public function getStrikeRateAttribute(): float
    {
        if ($this->balls_faced <= 0) {
            return 0;
        }
        return round(($this->runs / $this->balls_faced) * 100, 2);
    }

    public function getHighestScoreDisplayAttribute(): string
    {
        return $this->highest_score . ($this->highest_not_out ? '*' : '');
    }

    // Bowling Calculations
    public function getBowlingAverageAttribute(): ?float
    {
        if ($this->wickets <= 0) {
            return null;
        }
        return round($this->runs_conceded / $this->wickets, 2);
    }

    public function getEconomyRateAttribute(): ?float
    {
        if ($this->overs_bowled <= 0) {
            return null;
        }
        return round($this->runs_conceded / $this->overs_bowled, 2);
    }

    public function getBowlingStrikeRateAttribute(): ?float
    {
        if ($this->wickets <= 0) {
            return null;
        }
        $balls = $this->overs_bowled * 6;
        return round($balls / $this->wickets, 2);
    }

    // Scopes
    public function scopeTopRunScorers($query, $limit = 10)
    {
        return $query->orderByDesc('runs')->limit($limit);
    }

    public function scopeTopWicketTakers($query, $limit = 10)
    {
        return $query->orderByDesc('wickets')->limit($limit);
    }

    public function scopeTopSixHitters($query, $limit = 10)
    {
        return $query->orderByDesc('sixes')->limit($limit);
    }

    public function scopeForTournament($query, $tournamentId)
    {
        return $query->where('tournament_id', $tournamentId);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointTableEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'tournament_group_id',
        'actual_team_id',
        'matches_played',
        'won',
        'lost',
        'tied',
        'no_result',
        'points',
        'runs_scored',
        'overs_faced',
        'runs_conceded',
        'overs_bowled',
        'net_run_rate',
        'position',
        'qualified',
    ];

    protected $casts = [
        'overs_faced' => 'decimal:1',
        'overs_bowled' => 'decimal:1',
        'net_run_rate' => 'decimal:3',
        'qualified' => 'boolean',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TournamentGroup::class, 'tournament_group_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(ActualTeam::class, 'actual_team_id');
    }

    public function calculateNRR(): float
    {
        $runRateFor = $this->overs_faced > 0 ? $this->runs_scored / $this->overs_faced : 0;
        $runRateAgainst = $this->overs_bowled > 0 ? $this->runs_conceded / $this->overs_bowled : 0;

        return round($runRateFor - $runRateAgainst, 3);
    }

    public function updateNRR(): void
    {
        $this->net_run_rate = $this->calculateNRR();
        $this->save();
    }

    public function getNrrDisplayAttribute(): string
    {
        $nrr = $this->net_run_rate;
        $sign = $nrr >= 0 ? '+' : '';
        return $sign . number_format($nrr, 3);
    }

    public function scopeForGroup($query, $groupId)
    {
        return $query->where('tournament_group_id', $groupId);
    }

    public function scopeRanked($query)
    {
        return $query->orderByDesc('points')
            ->orderByDesc('net_run_rate')
            ->orderByDesc('won');
    }
}

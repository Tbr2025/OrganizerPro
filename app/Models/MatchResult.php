<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        // Team A
        'team_a_score',
        'team_a_wickets',
        'team_a_overs',
        'team_a_extras',
        // Team B
        'team_b_score',
        'team_b_wickets',
        'team_b_overs',
        'team_b_extras',
        // Result
        'result_summary',
        'winner_team_id',
        'result_type',
        'margin',
        // Toss
        'toss_won_by',
        'toss_decision',
        'team_a_batting_first',
        // Summary
        'match_notes',
        'summary_image',
        'summary_sent',
        'summary_sent_at',
    ];

    protected $casts = [
        'team_a_overs' => 'decimal:1',
        'team_b_overs' => 'decimal:1',
        'team_a_batting_first' => 'boolean',
        'summary_sent' => 'boolean',
        'summary_sent_at' => 'datetime',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(Matches::class, 'match_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(ActualTeam::class, 'winner_team_id');
    }

    public function tossWinner(): BelongsTo
    {
        return $this->belongsTo(ActualTeam::class, 'toss_won_by');
    }

    public function getTeamAScoreDisplayAttribute(): string
    {
        return "{$this->team_a_score}/{$this->team_a_wickets} ({$this->team_a_overs})";
    }

    public function getTeamBScoreDisplayAttribute(): string
    {
        return "{$this->team_b_score}/{$this->team_b_wickets} ({$this->team_b_overs})";
    }

    public function getSummaryImageUrlAttribute(): ?string
    {
        return $this->summary_image ? asset('storage/' . $this->summary_image) : null;
    }

    public function generateResultSummary(): string
    {
        $match = $this->match;

        if ($this->result_type === 'tie') {
            return 'Match Tied';
        }

        if ($this->result_type === 'no_result') {
            return 'No Result';
        }

        if (!$this->winner_team_id) {
            return 'Result pending';
        }

        $winnerName = $this->winner->name;
        $margin = $this->margin;

        return match ($this->result_type) {
            'runs' => "{$winnerName} won by {$margin} runs",
            'wickets' => "{$winnerName} won by {$margin} wickets",
            'super_over' => "{$winnerName} won via Super Over",
            'dls' => "{$winnerName} won by {$margin} runs (DLS)",
            default => "{$winnerName} won",
        };
    }

    /**
     * Get first batting team's score display
     */
    public function getFirstBattingScoreDisplayAttribute(): string
    {
        if ($this->team_a_batting_first === false) {
            return $this->team_b_score_display;
        }
        return $this->team_a_score_display;
    }

    /**
     * Get second batting team's score display
     */
    public function getSecondBattingScoreDisplayAttribute(): string
    {
        if ($this->team_a_batting_first === false) {
            return $this->team_a_score_display;
        }
        return $this->team_b_score_display;
    }

    /**
     * Derive team_a_batting_first from toss data
     */
    public static function deriveTeamABattingFirst(?int $tossWonBy, ?string $tossDecision, int $teamAId): ?bool
    {
        if (!$tossWonBy || !$tossDecision) {
            return null;
        }

        if ($tossDecision === 'bat') {
            return $tossWonBy == $teamAId;
        }
        // Toss winner chose to bowl, so the other team bats first
        return $tossWonBy != $teamAId;
    }

    public function markSummarySent(): void
    {
        $this->update([
            'summary_sent' => true,
            'summary_sent_at' => now(),
        ]);
    }
}

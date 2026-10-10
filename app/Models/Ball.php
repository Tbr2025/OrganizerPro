<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One delivery. batsman_id / non_striker_id / bowler_id / fielder_id /
 * out_batsman_id all point at actual_team_users.id (a squad row), not
 * players.id — see 2025_08_28_150600_alter_balls_table_foreign_keys.
 *
 * Conventions (MatchStateService reads them):
 *  - runs        runs off the bat.
 *  - extra_runs  the whole extra for the delivery: a plain wide is 1, a wide
 *                that runs two more is 3; byes/leg-byes are the runs taken.
 *  - innings     1 or 2, stamped from the match's current_innings when scored.
 */
class Ball extends Model
{
    protected $fillable = [
        'event_uuid',
        'match_id',
        'innings',
        'bowler_id',
        'batsman_id',
        'non_striker_id',
        'over',
        'ball_in_over',
        'runs',
        'extra_type',
        'extra_runs',
        'is_wicket',
        'dismissal_type',
        'out_batsman_id',
        'fielder_id',
    ];

    protected $casts = [
        'innings' => 'integer',
        'over' => 'integer',
        'ball_in_over' => 'integer',
        'runs' => 'integer',
        'extra_runs' => 'integer',
        'is_wicket' => 'boolean',
    ];

    public function match()
    {
        return $this->belongsTo(Matches::class);
    }

    public function batsman()
    {
        return $this->belongsTo(ActualTeamUser::class, 'batsman_id');
    }

    public function nonStriker()
    {
        return $this->belongsTo(ActualTeamUser::class, 'non_striker_id');
    }

    public function bowler()
    {
        return $this->belongsTo(ActualTeamUser::class, 'bowler_id');
    }

    public function fielder()
    {
        return $this->belongsTo(ActualTeamUser::class, 'fielder_id');
    }

    public function outBatsman()
    {
        return $this->belongsTo(ActualTeamUser::class, 'out_batsman_id');
    }

    /** Wides and no-balls are re-bowled and do not count toward the over. */
    public function isLegal(): bool
    {
        return ! in_array($this->extra_type, ['wide', 'no_ball'], true);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Matches extends Model
{
    protected $fillable = [
        'tournament_id',
        'name',
        'overs',
        'team_a_id',
        'team_b_id',
        'match_date',
        'venue',
        'status',
        'start_time',
        'end_time',
        'winner_team_id'
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function teamA()
    {
        return $this->belongsTo(ActualTeam::class, 'team_a_id');
    }

    public function teamB()
    {
        return $this->belongsTo(ActualTeam::class, 'team_b_id');
    }

    public function winner()
    {
        return $this->belongsTo(Team::class, 'winner_team_id');
    }


    public function appreciations()
    {
        return $this->hasMany(MatchAppreciation::class, 'match_id');
    }

    public function playersWithAppreciation()
    {
        return $this->players()->with('appreciation');
    }
    public function balls()
    {
        return $this->hasMany(Ball::class);
    }
}

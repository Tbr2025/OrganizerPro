<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ball extends Model
{
    protected $fillable = [
        'match_id',
        'bowler_id',
        'batsman_id',
        'over',
        'ball_in_over',
        'runs',
        'extra_type',
        'extra_runs',
        'is_wicket',
        'dismissal_type',
        'fielder_id',
    ];

    public function match() {
        return $this->belongsTo(Matches::class);
    }

    public function batsman() {
        return $this->belongsTo(Player::class, 'batsman_id');
    }

    public function bowler() {
        return $this->belongsTo(Player::class, 'bowler_id');
    }

    public function fielder() {
        return $this->belongsTo(Player::class, 'fielder_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ball extends Model
{

    protected $primaryKey = 'id';  // not 'id'
    public $incrementing = false;        // or false if UUID
    protected $keyType = 'int';         // or 'string' if UUID

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

    public function match()
    {
        return $this->belongsTo(Matches::class);
    }
    public function batsman()
    {
        // The foreign key in the 'balls' table is 'batsman_id'
        // It refers to the 'id' of the ActualTeamUser model
        return $this->belongsTo(ActualTeamUser::class, 'user_id');
    }

    public function bowler()
    {
        // The foreign key in the 'balls' table is 'bowler_id'
        // It refers to the 'id' of the ActualTeamUser model
        return $this->belongsTo(ActualTeamUser::class, 'user_id');
    }
    public function fielder()
    {
        return $this->belongsTo(ActualTeamUser::class, 'fielder_id');
    }
}

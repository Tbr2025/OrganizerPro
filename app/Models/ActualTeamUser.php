<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActualTeamUser extends Model
{
    protected $table = 'actual_team_users'; // Make sure this matches exactly

    protected $fillable = [
        'id',
        'actual_team_id',
        'user_id',
        'role',
    ];
public function player()
    {
        return $this->belongsTo(Player::class, 'user_id', 'user_id');
        // local user_id -> players.user_id
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function team()
    {
        return $this->belongsTo(ActualTeamUser::class, 'actual_team_id');
    }

    public function balls()
    {
        return $this->hasMany(Ball::class, 'bowler_id');
    }

    
}

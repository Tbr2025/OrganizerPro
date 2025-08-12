<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActualTeam extends Model
{
    protected $fillable = [
        'organization_id',
        'tournament_id',
        'name',
        'role'
    ];


    public function player()
    {
        return $this->belongsTo(Player::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function players()
    {
        return $this->belongsToMany(Player::class, 'player_team_tournament', 'team_id', 'player_id')
            ->withPivot('role');
    }

    public function users()
    {
        // This tells Eloquent:
        // 1. It's a many-to-many relationship with User.
        // 2. The pivot table is named 'actual_team_users'.
        // 3. CRUCIAL: Also load the 'role' column from that pivot table.
        return $this->belongsToMany(User::class, 'actual_team_users')->withPivot('role')->withTimestamps();
    }
}

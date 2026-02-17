<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function locations()
    {
        return $this->hasMany(PlayerLocation::class);
    }
    public function tournaments()
    {
        return $this->hasMany(Tournament::class);
    }

    public function zones()
    {
        return $this->hasMany(Zone::class);
    }

    public function actualTeams()
    {
        // This line tells Laravel:
        // "An Organization can have many ActualTeam models,
        // and you can find them by looking for the 'organization_id' on the 'actual_teams' table."
        return $this->hasMany(ActualTeam::class, 'organization_id');
    }
}

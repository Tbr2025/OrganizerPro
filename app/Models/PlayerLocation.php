<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayerLocation extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    // Optional: relationship to players
    public function players()
    {
        return $this->hasMany(Player::class, 'location_id');
    }
}

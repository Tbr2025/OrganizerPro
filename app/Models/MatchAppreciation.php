<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatchAppreciation extends Model
{
    use HasFactory;

    protected $fillable = ['match_id', 'player_id', 'title', 'remarks'];

    public function match()
    {
        return $this->belongsTo(Matches::class);
    }

    public function player()
    {
        return $this->belongsTo(Player::class);
    }
}

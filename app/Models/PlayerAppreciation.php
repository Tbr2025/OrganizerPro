<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerAppreciation extends Model
{
    protected $fillable = [
        'tournament_id',
        'match_id',
        'player_id',
        'title_line1',
        'title_line2',
        'font_family',
        'angle',
        'overlay_name',
        'image_path',
        'appreciation_type',
    ];

    public function player()
    {
        return $this->belongsTo(Player::class);
    }

    public function match()
    {
        return $this->belongsTo(Matches::class);
    }

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }
}


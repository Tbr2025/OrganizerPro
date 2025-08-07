<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Team extends Model
{
    protected $fillable = [
        'name',
        'short_name',
        'tournament_id',
        'logo',
        'admin_id',
        'created_by'
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    // public function players()
    // {
    //     return $this->belongsToMany(Player::class, 'player_team_tournament')
    //         ->withPivot('tournament_id')
    //         ->withTimestamps();
    // }

    public function players()
    {
        return $this->belongsToMany(Player::class, 'player_team_tournament')
            ->withPivot(['tournament_id', 'role', 'image_path'])
            ->withTimestamps();
    }
}

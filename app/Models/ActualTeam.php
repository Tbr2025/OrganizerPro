<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ActualTeam extends Model
{
    protected $fillable = [
        'organization_id',
        'tournament_id',
        'name',
        'team_logo',

    ];

    public function scopeApplyFilters(Builder $query, array $filters): Builder
    {
        // Apply Organization filter if provided
        if (!empty($filters['organization_id'])) {
            $query->where('organization_id', $filters['organization_id']);
        }

        // Apply Tournament filter if provided
        if (!empty($filters['tournament_id'])) {
            $query->where('tournament_id', $filters['tournament_id']);
        }

        return $query;
    }
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
        return $this->hasMany(ActualTeamUser::class, 'actual_team_id')
            ->with('player'); // eager load Player details
    }


    public function users()
    {
        // This tells Eloquent:
        // 1. It's a many-to-many relationship with User.
        // 2. The pivot table is named 'actual_team_users'.
        // 3. CRUCIAL: Also load the 'role' column from that pivot table.
        return $this->belongsToMany(User::class, 'actual_team_users')->withPivot('role')->withTimestamps();
    }

    public function auction()
    {
        return $this->belongsTo(Auction::class, 'auction_id');
    }

    /**
     * Get auction players won by this team (sold_to_team_id)
     */
    public function auctionPlayers()
    {
        return $this->hasMany(AuctionPlayer::class, 'sold_to_team_id');
    }


    public function members()
    {
        return $this->belongsToMany(User::class, 'actual_team_users')
            ->withPivot('role')
            ->withTimestamps();
    }
}

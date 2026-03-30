<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ActualTeam extends Model
{
    protected $fillable = [
        'organization_id',
        'tournament_id',
        'name',
        'short_name',
        'location',
        'team_logo',
        'primary_color',
        'secondary_color',
        'sponsor_logo',
        'captain_image',
        'invite_code',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($team) {
            if (empty($team->invite_code)) {
                $team->invite_code = Str::random(12);
            }
        });
    }

    public function getInviteLinkAttribute(): string
    {
        $link = url('/join/' . $this->invite_code);

        // Ensure HTTPS in production
        if (app()->environment('production')) {
            $link = str_replace('http://', 'https://', $link);
        }

        return $link;
    }

    /**
     * Get team logo URL
     */
    public function getTeamLogoUrlAttribute(): ?string
    {
        return $this->team_logo ? asset('storage/' . $this->team_logo) : null;
    }

    /**
     * Get sponsor logo URL
     */
    public function getSponsorLogoUrlAttribute(): ?string
    {
        return $this->sponsor_logo ? asset('storage/' . $this->sponsor_logo) : null;
    }

    /**
     * Get captain image URL
     */
    public function getCaptainImageUrlAttribute(): ?string
    {
        return $this->captain_image ? asset('storage/' . $this->captain_image) : null;
    }

    /**
     * Get display name (short_name or first word of name)
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->short_name ?? $this->name;
    }

    /**
     * Get team captain (first user with captain role)
     */
    public function getCaptainAttribute()
    {
        return $this->users()->wherePivot('role', 'captain')->first();
    }

    /**
     * Get team owner (first user with Owner role)
     */
    public function getOwnerAttribute()
    {
        return $this->users()->wherePivot('role', 'Owner')->first();
    }

    /**
     * Get team manager (first user with Manager role)
     */
    public function getManagerAttribute()
    {
        return $this->users()->wherePivot('role', 'Manager')->first();
    }

    public function scopeApplyFilters(Builder $query, array $filters): Builder
    {
        // Apply Organization filter if provided
        if (!empty($filters['organization_id'])) {
            $query->where('organization_id', $filters['organization_id']);
        }

        // Apply Tournament filter — check both primary tournament_id and pivot table
        if (!empty($filters['tournament_id'])) {
            $tournamentId = $filters['tournament_id'];
            $query->where(function ($q) use ($tournamentId) {
                $q->where('tournament_id', $tournamentId)
                  ->orWhereHas('tournaments', function ($sub) use ($tournamentId) {
                      $sub->where('tournaments.id', $tournamentId);
                  });
            });
        }

        // Apply name search if provided
        if (!empty($filters['search'])) {
            $query->where('name', 'like', '%' . $filters['search'] . '%');
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

    /**
     * Many-to-many: all tournaments this team participates in
     */
    public function tournaments()
    {
        return $this->belongsToMany(Tournament::class, 'actual_team_tournament')
            ->withTimestamps();
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

    /**
     * Get the tournament groups this team belongs to
     */
    public function groups()
    {
        return $this->belongsToMany(TournamentGroup::class, 'tournament_group_teams')
            ->withPivot('order')
            ->withTimestamps();
    }
}

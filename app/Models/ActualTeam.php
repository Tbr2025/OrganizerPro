<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Traits\BelongsToOrganization;

class ActualTeam extends Model
{
    use BelongsToOrganization;

    /** Organizers explicitly assigned to this team. */
    public function organizers()
    {
        return $this->morphToMany(User::class, 'assignable', 'organizer_assignments')->withTimestamps();
    }

    /**
     * Restrict to teams a user may see: Superadmin all; Organizer (not Admin) →
     * teams they're assigned to OR teams in tournaments they're assigned to;
     * others → own organization.
     */
    public function scopeForUser($query, $user)
    {
        if (! $user || (method_exists($user, 'hasRole') && $user->hasRole('Superadmin'))) {
            return $query;
        }
        if (method_exists($user, 'hasRole') && $user->hasRole('Organizer') && ! $user->hasRole('Admin')) {
            $tournamentIds = \App\Models\Tournament::forUser($user)->pluck('id');
            return $query->where(function ($q) use ($user, $tournamentIds) {
                $q->whereHas('organizers', fn ($o) => $o->where('users.id', $user->id))
                  ->orWhereIn('tournament_id', $tournamentIds);
            });
        }
        return $query->where('organization_id', $user->organization_id);
    }

    protected $fillable = [
        'organization_id',
        'tournament_id',
        'is_global',
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

    protected $casts = [
        'is_global' => 'boolean',
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

    /**
     * Scope: find teams belonging to a tournament (checks both legacy column and pivot table)
     */
    public function scopeForTournament(Builder $query, $tournamentId): Builder
    {
        return $query->where(function ($q) use ($tournamentId) {
            $q->where('tournament_id', $tournamentId)
              ->orWhereHas('tournaments', function ($sub) use ($tournamentId) {
                  $sub->where('tournaments.id', $tournamentId);
              });
        });
    }

    /**
     * Scope: teams the tournament has actually let in.
     *
     * An ActualTeam row is not proof of approval — on the live tournament seven teams exist
     * while only five registrations were approved. Anywhere that means "the teams in this
     * tournament" has to go through the registration, or pending sides end up in group
     * draws, on the broadcast ticker and in sealed bidding rounds.
     *
     * A team with NO registration at all is KEPT: those are created directly by an organizer
     * and were never part of the approval flow, so filtering on the absence of a row would
     * hide legitimate teams. Only a team whose registration exists and has not been approved
     * is withheld.
     */
    public function scopeApprovedForTournament(Builder $query, $tournamentId): Builder
    {
        return $query->where(function ($outer) use ($tournamentId) {
            $outer->whereHas('tournamentRegistrations', function ($r) use ($tournamentId) {
                $r->where('tournament_id', $tournamentId)
                    ->where('type', 'team')
                    ->where('status', 'approved');
            })->orWhereDoesntHave('tournamentRegistrations', function ($r) use ($tournamentId) {
                $r->where('tournament_id', $tournamentId)->where('type', 'team');
            });
        });
    }

    public function scopeApplyFilters(Builder $query, array $filters): Builder
    {
        // Apply Organization filter if provided
        if (! empty($filters['organization_id'])) {
            $query->where('organization_id', $filters['organization_id']);
        }

        // Apply Tournament filter — check primary tournament_id, pivot table, OR global teams in same org
        if (! empty($filters['tournament_id'])) {
            $tournamentId = $filters['tournament_id'];
            $query->where(function ($q) use ($tournamentId) {
                $q->where('tournament_id', $tournamentId)
                  ->orWhereHas('tournaments', function ($sub) use ($tournamentId) {
                      $sub->where('tournaments.id', $tournamentId);
                  })
                  ->orWhere(function ($sub) use ($tournamentId) {
                      // Global teams: match by organization of the filtered tournament
                      $tournament = \App\Models\Tournament::find($tournamentId);
                      if ($tournament) {
                          $sub->where('is_global', true)
                              ->where('organization_id', $tournament->organization_id);
                      }
                  });
            });
        }

        // Apply name search if provided
        if (! empty($filters['search'])) {
            $query->where('name', 'like', '%' . $filters['search'] . '%');
        }

        return $query;
    }

    /**
     * Players assigned to this team via the per-tournament pivot
     */
    public function playersPerTournament()
    {
        return $this->belongsToMany(Player::class, 'player_actual_team_tournament')
            ->withPivot('tournament_id', 'role')
            ->withTimestamps();
    }

    /**
     * Players for a specific tournament
     */
    public function playersForTournament($tournamentId)
    {
        return $this->belongsToMany(Player::class, 'player_actual_team_tournament')
            ->withPivot('tournament_id', 'role')
            ->withTimestamps()
            ->wherePivot('tournament_id', $tournamentId);
    }

    /**
     * For global teams, returns all org tournaments; otherwise returns pivot tournaments
     */
    public function getEffectiveTournamentsAttribute()
    {
        if ($this->is_global) {
            return Tournament::where('organization_id', $this->organization_id)->get();
        }

        $tournaments = $this->tournaments;
        if ($tournaments->isEmpty() && $this->tournament_id) {
            return Tournament::where('id', $this->tournament_id)->get();
        }

        return $tournaments;
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
     * The public-registration rows that produced this team.
     *
     * An ActualTeam row is not proof of approval — a team can exist while its registration
     * is still pending — so anywhere that means "teams who are actually in the tournament"
     * has to go through these rather than assuming the team's existence is enough.
     */
    public function tournamentRegistrations()
    {
        return $this->hasMany(TournamentRegistration::class, 'actual_team_id');
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

    /**
     * The account an admin should be dropped into when they "log in as" this team.
     *
     * Not simply "a user on the team": logging in as a Player lands on the player dashboard, so
     * the only useful target is somebody who can actually reach the Team Manager dashboard.
     *
     * The list page previously picked by PIVOT role, matching 'Owner', 'Manager' or
     * 'Team Manager' exactly — which on live is true for 9 of 121 teams, because almost every
     * pivot row is 'Player' (446) or 'captain' (53). So the option was invisible on nearly every
     * team and looked like a missing feature.
     *
     * Order of preference: the account's real (Spatie) role first, since that is what decides
     * which dashboard they land on; then the legacy pivot roles, so teams set up the old way
     * still work.
     */
    public function loginAsUser(): ?User
    {
        $users = $this->relationLoaded('users') ? $this->users : $this->users()->get();

        if ($users->isEmpty()) {
            return null;
        }

        foreach (['Team Owner', 'Team Manager'] as $role) {
            $match = $users->first(fn ($u) => $u->hasRole($role));

            if ($match) {
                return $match;
            }
        }

        // Set up before the roles existed, or by hand.
        return $users->first(function ($u) {
            $pivotRole = strtolower((string) ($u->pivot->role ?? ''));

            return in_array($pivotRole, ['owner', 'manager', 'team manager'], true);
        });
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

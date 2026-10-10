<?php

namespace App\Http\Middleware;

use App\Models\ActualTeam;
use App\Models\Auction;
use App\Models\AuctionTemplate;
use App\Models\Matches;
use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-item authorization for Organizers.
 *
 * Superadmin/Admin are unaffected. For a user whose ONLY elevated role is
 * "Organizer" (or "Auction Organizer"), every route-bound Tournament / ActualTeam /
 * Matches / TournamentRegistration / Auction / Player, and any posted
 * `tournament_id`, is checked against their explicit
 * assignments (mirroring the model `forUser` scopes used in listings).
 * Any bound model the organizer isn't assigned to -> 403.
 */
class EnsureOrganizerCanAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Only constrain Organizers (and Auction Organizers). Admin/Superadmin bypass entirely.
        if (! $user
            || ! $user->hasAnyRole(['Organizer', 'Auction Organizer'])
            || $user->hasRole('Admin')
            || $user->hasRole('Superadmin')) {
            return $next($request);
        }

        // A tournament named in the request body or query (filters, create
        // forms) — not just a route-bound model — must be one of theirs too.
        $posted = $request->input('tournament_id');
        if (is_scalar($posted) && $posted !== '') {
            abort_unless($this->tournamentAllowed($user, $posted), 403);
        }

        foreach ($request->route()?->parameters() ?? [] as $param) {
            if (! is_object($param)) {
                continue;
            }

            if ($param instanceof Tournament) {
                abort_unless($this->tournamentAllowed($user, $param->getKey()), 403);
            } elseif ($param instanceof ActualTeam) {
                abort_unless(ActualTeam::forUser($user)->whereKey($param->getKey())->exists(), 403);
            } elseif ($param instanceof Team) {
                abort_unless($this->tournamentAllowed($user, $param->tournament_id), 403);
            } elseif ($param instanceof Matches) {
                abort_unless(Matches::forUser($user)->whereKey($param->getKey())->exists(), 403);
            } elseif ($param instanceof TournamentRegistration) {
                abort_unless($this->tournamentAllowed($user, $param->tournament_id), 403);
            } elseif ($param instanceof AuctionTemplate) {
                // A template bound to an auction is scoped by that auction's tournament.
                // A global template (auction_id null) is shared by every organization,
                // so a pure Organizer must not reach it at all.
                abort_unless(
                    $param->auction_id !== null
                        && $this->tournamentAllowed($user, Auction::withoutGlobalScopes()->whereKey($param->auction_id)->value('tournament_id')),
                    403
                );
            } elseif ($param instanceof Auction) {
                abort_unless($this->tournamentAllowed($user, $param->tournament_id), 403);
            } elseif ($param instanceof Player) {
                abort_unless($this->playerAllowed($user, $param->getKey()), 403);
            }
        }

        return $next($request);
    }

    /**
     * A player has no tournament of their own; they are in scope when they
     * registered for, or sit on a squad in, a tournament the organizer can see.
     */
    protected function playerAllowed($user, $playerId): bool
    {
        $tournamentIds = Tournament::forUser($user)->pluck('id');

        return DB::table('tournament_registrations')
            ->where('player_id', $playerId)->whereIn('tournament_id', $tournamentIds)->exists()
            || DB::table('player_actual_team_tournament')
                ->where('player_id', $playerId)->whereIn('tournament_id', $tournamentIds)->exists();
    }

    /** Is this tournament id within the organizer's assigned scope? */
    protected function tournamentAllowed($user, $tournamentId): bool
    {
        return $tournamentId !== null
            && Tournament::forUser($user)->whereKey($tournamentId)->exists();
    }
}

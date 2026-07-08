<?php

namespace App\Http\Middleware;

use App\Models\ActualTeam;
use App\Models\Auction;
use App\Models\Matches;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-item authorization for Organizers.
 *
 * Superadmin/Admin are unaffected. For a user whose ONLY elevated role is
 * "Organizer", every route-bound Tournament / ActualTeam / Matches /
 * TournamentRegistration / Auction is checked against their explicit
 * assignments (mirroring the model `forUser` scopes used in listings).
 * Any bound model the organizer isn't assigned to -> 403.
 */
class EnsureOrganizerCanAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Only constrain pure Organizers. Admin/Superadmin bypass entirely.
        if (! $user || ! $user->hasRole('Organizer') || $user->hasRole('Admin') || $user->hasRole('Superadmin')) {
            return $next($request);
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
            } elseif ($param instanceof Auction) {
                abort_unless($this->tournamentAllowed($user, $param->tournament_id), 403);
            }
        }

        return $next($request);
    }

    /** Is this tournament id within the organizer's assigned scope? */
    protected function tournamentAllowed($user, $tournamentId): bool
    {
        return $tournamentId !== null
            && Tournament::forUser($user)->whereKey($tournamentId)->exists();
    }
}

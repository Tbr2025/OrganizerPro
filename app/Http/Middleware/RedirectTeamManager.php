<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RedirectTeamManager
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->hasAnyRole(['Team Manager', 'Team Owner']) && !$user->hasAnyRole(['Superadmin', 'Admin', 'Organizer'])) {
            /*
             * Everywhere a team manager is legitimately sent.
             *
             * This list has to match what their own menu offers them, and it had drifted: the
             * sidebar shows Matches — the role carries `match.view`, so the item renders — and
             * clicking it landed on /admin/matches, which was not listed here. The middleware
             * sent them back to their dashboard, so from the chair it is a link that does
             * nothing, on the menu they use most.
             *
             * Team-manager screens, their own team, the players they manage, their profile and
             * registration, the matches their menu offers, and the live bidding page — plus
             * switch-back, so an admin who has impersonated somebody is never trapped inside it.
             */
            $allowed = $request->is('admin/team-manager*')
                || $request->is('admin/actual-teams*')
                || $request->is('admin/players*')
                || $request->is('admin/matches*')
                || $request->is('admin/team/auction/*')
                || $request->is('profile*')
                || $request->is('profileplayers*')
                || $request->routeIs('admin.users.switch-back');

            if ($allowed) {
                return $next($request);
            }

            return redirect()->route('team-manager.dashboard');
        }

        return $next($request);
    }
}

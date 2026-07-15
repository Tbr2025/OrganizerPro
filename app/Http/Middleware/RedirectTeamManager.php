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
            // Allow access to team-manager routes, and always allow exiting an
            // impersonation session (switch-back) so an admin is never trapped.
            if ($request->is('admin/team-manager*') || $request->is('admin/actual-teams*') || $request->is('admin/players*') || $request->is('profile*') || $request->is('profileplayers*') || $request->routeIs('admin.users.switch-back')) {
                return $next($request);
            }

            return redirect()->route('team-manager.dashboard');
        }

        return $next($request);
    }
}

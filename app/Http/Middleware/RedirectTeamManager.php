<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RedirectTeamManager
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->hasRole('Team Manager') && !$user->hasAnyRole(['Super Admin', 'Admin', 'Organizer'])) {
            // Allow access to team-manager routes
            if ($request->is('admin/team-manager*')) {
                return $next($request);
            }

            return redirect()->route('team-manager.dashboard');
        }

        return $next($request);
    }
}

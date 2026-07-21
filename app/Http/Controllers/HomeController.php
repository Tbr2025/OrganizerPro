<?php

declare(strict_types=1);

namespace App\Http\Controllers;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function redirectAdmin()
    {
        return redirect()->route('admin.dashboard');
    }

    /**
     * Redirect authenticated users to their role-appropriate dashboard.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function index()
    {
        $user = auth()->user();

        // Team Manager / Team Owner (without higher roles) → team manager dashboard
        if ($user->hasAnyRole(['Team Manager', 'Team Owner'])
            && !$user->hasAnyRole(['Superadmin', 'Admin', 'Organizer'])) {
            return redirect()->route('team-manager.dashboard');
        }

        // Superadmin, Admin, Organizer → admin dashboard
        if ($user->hasAnyRole(['Superadmin', 'Admin', 'Organizer'])) {
            return redirect()->route('admin.dashboard');
        }

        // Player → player dashboard
        if ($user->hasRole('Player') || $user->player) {
            return redirect()->route('player-dashboard');
        }

        // Fallback: show the generic home view
        return view('home');
    }
}

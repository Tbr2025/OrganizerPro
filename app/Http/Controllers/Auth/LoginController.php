<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use App\Traits\ValidatesTurnstile;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    use AuthenticatesUsers;
    use ValidatesTurnstile;

    protected $redirectTo = RouteServiceProvider::HOME;

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    protected function validateLogin(Request $request)
    {
        $this->validateTurnstile($request);

        $request->validate([
            $this->username() => 'required|string',
            'password' => 'required|string',
        ]);
    }

    /**
     * Redirect users to their role-appropriate dashboard after login.
     */
    protected function authenticated(Request $request, $user)
    {
        // Team Manager / Team Owner (without higher roles)
        if ($user->hasAnyRole(['Team Manager', 'Team Owner'])
            && !$user->hasAnyRole(['Superadmin', 'Admin', 'Organizer'])) {
            return redirect()->intended(route('team-manager.dashboard'));
        }

        // Superadmin, Admin, Organizer
        if ($user->hasAnyRole(['Superadmin', 'Admin', 'Organizer'])) {
            return redirect()->intended(route('admin.dashboard'));
        }

        // Player
        if ($user->hasRole('Player')) {
            return redirect()->intended(route('profileplayers.edit'));
        }

        // Fallback
        return redirect()->intended(RouteServiceProvider::HOME);
    }
}

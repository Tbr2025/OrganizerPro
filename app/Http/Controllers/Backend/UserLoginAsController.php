<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class UserLoginAsController extends Controller
{
    public function loginAs(int $id): RedirectResponse
    {
        $this->checkAuthorization(auth()->user(), ['user.login_as']);

        $user = User::findOrFail($id);

        Session::put('original_user_id', auth()->id());
        Auth::login($user);

        session()->flash('success', __('You are now logged in as :name.', ['name' => $user->name]));

        // Send the impersonated user to a landing page their role can actually
        // access. The admin dashboard requires the "dashboard.view" permission,
        // so users without it (e.g. Players) would otherwise hit a 403.
        if ($user->hasRole('Team Manager') && ! $user->hasAnyRole(['Superadmin', 'Admin', 'Organizer'])) {
            return redirect()->route('team-manager.dashboard');
        }

        if ($user->can('dashboard.view')) {
            return redirect()->route('admin.dashboard');
        }

        return redirect()->route('home');
    }

    public function switchBack(): RedirectResponse
    {
        $originalUserId = session()->pull('original_user_id');
        if ($originalUserId) {
            Auth::loginUsingId($originalUserId);
            session()->flash('success', __('Switched back to the original user.'));
        }

        return redirect()->route('admin.dashboard');
    }
}

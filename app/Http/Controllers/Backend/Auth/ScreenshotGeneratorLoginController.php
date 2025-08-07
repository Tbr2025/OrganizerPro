<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backend\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\App;

class ScreenshotGeneratorLoginController extends Controller
{
    public function login($email): RedirectResponse
    {
        // Only allow this functionality in non-production environments
        if (App::environment('production')) {
            abort(404, 'This functionality is not available in production environment');
        }

        // Find the user by email.
        $user = User::where('email', $email)->firstOrFail();

        // Authenticate the user and redirect.
        Auth::login($user);
        return redirect(request()->target ? request()->target : '/admin');
    }
}

<?php

namespace App\Http\Controllers\Backend\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Providers\RouteServiceProvider;
use App\Services\DemoAppService;
use App\Traits\ValidatesTurnstile;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;
    use ValidatesTurnstile;

    public function __construct(private readonly DemoAppService $demoAppService)
    {
    }

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::ADMIN_DASHBOARD;

    /**
     * show login form for admin guard
     *
     * @return \Illuminate\Contracts\Support\Renderable|\Illuminate\Http\RedirectResponse
     */
    public function showLoginForm()
    {
        if (Auth::guard('web')->check()) {
            return $this->redirectByRole();
        }

        $this->demoAppService->maybeSetDemoLocaleToEnByDefault();

        $email = app()->environment('local') ? 'superadmin@sportzley.com' : '';
        $password = app()->environment('local') ? '12345678' : '';

        return view('backend.auth.login')->with(compact('email', 'password'));
    }

    /**
     * Login admin.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function login(LoginRequest $request)
    {
        $this->validateTurnstile($request);

        if (Auth::guard('web')->attempt(['email' => $request->email, 'password' => $request->password], $request->remember)) {
            $this->demoAppService->maybeSetDemoLocaleToEnByDefault();
            session()->flash('success', 'Successfully Logged in!');

            return $this->redirectByRole();
        }

        if (Auth::guard('web')->attempt(['username' => $request->email, 'password' => $request->password], $request->remember)) {
            $this->demoAppService->maybeSetDemoLocaleToEnByDefault();
            session()->flash('success', 'Successfully Logged in!');

            return $this->redirectByRole();
        }

        session()->flash('error', __('auth.failed'));

        return back();
    }

    protected function redirectByRole()
    {
        $user = Auth::guard('web')->user();

        // Team Manager / Team Owner (without higher roles)
        if ($user->hasAnyRole(['Team Manager', 'Team Owner'])
            && !$user->hasAnyRole(['Superadmin', 'Admin', 'Organizer'])) {
            return redirect()->route('team-manager.dashboard');
        }

        // Superadmin, Admin, Organizer
        if ($user->hasAnyRole(['Superadmin', 'Admin', 'Organizer'])) {
            return redirect()->route('admin.dashboard');
        }

        // Player
        if ($user->hasRole('Player') || $user->player) {
            return redirect()->route('profileplayers.edit');
        }

        return redirect()->route('admin.dashboard');
    }

    /**
     * logout admin guard
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function logout()
    {
        Auth::guard('web')->logout();

        return redirect()->route('admin.login');
    }
}

<?php

namespace App\Providers;

use App\Models\Player;
use App\Policies\PlayerPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // 'App\Model' => 'App\Policies\ModelPolicy',
        Player::class => PlayerPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Superadmin passes every ability check. Routes and views are gated by
        // permission rather than role name, and a permission added in code
        // must not lock the Superadmin out on a database that hasn't had it
        // granted yet. Returning null (not false) leaves everyone else to the
        // normal permission check.
        Gate::before(fn ($user) => method_exists($user, 'hasRole') && $user->hasRole('Superadmin') ? true : null);
    }
}

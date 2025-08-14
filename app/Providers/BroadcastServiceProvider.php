<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register the broadcasting routes with proper middleware
        // If you are using Laravel Sanctum for SPA authentication, use 'auth:sanctum'
        // If you are using session-based auth, use ['web', 'auth']
        
        Broadcast::routes([
            'middleware' => ['web', 'auth'] // or ['auth:sanctum'] if SPA/Sanctum
        ]);

        // Load the channel authorization callbacks
        require base_path('routes/channels.php');
    }
}

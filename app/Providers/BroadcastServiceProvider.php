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
        // Remove 'auth' so guests can still access broadcasting/auth
        Broadcast::routes(['middleware' => ['web']]);

        require base_path('routes/channels.php');
    }
}

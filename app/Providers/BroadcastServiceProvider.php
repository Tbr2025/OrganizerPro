<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Remove default 'auth' middleware
        Broadcast::routes(['middleware' => ['web']]);

        require base_path('routes/channels.php');
    }
}

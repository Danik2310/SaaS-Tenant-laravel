<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

/**
 * Broadcasting bootstrap.
 *
 * Registers the broadcast auth routes and loads `routes/channels.php`.
 *
 * Note: this provider is currently **disabled** — it is commented out in
 * `config/app.php`, so channel authorization is not wired up.
 */
class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Broadcast::routes();

        require base_path('routes/channels.php');
    }
}

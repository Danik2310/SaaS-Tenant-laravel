<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/**
 * Broadcasting bootstrap.
 *
 * Registers the broadcast auth route with the same tenant-aware middleware
 * stack as the application routes, then loads `routes/channels.php`. Without
 * tenancy on this route the private notification channel cannot resolve the
 * tenant `User` that owns the JWT cookie.
 */
class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Broadcast::routes([
            'middleware' => [
                'web',
                InitializeTenancyByDomain::class,
                PreventAccessFromCentralDomains::class,
                'jwt.cookie',
                'session.expiry:web',
                'auth',
                'jwt.tenant:web',
                'cache.headers:no_store;private',
            ],
        ]);

        require base_path('routes/channels.php');
    }
}

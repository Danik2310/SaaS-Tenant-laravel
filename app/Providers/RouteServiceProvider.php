<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

/**
 * Route and rate-limiter registration.
 *
 * Flow:
 *  1. Alias Spatie's `permission` and `role` middleware.
 *  2. Define named rate limiters:
 *     - `api` — 60/min, keyed per tenant+user (or tenant+IP centrally);
 *     - `registration` — 5/hour per hashed email **and** per IP, so an attacker
 *       rotating IPs cannot hammer the email-conflict check for one address.
 *  3. Load the route files: `routes/api.php` under `api` + the `api/v1` prefix,
 *     and `routes/web.php` under `web`. Tenant routes are mapped separately by
 *     {@see TenancyServiceProvider::mapRoutes()}.
 */
class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        // Register Spatie Permission middleware
        $router = $this->app['router'];
        $router->aliasMiddleware('permission', PermissionMiddleware::class);
        $router->aliasMiddleware('role', RoleMiddleware::class);

        RateLimiter::for('api', function (Request $request) {
            $tenantId = tenant('id');
            $prefix = $tenantId ? 'tenant_'.$tenantId : 'central';
            $key = $request->user()
                ? $prefix.'_user_'.$request->user()->id
                : $prefix.'_ip_'.$request->ip();

            return Limit::perMinute(60)->by($key);
        });

        // Per-email cap on public registration, keyed by the submission email
        // so an attacker rotating IPs cannot hammer the email-conflict check
        // for one address. The key is hashed so raw emails never hit the cache
        // store. Composed with the per-IP `throttle:3,1` on the POST route.
        RateLimiter::for('registration', function (Request $request) {
            $email = strtolower((string) $request->input('email', ''));

            $byEmail = $email !== ''
                ? Limit::perMinutes(60, 5)->by('registration_'.hash('sha256', $email))
                : null;

            return collect([$byEmail, Limit::perMinutes(60, 5)->by('registration_ip_'.$request->ip())])
                ->filter()
                ->values()
                ->all();
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api/v1')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}

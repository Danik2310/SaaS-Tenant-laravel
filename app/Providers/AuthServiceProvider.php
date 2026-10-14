<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use App\Shared\Constants\PermissionNames;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

/**
 * Authorization wiring.
 *
 * Intentionally minimal: the application uses **no policies**. Access is
 * enforced by Spatie permission middleware on routes (`permission:…,admin`)
 * rather than Gate/policy classes, so `$policies` is left empty on purpose.
 *
 * @see PermissionNames
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}

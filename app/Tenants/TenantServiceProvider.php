<?php

declare(strict_types=1);

namespace App\Tenants;

use App\Tenants\Builders\TenantBuilder;
use App\Tenants\Console\Commands\CollectTenantMetricsCommand;
use App\Tenants\Console\Commands\ExpireTrialsCommand;
use App\Tenants\Contracts\DatabaseServiceInterface;
use App\Tenants\Contracts\TenantBuilderInterface;
use App\Tenants\Contracts\TenantManagerInterface;
use App\Tenants\Contracts\TenantRepositoryInterface;
use App\Tenants\Decorators\CachedTenantRepository;
use App\Tenants\Repositories\TenantRepository;
use App\Tenants\Services\DatabaseService;
use Illuminate\Support\ServiceProvider;

/**
 * Tenants module provider — container bindings for tenant lifecycle services.
 *
 * Flow (`register()`):
 *  1. {@see TenantManagerInterface} → {@see TenantManager} (singleton — the
 *     façade for provisioning and lifecycle operations).
 *  2. {@see TenantRepositoryInterface} → {@see CachedTenantRepository} wrapping
 *     {@see TenantRepository} (decorator, singleton).
 *  3. {@see TenantBuilderInterface} → {@see TenantBuilder} (bind, not singleton:
 *     the builder is stateful per provision call).
 *  4. {@see DatabaseServiceInterface} → {@see DatabaseService} (bind).
 *
 * `boot()` registers the module's console commands.
 */
class TenantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantManagerInterface::class, TenantManager::class);

        $this->app->singleton(TenantRepositoryInterface::class, function () {
            return new CachedTenantRepository(app(TenantRepository::class));
        });

        $this->app->bind(TenantBuilderInterface::class, TenantBuilder::class);
        $this->app->bind(DatabaseServiceInterface::class, DatabaseService::class);
    }

    public function boot(): void
    {
        $this->commands([
            CollectTenantMetricsCommand::class,
            ExpireTrialsCommand::class,
        ]);
    }
}

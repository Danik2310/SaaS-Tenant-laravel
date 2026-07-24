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

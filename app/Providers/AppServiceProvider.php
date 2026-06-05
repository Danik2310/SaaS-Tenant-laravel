<?php

namespace App\Providers;

use App\Contracts\ExportServiceInterface;
use App\Contracts\PermissionServiceInterface;
use App\Contracts\RoleServiceInterface;
use App\Contracts\TenantManagerInterface;
use App\Services\ExportService;
use App\Services\PermissionService;
use App\Services\RoleService;
use App\Services\TenantAwarePermissionRegistrar;
use App\Services\TenantManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\PermissionRegistrar;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantManagerInterface::class, TenantManager::class);
        $this->app->singleton(RoleServiceInterface::class, RoleService::class);
        $this->app->singleton(PermissionServiceInterface::class, PermissionService::class);
        $this->app->singleton(ExportServiceInterface::class, ExportService::class);
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        $this->app->forgetInstance(PermissionRegistrar::class);
        $this->app->singleton(PermissionRegistrar::class, TenantAwarePermissionRegistrar::class);
    }
}

<?php

namespace App\Providers;

use App\Adapters\StripePaymentAdapter;
use App\Builders\TenantBuilder;
use App\Contracts\ExportServiceInterface;
use App\Contracts\PaymentGatewayInterface;
use App\Contracts\PermissionServiceInterface;
use App\Contracts\RoleServiceInterface;
use App\Contracts\TenantBuilderInterface;
use App\Contracts\TenantManagerInterface;
use App\Contracts\TenantRepositoryInterface;
use App\Decorators\CachedTenantRepository;
use App\Repositories\TenantRepository;
use App\Services\ExportService;
use App\Services\PermissionService;
use App\Services\RoleService;
use App\Services\TenantAwarePermissionRegistrar;
use App\Services\TenantManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
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

        $this->app->singleton(TenantRepositoryInterface::class, function () {
            return new CachedTenantRepository(app(TenantRepository::class));
        });

        $this->app->bind(PaymentGatewayInterface::class, StripePaymentAdapter::class);

        $this->app->bind(TenantBuilderInterface::class, TenantBuilder::class);
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);

        Model::preventLazyLoading(! $this->app->isProduction());

        if ($this->app->isProduction()) {
            Model::handleLazyLoadingViolationUsing(function ($model, $relation) {
                \Log::warning("Lazy loading {$relation} on ".get_class($model));
            });
        }

        $this->app->forgetInstance(PermissionRegistrar::class);
        $this->app->singleton(PermissionRegistrar::class, TenantAwarePermissionRegistrar::class);
    }
}

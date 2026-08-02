<?php

declare(strict_types=1);

namespace App\Shared;

use App\Shared\Console\Commands\AssignStaffRole;
use App\Shared\Console\Commands\AssignSuperAdminRole;
use App\Shared\Console\Commands\CheckAdminPermissions;
use App\Shared\Console\Commands\CheckPermissionsData;
use App\Shared\Console\Commands\GenerateTenantReferenceIds;
use App\Shared\Contracts\ExchangeRateServiceInterface;
use App\Shared\Contracts\ExportServiceInterface;
use App\Shared\Contracts\PermissionServiceInterface;
use App\Shared\Contracts\RoleServiceInterface;
use App\Shared\Contracts\ServerDiskInfo;
use App\Shared\Services\CloudServerDiskInfo;
use App\Shared\Services\ExchangeRateService;
use App\Shared\Services\ExportService;
use App\Shared\Services\LocalServerDiskInfo;
use App\Shared\Services\PermissionService;
use App\Shared\Services\RoleService;
use App\Shared\Services\TenantAwarePermissionRegistrar;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\PermissionRegistrar;

class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RoleServiceInterface::class, RoleService::class);
        $this->app->singleton(PermissionServiceInterface::class, PermissionService::class);
        $this->app->singleton(ExportServiceInterface::class, ExportService::class);
        $this->app->singleton(ExchangeRateServiceInterface::class, ExchangeRateService::class);

        $this->app->singleton(ServerDiskInfo::class, function () {
            return env('SERVER_DISK_DRIVER', 'local') === 's3'
                ? new CloudServerDiskInfo
                : new LocalServerDiskInfo;
        });
    }

    public function boot(): void
    {
        $this->commands([
            AssignStaffRole::class,
            AssignSuperAdminRole::class,
            CheckAdminPermissions::class,
            CheckPermissionsData::class,
            GenerateTenantReferenceIds::class,
        ]);

        $this->app->forgetInstance(PermissionRegistrar::class);
        $this->app->singleton(PermissionRegistrar::class, TenantAwarePermissionRegistrar::class);
    }
}

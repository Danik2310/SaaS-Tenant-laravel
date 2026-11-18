<?php

declare(strict_types=1);

namespace App\Shared;

use Akaunting\Money\Currency as AkauntingCurrency;
use App\Shared\Console\Commands\AssignStaffRole;
use App\Shared\Console\Commands\AssignSuperAdminRole;
use App\Shared\Console\Commands\CheckAdminPermissions;
use App\Shared\Console\Commands\CheckPermissionsData;
use App\Shared\Console\Commands\GenerateTenantReferenceIds;
use App\Shared\Console\Commands\ScanNotificationTriggersCommand;
use App\Shared\Contracts\CurrencyCatalogInterface;
use App\Shared\Contracts\CurrencySettingsInterface;
use App\Shared\Contracts\ExchangeRateServiceInterface;
use App\Shared\Contracts\ExportServiceInterface;
use App\Shared\Contracts\LocationCatalog;
use App\Shared\Contracts\NotificationDispatcherInterface;
use App\Shared\Contracts\PermissionServiceInterface;
use App\Shared\Contracts\PlanPriceResolverInterface;
use App\Shared\Contracts\RoleServiceInterface;
use App\Shared\Contracts\ServerDiskInfo;
use App\Shared\Factories\ExchangeRateProviderFactory;
use App\Shared\Locations\DatabaseLocationCatalog;
use App\Shared\Notifications\NotificationDispatcher;
use App\Shared\Services\CloudServerDiskInfo;
use App\Shared\Services\CurrencyCatalog;
use App\Shared\Services\CurrencySettings;
use App\Shared\Services\ExchangeRateService;
use App\Shared\Services\ExportService;
use App\Shared\Services\LocalServerDiskInfo;
use App\Shared\Services\PermissionService;
use App\Shared\Services\PlanPriceResolver;
use App\Shared\Services\RoleService;
use App\Shared\Services\TenantAwarePermissionRegistrar;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\PermissionRegistrar;
use Swap\Swap;

/**
 * Shared module provider — container bindings and cross-cutting registration.
 *
 * Flow (`register()`):
 *  1. Bind the shared contracts to their implementations (Role, Permission,
 *     Export, ExchangeRate services, plus the location catalog).
 *  2. Bind {@see ServerDiskInfo} to a local or S3 driver selected from the
 *     `SERVER_DISK_DRIVER` env value.
 *
 * Flow (`boot()`):
 *  1. Register the shared console commands.
 *  2. Replace Spatie's PermissionRegistrar with
 *     {@see TenantAwarePermissionRegistrar} so the permission cache is scoped per
 *     tenant/central context.
 *
 * Interfaces live in `App\Shared\Contracts`; implementations in
 * `App\Shared\Services` / `App\Shared\Locations`.
 *
 * @see docs/architecture/05-permissions-and-plan-gating.md
 */
class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RoleServiceInterface::class, RoleService::class);
        $this->app->singleton(PermissionServiceInterface::class, PermissionService::class);
        $this->app->singleton(ExportServiceInterface::class, ExportService::class);
        $this->app->singleton(ExchangeRateServiceInterface::class, ExchangeRateService::class);
        $this->app->singleton(CurrencyCatalogInterface::class, CurrencyCatalog::class);
        $this->app->singleton(CurrencySettingsInterface::class, CurrencySettings::class);
        $this->app->singleton(PlanPriceResolverInterface::class, PlanPriceResolver::class);
        $this->app->singleton(LocationCatalog::class, DatabaseLocationCatalog::class);
        $this->app->singleton(NotificationDispatcherInterface::class, NotificationDispatcher::class);

        $this->app->singleton('swap', fn () => (new ExchangeRateProviderFactory)->make());
        $this->app->alias('swap', Swap::class);

        $this->app->singleton(ServerDiskInfo::class, function () {
            return config('filesystems.server_disk_driver', 'local') === 's3'
                ? new CloudServerDiskInfo
                : new LocalServerDiskInfo;
        });
    }

    public function boot(): void
    {
        $this->configureMoney();
        $this->commands([
            AssignStaffRole::class,
            AssignSuperAdminRole::class,
            CheckAdminPermissions::class,
            CheckPermissionsData::class,
            GenerateTenantReferenceIds::class,
            ScanNotificationTriggersCommand::class,
        ]);

        $this->app->forgetInstance(PermissionRegistrar::class);
        $this->app->singleton(PermissionRegistrar::class, TenantAwarePermissionRegistrar::class);
    }

    /**
     * Keep Akaunting's formatting metadata while applying the curated symbols
     * and en-US-style separators used across the existing interface.
     */
    private function configureMoney(): void
    {
        $currencies = AkauntingCurrency::getCurrencies();

        foreach ((array) config('currency.currencies', []) as $code => $currency) {
            if (! isset($currencies[$code]) || ! is_array($currency)) {
                continue;
            }

            $currencies[$code]['symbol'] = (string) ($currency['symbol'] ?? $currencies[$code]['symbol']);
            $currencies[$code]['symbol_first'] = true;
            $currencies[$code]['decimal_mark'] = '.';
            $currencies[$code]['thousands_separator'] = ',';
        }

        AkauntingCurrency::setCurrencies($currencies);
    }
}

<?php

declare(strict_types=1);

namespace App\Providers;

use App\Tenants\Events\TenantReactivated;
use App\Tenants\Events\TenantSuspended;
use App\Tenants\Listeners\HandleTenantReactivation;
use App\Tenants\Listeners\HandleTenantSuspension;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\PermissionRegistrar;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Jobs;
use Stancl\Tenancy\Listeners;
use Stancl\Tenancy\Middleware;

class TenancyServiceProvider extends ServiceProvider
{
    // By default, no namespace is used to support the callable array syntax.
    public static string $controllerNamespace = '';

    public function events()
    {
        return [
            // Tenant events — provisioning pipeline runs asynchronously
            Events\CreatingTenant::class => [],
            Events\TenantCreated::class => [
                JobPipeline::make([
                    Jobs\CreateDatabase::class,
                    Jobs\MigrateDatabase::class,
                    Jobs\SeedDatabase::class,
                ])->send(function (Events\TenantCreated $event) {
                    return $event->tenant;
                })->shouldBeQueued(true)->toListener(),
            ],

            Events\SavingTenant::class => [],
            Events\TenantSaved::class => [],
            Events\UpdatingTenant::class => [],
            Events\TenantUpdated::class => [],
            Events\DeletingTenant::class => [],
            Events\TenantDeleted::class => [
                function (Events\TenantDeleted $event) {
                    $tenant = $event->tenant;

                    // Only drop the database on force-delete. Soft-delete keeps the
                    // database so the tenant can be restored later without data loss.
                    if ($tenant->isForceDeleting()) {
                        $pipeline = JobPipeline::make([
                            Jobs\DeleteDatabase::class,
                        ])->send(function () use ($tenant) {
                            return $tenant;
                        })->shouldBeQueued(false);

                        $pipeline->toListener()($event);
                    }
                },
            ],

            // Custom domain events
            TenantSuspended::class => [HandleTenantSuspension::class],
            TenantReactivated::class => [
                HandleTenantReactivation::class,
            ],

            // Domain events
            Events\CreatingDomain::class => [],
            Events\DomainCreated::class => [],
            Events\SavingDomain::class => [],
            Events\DomainSaved::class => [],
            Events\UpdatingDomain::class => [],
            Events\DomainUpdated::class => [],
            Events\DeletingDomain::class => [],
            Events\DomainDeleted::class => [],

            // Database events
            Events\DatabaseCreated::class => [],
            Events\DatabaseMigrated::class => [],
            Events\DatabaseSeeded::class => [],
            Events\DatabaseRolledBack::class => [],
            Events\DatabaseDeleted::class => [],

            // Tenancy events
            Events\InitializingTenancy::class => [],
            Events\TenancyInitialized::class => [
                Listeners\BootstrapTenancy::class,
            ],

            Events\EndingTenancy::class => [],
            Events\TenancyEnded::class => [
                Listeners\RevertToCentralContext::class,
            ],

            Events\BootstrappingTenancy::class => [],
            Events\TenancyBootstrapped::class => [
                function () {
                    app(PermissionRegistrar::class)->clearPermissionsCollection();
                },
            ],
            Events\RevertingToCentralContext::class => [],
            Events\RevertedToCentralContext::class => [
                function () {
                    app(PermissionRegistrar::class)->clearPermissionsCollection();
                },
            ],

            // Resource syncing
            Events\SyncedResourceSaved::class => [
                Listeners\UpdateSyncedResource::class,
            ],

            // Fired only when a synced resource is changed in a different DB than the origin DB (to avoid infinite loops)
            Events\SyncedResourceChangedInForeignDatabase::class => [],
        ];
    }

    public function register()
    {
        //
    }

    public function boot()
    {
        $this->bootEvents();
        $this->mapRoutes();

        $this->makeTenancyMiddlewareHighestPriority();
        $this->registerTenantJobFailureHandler();
    }

    protected function bootEvents()
    {
        foreach ($this->events() as $event => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof JobPipeline) {
                    $listener = $listener->toListener();
                }

                Event::listen($event, $listener);
            }
        }
    }

    protected function mapRoutes()
    {
        if (file_exists(base_path('routes/tenant.php'))) {
            Route::namespace(static::$controllerNamespace)
                ->group(base_path('routes/tenant.php'));
        }
    }

    protected function makeTenancyMiddlewareHighestPriority()
    {
        $tenancyMiddleware = [
            // Even higher priority than the initialization middleware
            Middleware\PreventAccessFromCentralDomains::class,

            Middleware\InitializeTenancyByDomain::class,
            Middleware\InitializeTenancyBySubdomain::class,
            Middleware\InitializeTenancyByDomainOrSubdomain::class,
            Middleware\InitializeTenancyByPath::class,
            Middleware\InitializeTenancyByRequestData::class,
        ];

        foreach (array_reverse($tenancyMiddleware) as $middleware) {
            $this->app[Kernel::class]->prependToMiddlewarePriority($middleware);
        }
    }

    protected function registerTenantJobFailureHandler(): void
    {
        Queue::failing(function (JobFailed $event) {
            $payload = $event->job->payload();
            $jobClass = $payload['displayName'] ?? get_class($event->job);

            if (str_contains($jobClass, 'Stancl\Tenancy\Jobs')) {
                Log::error('Tenant provisioning job failed', [
                    'job' => $jobClass,
                    'exception' => $event->exception->getMessage(),
                    'trace' => $event->exception->getTraceAsString(),
                ]);
            }
        });
    }
}

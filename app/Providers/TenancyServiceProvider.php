<?php

declare(strict_types=1);

namespace App\Providers;

use App\Tenants\Events\TenantReactivated;
use App\Tenants\Events\TenantSuspended;
use App\Tenants\Listeners\CreateTenantStorageSkeleton;
use App\Tenants\Listeners\DeleteTenantStorageSkeleton;
use App\Tenants\Listeners\HandleTenantReactivation;
use App\Tenants\Listeners\HandleTenantSuspension;
use App\Tenants\TenantManager;
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

/**
 * Multi-tenancy wiring — the backbone of the central/tenant split.
 *
 * Flow (all registered in `boot()`):
 *  1. `bootEvents()` attaches the stancl tenancy lifecycle listeners and the
 *     provisioning pipeline defined in `events()`. `TenantCreated` queues
 *     `CreateDatabase → MigrateDatabase → SeedDatabase`; `TenantDeleted` drops
 *     the database on force-delete only.
 *  2. `mapRoutes()` loads `routes/tenant.php` (no namespace, callable syntax).
 *  3. `makeTenancyMiddlewareHighestPriority()` prepends tenancy identification
 *     middleware so it runs before middleware that reads tenant data.
 *  4. `registerTenantJobFailureHandler()` logs failures of stancl's provisioning
 *     jobs.
 *
 * @see TenantManager  Application entry point for provisioning and lifecycle.
 * @see docs/architecture/03-multi-tenancy.md
 */
class TenancyServiceProvider extends ServiceProvider
{
    // By default, no namespace is used to support the callable array syntax.
    public static string $controllerNamespace = '';

    /**
     * Event → listener map consumed by {@see self::bootEvents()}.
     *
     * Highlights:
     *  - `TenantCreated` → storage skeleton + a queued provisioning JobPipeline.
     *  - `TenantDeleted` → drop the database and storage skeleton on force-delete only.
     *  - `TenantSuspended` / `TenantReactivated` → module listeners.
     *  - `TenancyInitialized` / `TenancyEnded` → stancl's bootstrap/revert
     *    listeners, plus clearing Spatie's cached permission collection when
     *    tenant context changes.
     *
     * @return array<class-string, array<int, mixed>>
     */
    public function events()
    {
        return [
            // Tenant events — provisioning pipeline runs asynchronously
            Events\CreatingTenant::class => [],
            Events\TenantCreated::class => [
                CreateTenantStorageSkeleton::class,
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
                DeleteTenantStorageSkeleton::class,
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

    /**
     * Boot the tenancy integration: register events, map tenant routes, raise
     * the priority of tenancy middleware, and attach a provisioning job-failure
     * logger.
     */
    public function boot()
    {
        $this->bootEvents();
        $this->mapRoutes();

        $this->makeTenancyMiddlewareHighestPriority();
        $this->registerTenantJobFailureHandler();
    }

    /**
     * Register every entry of {@see self::events()}, unwrapping JobPipeline
     * instances into their listener form first.
     */
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

    /**
     * Load tenant routes from `routes/tenant.php` with no controller namespace,
     * so the closure/array callable syntax used there keeps working.
     */
    protected function mapRoutes()
    {
        if (file_exists(base_path('routes/tenant.php'))) {
            Route::namespace(static::$controllerNamespace)
                ->group(base_path('routes/tenant.php'));
        }
    }

    /**
     * Give tenancy middleware the highest priority so tenant identification runs
     * before any middleware that reads tenant data. Prepended in reverse so the
     * list ends up in the intended order.
     */
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

    /**
     * Log failures of stancl's own tenancy provisioning jobs (CreateDatabase,
     * MigrateDatabase, SeedDatabase) so a broken tenant provision is visible in
     * the logs rather than silently stuck.
     */
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

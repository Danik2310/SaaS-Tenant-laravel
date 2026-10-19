<?php

namespace Tests;

use App\Tenants\Listeners\CreateTenantStorageSkeleton;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Jobs;
use Tests\Support\CloneTenantSchemaJob;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $this->guardAgainstCachedConfiguration();

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        $this->installFastTenantProvisioning();

        return $app;
    }

    /**
     * Replace the automatic `TenantCreated` provisioning pipeline with a
     * test-only variant that clones a pre-migrated schema template instead of
     * running all ~34 tenant migrations for every provisioned tenant
     * (factories, admin API, registration, full seeds).
     *
     * This changes *how* the schema is created, never *what* it is: the
     * template is built by running the real tenant migrations once per process
     * (see TenantSchemaCloner), so cloned databases are byte-for-byte the same
     * schema MigrateDatabase would have produced. Production is untouched —
     * tests that exercise real migrations (TenantMigrationTest, the admin
     * migrate endpoint, TenantSeeder's explicit migrateTenant()) bypass this
     * pipeline entirely.
     *
     * @see TestCase::createTestTenant()
     */
    private function installFastTenantProvisioning(): void
    {
        Event::forget(Events\TenantCreated::class);

        Event::listen(Events\TenantCreated::class, CreateTenantStorageSkeleton::class);
        Event::listen(Events\TenantCreated::class, JobPipeline::make([
            Jobs\CreateDatabase::class,
            CloneTenantSchemaJob::class,
            Jobs\SeedDatabase::class,
        ])->send(function (Events\TenantCreated $event) {
            return $event->tenant;
        })->shouldBeQueued(true)->toListener());
    }

    /**
     * A cached config (bootstrap/cache/config.php) makes the framework ignore
     * phpunit.xml's env overrides, so the suite silently runs against the
     * development environment and database — wiping it on migrate:fresh.
     * `php artisan optimize`, which the /super-commit flow runs, creates
     * exactly that file. Fail fast with a clear remedy instead of producing
     * hundreds of misleading failures.
     */
    private function guardAgainstCachedConfiguration(): void
    {
        if (! is_file(dirname(__DIR__).'/bootstrap/cache/config.php')) {
            return;
        }

        throw new RuntimeException(
            'Configuration is cached at bootstrap/cache/config.php, so tests would run against the '
            .'wrong environment and database. Run `php artisan optimize:clear` and re-run the suite.'
        );
    }
}

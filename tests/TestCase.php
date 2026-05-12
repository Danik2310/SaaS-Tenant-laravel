<?php

namespace Tests;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Create a test tenant for testing, with tenant migrations run.
     *
     * Uses Tenant::withoutEvents() to prevent ActivityLogObserver from firing
     * (which would fail on string tenant IDs in integer subject_id columns).
     * Configures the 'tenant' connection manually and purges it before
     * running migrations to avoid connection caching issues.
     */
    protected function createTestTenant(): Tenant
    {
        $tenant = Tenant::withoutEvents(function () {
            $t = Tenant::create([
                'id' => 'test-' . uniqid(),
            ]);

            $t->database()->makeCredentials();
            $t->database()->manager()->createDatabase($t);
            $t->save();

            return $t;
        });

        $dbName = $tenant->database()->getName();

        // Configure the tenant connection explicitly to avoid caching issues
        // where tenancy()->initialize() caches a connection with the wrong DB name.
        config([
            'database.connections.tenant' => [
                'driver' => env('DB_CONNECTION', 'mysql'),
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '3306'),
                'database' => $dbName,
                'username' => env('DB_USERNAME', 'root'),
                'password' => env('DB_PASSWORD', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => true,
                'engine' => null,
            ],
        ]);

        DB::purge('tenant');

        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--database' => 'tenant',
            '--force' => true,
        ]);

        return $tenant;
    }

    /**
     * Initialize the tenancy for a given tenant.
     */
    protected function initializeTenant(Tenant $tenant): void
    {
        tenancy()->initialize($tenant);
        // point default connection at tenant so Eloquent models hit the right DB
        config(['database.default' => 'tenant']);
    }

    /**
     * Forget the current tenant.
     */
    protected function forgetTenant(): void
    {
        tenancy()->end();
        // restore central connection as default
        config(['database.default' => env('DB_CONNECTION', 'mysql')]);
    }
}

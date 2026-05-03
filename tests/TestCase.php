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
     * Create a test tenant for testing.
     */
    protected function createTestTenant(): Tenant
    {
        $tenant = Tenant::create([
            'id' => 'test-' . uniqid(),
        ]);

        $tenant->database()->makeCredentials();
        // Provision the database using the configured manager
        $tenant->database()->manager()->createDatabase($tenant);
        $tenant->save();

        // Initialize the tenant context so the `tenant` connection is configured
        tenancy()->initialize($tenant);

        // make sure the connection config actually has the database name
        config(['database.connections.tenant.database' => $tenant->database()->getName()]);

        // Run migrations using the standard migrator but target the tenant connection.
        // Limiting the path prevents central scripts from re‑running.
        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--database' => 'tenant',
            '--force' => true,
        ]);

        // End the tenant context so subsequent operations use the central DB again.
        tenancy()->end();

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

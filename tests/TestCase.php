<?php

namespace Tests;

use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected $connectionsToTransact = ['mysql'];

    /**
     * Purge committed leftovers that RefreshDatabase's transaction never
     * rolls back, so tests never observe another test's data.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->purgeCentralLeaks();
        $this->reSeedCanonicalData();
    }

    /**
     * mysql_central shares the same physical database as mysql but is NOT
     * wrapped by RefreshDatabase's transaction (only ['mysql'] is), and
     * createTestTenant() commits the active transaction when it issues
     * CREATE DATABASE (MySQL DDL auto-commits). Rows written that way
     * therefore persist across tests. Delete them here — via mysql_central,
     * whose statements commit — so tests never observe another test's data.
     */
    private function purgeCentralLeaks(): void
    {
        $central = DB::connection('mysql_central');

        // FK-safe order: children before parents.
        foreach ([
            'activity_log',
            'personal_access_tokens',
            'admin_users',
            'subscription_payments',
            'subscriptions',
            'tenant_resource_usage',
            'resource_usage_history',
            'plan_features',
            'plans',
            'payment_methods',
            'feature_flags',
            'global_settings',
            'domains',
            'tenants',
        ] as $table) {
            try {
                $central->table($table)->delete();
            } catch (\Throwable $e) {
                // Table may be missing mid-migration in MigrationUpDownTest.
            }
        }

        $this->dropOrphanTenantDatabases($central);
    }

    /**
     * Tenant databases (e.g. "tenanttest-et-iste") are never dropped by the
     * tests that create them — CREATE DATABASE auto-commits and there is no
     * transaction to roll back. They accumulate across runs and eventually
     * collide with a faker-generated tenant id (TenantFactory ids are only
     * unique within a single process), aborting the next run with
     * TenantDatabaseAlreadyExistsException. Drop every orphan here.
     */
    private function dropOrphanTenantDatabases($central): void
    {
        $prefix = config('tenancy.database.prefix');

        if (! $prefix) {
            return;
        }

        $pattern = '/^'.preg_quote($prefix, '/').'[a-z0-9-]+$/';

        foreach ($central->select('SHOW DATABASES') as $db) {
            $name = $db->Database ?? $db->name;

            // Only auto-provisioned tenant DBs match (prefix + tenant slug);
            // central DBs like "saas_app_testing" are never touched.
            if (! preg_match($pattern, $name)) {
                continue;
            }

            try {
                $central->statement("DROP DATABASE `{$name}`");
            } catch (\Throwable $e) {
                // A DB may be locked by another process — leave it for the next run.
            }
        }
    }

    /**
     * The feature-flag catalog and the default global settings are seeded by
     * migrations only during migrate:fresh (once per process).
     * purgeCentralLeaks() deletes those rows before every test, so re-create
     * the canonical rows here to keep the seeded baseline intact for tests
     * that rely on it.
     */
    private function reSeedCanonicalData(): void
    {
        $central = DB::connection('mysql_central');

        try {
            $sortOrder = 0;

            foreach (config('plan_features', []) as $key => $definition) {
                $central->table('feature_flags')->updateOrInsert(
                    ['key' => $key],
                    [
                        'label' => $definition['label'],
                        'description' => $definition['description'] ?? null,
                        'is_locked' => true,
                        'is_active' => true,
                        'sort_order' => $sortOrder++,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        } catch (\Throwable $e) {
            // Table may be missing mid-migration in MigrationUpDownTest.
        }

        try {
            foreach ([
                ['key' => 'app_name', 'value' => 'SaaS Admin'],
                ['key' => 'app_description', 'value' => 'Multi-tenant SaaS Management Console'],
                ['key' => 'support_email', 'value' => 'support@example.com'],
                ['key' => 'allow_registration', 'value' => 'true'],
                ['key' => 'maintenance_mode', 'value' => 'false'],
                ['key' => 'default_plan_id', 'value' => '1'],
                ['key' => 'tenant_db_prefix', 'value' => 'tenant'],
                ['key' => 'currency', 'value' => 'USD'],
            ] as $setting) {
                $central->table('global_settings')->updateOrInsert(
                    ['key' => $setting['key']],
                    [
                        'value' => $setting['value'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        } catch (\Throwable $e) {
            // Table may be missing mid-migration in MigrationUpDownTest.
        }
    }

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
        $this->seed(PlanSeeder::class);

        $tenant = Tenant::withoutEvents(function () {
            $tenantId = 'test-'.uniqid();
            $t = Tenant::create([
                'id' => $tenantId,
                'domain' => $tenantId.'.localhost',
            ]);

            $t->database()->makeCredentials();
            $t->database()->manager()->createDatabase($t);
            $t->save();

            // Create a domain record so the InitializeTenancyByDomain middleware can identify this tenant
            Domain::create([
                'tenant_id' => $t->id,
                'domain' => $t->domain,
                'is_primary' => true,
            ]);

            // Refresh from DB to pick up column defaults (e.g. status = 'Active')
            $t->refresh();

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
            '--env' => 'testing',
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

        if ($tenant->domain) {
            // Force URL generation to the tenant domain. A plain Host header is
            // ignored because Symfony's Request::create overrides HTTP_HOST with
            // the URL's host, which resolves to the baseUrl (localhost — a central
            // domain) and gets 404'd by PreventAccessFromCentralDomains.
            $tenantUrl = 'http://'.$tenant->domain;
            config(['app.url' => $tenantUrl]);
            app('url')->forceRootUrl($tenantUrl);
        }
    }

    /**
     * Act as a given user, minting a real JWT for tenant users so tenant
     * auth middleware (jwt.tenant) can validate the token's tenant claim.
     */
    public function actingAs($user, $guard = null)
    {
        if (($guard === null || $guard === 'web')
            && $user instanceof User
            && tenancy()->initialized
        ) {
            $token = Auth::guard('web')->login($user);
            $this->withCredentials()->withUnencryptedCookie('token', $token);
        }

        return parent::actingAs($user, $guard);
    }

    /**
     * Forget the current tenant.
     */
    protected function forgetTenant(): void
    {
        tenancy()->end();
        // restore central connection as default
        config(['database.default' => env('DB_CONNECTION', 'mysql')]);
        config(['app.url' => env('APP_URL', 'http://localhost')]);
        app('url')->forceRootUrl(null);
    }
}

<?php

namespace Tests;

use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected $connectionsToTransact = ['mysql'];

    /**
     * Name of the tenant database whose schema every test tenant is cloned
     * from. Built once per test process by tenantTemplateDatabase().
     */
    private static ?string $tenantTemplateDatabase = null;

    /**
     * Whether the "keep migrate:fresh fast" teardown hook has been registered
     * for this test (see createTestTenant()).
     */
    protected bool $tenantRefreshHookRegistered = false;

    /**
     * Cached [table, CREATE statement] pairs from the tenant template, built
     * once per process (see cloneTenantSchema()).
     */
    private static array $tenantTemplateDdl = [];

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

        // Test-generated tenants always use an id prefixed with "test-",
        // producing databases like "tenanttest-abcd1234". Narrow the drop to
        // those so real (seeded / admin-created) tenant databases — e.g.
        // "tenantagencia-de-viajes-aventura" — are never destroyed by tests.
        $pattern = '/^'.preg_quote($prefix, '/').'test-[a-z0-9-]+$/';

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
        // Creating a tenant issues DDL (CREATE DATABASE), which MySQL
        // auto-commits and therefore destroys RefreshDatabase's transaction.
        // Laravel then treats the database as dirty and re-runs migrate:fresh
        // before *every* subsequent test (RefreshDatabase.php:159) — ~4.5s
        // each. The central schema is still intact and purgeCentralLeaks()
        // already removes committed rows, so restore the "migrated once" fast
        // path at teardown (our callback is registered after RefreshDatabase's,
        // so it runs last and wins).
        if (! $this->tenantRefreshHookRegistered) {
            $this->tenantRefreshHookRegistered = true;
            $this->beforeApplicationDestroyed(function () {
                RefreshDatabaseState::$migrated = true;
            });
        }

        $this->seed(PlanSeeder::class);

        $tenant = Tenant::withoutEvents(function () {
            $tenantId = 'test-'.uniqid();
            $t = Tenant::create([
                'id' => $tenantId,
            ]);

            $t->database()->makeCredentials();
            $t->database()->manager()->createDatabase($t);
            $t->save();

            // Create a domain record so the InitializeTenancyByDomain middleware can identify this tenant
            Domain::create([
                'tenant_id' => $t->id,
                'domain' => $tenantId.'.sasapp',
                'is_primary' => true,
            ]);

            // Refresh from DB to pick up column defaults (e.g. status = 'Active')
            $t->refresh();

            return $t;
        });

        $dbName = $tenant->database()->getName();

        // Clone the pre-migrated tenant schema instead of running all ~34
        // tenant migrations for every test (~7s each). Tenant DDL auto-commits
        // and so cannot be rolled back by RefreshDatabase, which is why every
        // test still gets its own physical database — it is just created from
        // a template built once per process.
        $this->cloneTenantSchema($this->tenantTemplateDatabase(), $dbName);

        $this->useTenantConnection($dbName);

        return $tenant;
    }

    /**
     * Build — once per test process — a tenant database with every tenant
     * migration applied. Subsequent tests clone its schema, turning several
     * seconds of per-test migrations into a handful of fast DDL statements.
     */
    private function tenantTemplateDatabase(): string
    {
        $template = config('tenancy.database.prefix').'schema-template';

        if (self::$tenantTemplateDatabase === $template && $this->tenantDatabaseExists($template)) {
            return $template;
        }

        $central = DB::connection('mysql_central');
        $central->statement("DROP DATABASE IF EXISTS `{$template}`");
        $central->statement("CREATE DATABASE `{$template}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $this->useTenantConnection($template);

        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--database' => 'tenant',
            '--env' => 'testing',
            '--force' => true,
        ]);

        return self::$tenantTemplateDatabase = $template;
    }

    /**
     * Copy every base table from the migrated template database into the
     * target database, foreign keys included.
     */
    private function cloneTenantSchema(string $source, string $target): void
    {
        $central = DB::connection('mysql_central');

        $central->statement("CREATE DATABASE IF NOT EXISTS `{$target}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        // The template's DDL is read once and reused, so each test only pays
        // for executing the CREATE statements, not for re-introspecting.
        if (self::$tenantTemplateDdl === []) {
            $tables = $central->select(
                "SELECT table_name AS name FROM information_schema.tables
                 WHERE table_schema = ? AND table_type = 'BASE TABLE'",
                [$source]
            );

            foreach ($tables as $table) {
                self::$tenantTemplateDdl[] = [
                    $table->name,
                    $central->select("SHOW CREATE TABLE `{$source}`.`{$table->name}`")[0]->{'Create Table'},
                ];
            }
        }

        // Foreign-key checks are disabled so tables may be created in any
        // order (a child can reference a parent that does not exist yet).
        $central->statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach (self::$tenantTemplateDdl as [$name, $ddl]) {
                // SHOW CREATE TABLE emits an unqualified `CREATE TABLE `name``;
                // qualify it with the target database.
                $central->statement(preg_replace(
                    '/^CREATE TABLE `'.preg_quote($name, '/').'`/i',
                    "CREATE TABLE `{$target}`.`{$name}`",
                    $ddl,
                    1
                ));
            }
        } finally {
            $central->statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function tenantDatabaseExists(string $name): bool
    {
        return DB::connection('mysql_central')
            ->select('SELECT schema_name FROM information_schema.schemata WHERE schema_name = ?', [$name]) !== [];
    }

    /**
     * Point the 'tenant' connection at a database, sidestepping the connection
     * caching issues where tenancy()->initialize() caches the wrong DB name.
     */
    private function useTenantConnection(string $database): void
    {
        config([
            'database.connections.tenant' => [
                'driver' => env('DB_CONNECTION', 'mysql'),
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '3306'),
                'database' => $database,
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

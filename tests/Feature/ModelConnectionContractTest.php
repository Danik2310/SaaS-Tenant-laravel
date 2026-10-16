<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

/**
 * Contract guard: models whose table lives in the central database MUST declare
 * `protected $connection = 'mysql_central'`.
 *
 * The database-per-tenant setup switches the *default* connection to the tenant
 * database during a tenant request. A central model that relies on the default
 * connection would therefore silently read/write the tenant database instead of
 * the central one. This test scans every model in app/Models and fails if a
 * model backed by a central-only table omits the explicit connection.
 *
 * A small allowlist documents the models that legitimately use the default
 * connection (shared tables and the Spatie/stancl base models).
 */
class ModelConnectionContractTest extends TestCase
{
    /**
     * Central tables whose model intentionally does NOT use 'mysql_central'.
     *
     * @var array<string, string> table => reason
     */
    private const EXCEPTIONS = [
        'users' => 'App\Models\User is the web-guard model, shared by central and tenant contexts',
        'roles' => 'App\Models\Role (Spatie) — permission tables migrated on the default connection',
        'permissions' => 'App\Models\Permission (Spatie) — permission tables migrated on the default connection',
        'admin_users' => 'App\Models\AdminUser — Spatie lock contention; only accessed in central context',
        'tenants' => 'App\Models\Tenant (stancl base) — resolves via the default/central connection',
        'domains' => 'App\Models\Domain (stancl base) — resolves via the default/central connection',
    ];

    public function test_central_models_declare_mysql_central_connection(): void
    {
        $centralTables = $this->tablesFromMigrations('database/migrations/*.php');

        $this->assertNotEmpty($centralTables, 'No central tables were detected — the migration scan is broken.');

        $violations = [];
        $checked = 0;

        foreach ($this->modelClasses() as $class) {
            /** @var Model $model */
            $model = new $class;
            $table = $model->getTable();

            // Only central-table-backed models are subject to the contract.
            if (! in_array($table, $centralTables, true)) {
                continue;
            }

            // Documented, intentional exceptions.
            if (array_key_exists($table, self::EXCEPTIONS)) {
                continue;
            }

            $checked++;

            if ($model->getConnectionName() !== 'mysql_central') {
                $violations[] = sprintf('%s (table: %s, connection: %s)', $class, $table, $model->getConnectionName() ?? 'null');
            }
        }

        $this->assertGreaterThan(
            0,
            $checked,
            'No central-table models were checked — the model scan is broken (vacuous pass).'
        );

        $this->assertSame(
            [],
            $violations,
            "Central models must declare protected \$connection = 'mysql_central':\n - ".implode("\n - ", $violations)
        );
    }

    public function test_documented_connection_exceptions_are_real_central_tables(): void
    {
        $centralTables = $this->tablesFromMigrations('database/migrations/*.php');

        foreach (array_keys(self::EXCEPTIONS) as $table) {
            $this->assertContains(
                $table,
                $centralTables,
                "The exception table '{$table}' is not a central table — remove or update the stale exception."
            );
        }
    }

    /**
     * Collect table names referenced by Schema::create()/Schema::table() in the
     * given migration glob (top-level central migrations only).
     *
     * @return list<string>
     */
    private function tablesFromMigrations(string $glob): array
    {
        $tables = [];

        foreach (glob(base_path($glob)) as $file) {
            $contents = (string) file_get_contents($file);

            if (preg_match_all("/Schema::(?:create|table)\(\s*['\"]([a-zA-Z0-9_]+)['\"]/", $contents, $matches)) {
                foreach ($matches[1] as $table) {
                    $tables[$table] = true;
                }
            }
        }

        return array_keys($tables);
    }

    /**
     * Resolve every concrete Eloquent model class in app/Models.
     *
     * @return list<class-string<Model>>
     */
    private function modelClasses(): array
    {
        $classes = [];

        foreach (glob(app_path('Models/*.php')) as $file) {
            $class = 'App\\Models\\'.basename($file, '.php');

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);

            if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Model::class)) {
                continue;
            }

            $classes[] = $class;
        }

        return $classes;
    }
}

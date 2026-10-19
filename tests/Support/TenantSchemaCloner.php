<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Builds — once per test process — a tenant database with every tenant
 * migration applied, and clones its schema into new tenant databases.
 *
 * Shared by TestCase::createTestTenant() and CloneTenantSchemaJob so the
 * automatic `TenantCreated` provisioning pipeline in tests clones a
 * pre-migrated schema instead of running all ~34 tenant migrations for every
 * tenant (~7s each). Tenant DDL auto-commits and cannot be rolled back by
 * RefreshDatabase, which is why every test still gets its own physical
 * database — it is merely created from a template built once per process.
 */
final class TenantSchemaCloner
{
    /**
     * Name of the template database, cached once built in this process.
     */
    private static ?string $template = null;

    /**
     * Cached [table, CREATE statement] pairs read from the template, so each
     * clone pays only for executing the DDL, not for re-introspecting.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private static array $ddl = [];

    /**
     * Return the template database, running the tenant migrations once per
     * process if it does not already exist.
     */
    public static function template(): string
    {
        $template = config('tenancy.database.prefix').'schema-template';

        if (self::$template === $template && self::databaseExists($template)) {
            return $template;
        }

        $central = DB::connection('mysql_central');
        $central->statement("DROP DATABASE IF EXISTS `{$template}`");
        $central->statement("CREATE DATABASE `{$template}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        self::useTenantConnection($template);

        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--database' => 'tenant',
            '--env' => 'testing',
            '--force' => true,
        ]);

        // A freshly migrated template invalidates any cached DDL.
        self::$ddl = [];

        return self::$template = $template;
    }

    /**
     * Clone the template schema into a target database and point the `tenant`
     * connection at it.
     */
    public static function cloneTo(string $target): void
    {
        self::cloneTenantSchema(self::template(), $target);
        self::useTenantConnection($target);
    }

    /**
     * Copy every base table from the migrated template database into the
     * target database, foreign keys included.
     */
    public static function cloneTenantSchema(string $source, string $target): void
    {
        $central = DB::connection('mysql_central');

        $central->statement("CREATE DATABASE IF NOT EXISTS `{$target}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        if (self::$ddl === []) {
            $tables = $central->select(
                "SELECT table_name AS name FROM information_schema.tables
                 WHERE table_schema = ? AND table_type = 'BASE TABLE'",
                [$source]
            );

            foreach ($tables as $table) {
                self::$ddl[] = [
                    $table->name,
                    $central->select("SHOW CREATE TABLE `{$source}`.`{$table->name}`")[0]->{'Create Table'},
                ];
            }
        }

        // Foreign-key checks are disabled so tables may be created in any
        // order (a child can reference a parent that does not exist yet).
        $central->statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach (self::$ddl as [$name, $ddl]) {
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

    public static function databaseExists(string $name): bool
    {
        return DB::connection('mysql_central')
            ->select('SELECT schema_name FROM information_schema.schemata WHERE schema_name = ?', [$name]) !== [];
    }

    /**
     * Point the `tenant` connection at a database, sidestepping the connection
     * caching issues where tenancy()->initialize() caches the wrong DB name.
     */
    public static function useTenantConnection(string $database): void
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
}

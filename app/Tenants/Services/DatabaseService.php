<?php

declare(strict_types=1);

namespace App\Tenants\Services;

use App\Tenants\Contracts\DatabaseServiceInterface;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager;

class DatabaseService implements DatabaseServiceInterface
{
    private MySQLDatabaseManager $manager;

    public function __construct()
    {
        $this->manager = new MySQLDatabaseManager;
        $this->manager->setConnection(config('tenancy.database.central_connection'));
    }

    public function databaseExists(string $name): bool
    {
        return $this->manager->databaseExists($name);
    }

    public function dropDatabase(string $name): void
    {
        // `DROP DATABASE IF EXISTS` keeps the operation idempotent: it never
        // throws when the database is already gone, so a concurrent drop (or a
        // re-run of an orphan reaper) cannot be masked as a hard failure.
        // stancl's MySQLDatabaseManager issues a bare `DROP DATABASE`, which
        // raises "Unknown database" in that case.
        $connection = (string) config('tenancy.database.central_connection');
        $safe = '`'.str_replace('`', '``', $name).'`';

        DB::connection($connection)->statement("DROP DATABASE IF EXISTS {$safe}");
    }

    public function getDatabaseSizeKb(string $name): int
    {
        $result = DB::connection(config('tenancy.database.central_connection'))
            ->table('information_schema.tables')
            ->selectRaw('ROUND(SUM(data_length + index_length) / 1024, 0) as size_kb')
            ->where('table_schema', $name)
            ->value('size_kb');

        return (int) ($result ?? 0);
    }
}

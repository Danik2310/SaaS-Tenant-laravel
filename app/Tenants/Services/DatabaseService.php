<?php

declare(strict_types=1);

namespace App\Tenants\Services;

use App\Models\Tenant;
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
        $stub = new Tenant;
        $stub->setInternal('db_name', $name);

        $this->manager->deleteDatabase($stub);
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

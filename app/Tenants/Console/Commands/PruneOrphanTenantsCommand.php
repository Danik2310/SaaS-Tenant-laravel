<?php

declare(strict_types=1);

namespace App\Tenants\Console\Commands;

use App\Models\Tenant;
use App\Tenants\Contracts\DatabaseServiceInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * `tenants:prune-orphans` — detects drift between tenant rows and tenant databases.
 *
 * Two directions are reconciled:
 *  - A database matching the tenant naming pattern with **no** tenant row → orphan database.
 *  - A tenant row (including soft-deleted) with **no** database → orphan row.
 *
 * Report-only by default. Pass `--force` to actually drop orphan databases and
 * `--delete-rows` to force-delete rows whose database is missing.
 */
class PruneOrphanTenantsCommand extends Command
{
    protected $signature = 'tenants:prune-orphans
        {--force : Drop orphan tenant databases (default is report-only)}
        {--delete-rows : Also force-delete tenant rows whose database is missing (requires --force)}';

    protected $description = 'Detect and (with --force) remove orphan tenant databases and rows';

    public function handle(DatabaseServiceInterface $databaseService): int
    {
        $prefix = (string) config('tenancy.database.prefix', 'tenant');
        $suffix = (string) config('tenancy.database.suffix', '');
        $central = (string) config('tenancy.database.central_connection', 'mysql_central');

        $schemas = DB::connection($central)
            ->table('information_schema.schemata')
            ->select('SCHEMA_NAME as schema_name')
            ->pluck('schema_name')
            ->all();

        $orphanDatabases = [];

        foreach ($schemas as $schema) {
            if (! $this->matchesTenantPattern($schema, $prefix, $suffix)) {
                continue;
            }

            $tenantId = substr($schema, strlen($prefix), strlen($schema) - strlen($prefix) - strlen($suffix));

            if (! Tenant::withTrashed()->where('id', $tenantId)->exists()) {
                $orphanDatabases[] = ['schema' => $schema, 'tenant_id' => $tenantId];
            }
        }

        $orphanRows = [];
        foreach (Tenant::withTrashed()->pluck('id') as $tenantId) {
            $database = $prefix.$tenantId.$suffix;

            if (! in_array($database, $schemas, true)) {
                $orphanRows[] = ['tenant_id' => $tenantId, 'database' => $database];
            }
        }

        $this->reportOrphans($orphanDatabases, $orphanRows);

        if (empty($orphanDatabases) && empty($orphanRows)) {
            $this->info('No orphan tenant databases or rows found.');

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->warn('Report-only mode. Re-run with --force to drop orphan databases.');

            return self::SUCCESS;
        }

        foreach ($orphanDatabases as $orphan) {
            $databaseService->dropDatabase($orphan['schema']);
            $this->info("Dropped orphan database {$orphan['schema']}.");
        }

        if ($this->option('delete-rows')) {
            foreach ($orphanRows as $orphan) {
                Tenant::withTrashed()->where('id', $orphan['tenant_id'])->forceDelete();
                $this->info("Force-deleted orphan tenant row {$orphan['tenant_id']}.");
            }
        }

        return self::SUCCESS;
    }

    private function matchesTenantPattern(string $schema, string $prefix, string $suffix): bool
    {
        if (! str_starts_with($schema, $prefix)) {
            return false;
        }

        if ($suffix !== '' && ! str_ends_with($schema, $suffix)) {
            return false;
        }

        // Must have at least one character of tenant id between prefix and suffix.
        return strlen($schema) > strlen($prefix) + strlen($suffix);
    }

    /**
     * @param  array<int, array{schema: string, tenant_id: string}>  $orphanDatabases
     * @param  array<int, array{tenant_id: string, database: string}>  $orphanRows
     */
    private function reportOrphans(array $orphanDatabases, array $orphanRows): void
    {
        if (! empty($orphanDatabases)) {
            $this->warn(count($orphanDatabases).' orphan database(s) without a tenant row:');
            foreach ($orphanDatabases as $orphan) {
                $this->line("  - {$orphan['schema']} (tenant_id: {$orphan['tenant_id']})");
            }
        }

        if (! empty($orphanRows)) {
            $this->warn(count($orphanRows).' tenant row(s) without a database:');
            foreach ($orphanRows as $orphan) {
                $this->line("  - {$orphan['tenant_id']} (database: {$orphan['database']})");
            }
        }
    }
}

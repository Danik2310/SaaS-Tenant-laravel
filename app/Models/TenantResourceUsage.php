<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class TenantResourceUsage extends Model
{
    /**
     * Always use the central MySQL connection, even when tenancy is active.
     * This ensures increment/decrement operations always hit the central DB.
     */
    protected $connection = 'mysql';

    protected $table = 'tenant_resource_usage';

    protected $fillable = [
        'tenant_id',
        'users_count',
        'storage_kb',
        'db_size_kb',
        'products_count',
        'orders_count',
        'collected_at',
    ];

    protected $casts = [
        'users_count' => 'integer',
        'storage_kb' => 'integer',
        'db_size_kb' => 'integer',
        'products_count' => 'integer',
        'orders_count' => 'integer',
        'collected_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Atomically increment (or decrement with a negative delta) a usage counter
     * for a given tenant. The row is created first if it doesn't exist.
     *
     * This method always operates on the central 'mysql' connection, so it
     * remains safe to call from within tenant context (e.g. model observers).
     *
     * Failures are logged and silently swallowed — resource usage tracking
     * is non-critical and can be rebuilt by the CollectTenantMetrics job.
     */
    public static function incrementCount(string $tenantId, string $column, int $delta = 1): void
    {
        try {
            // Ensure a row exists for this tenant with sensible defaults.
            // Handles race condition: when two concurrent requests both find
            // no row and attempt INSERT, the second gets a duplicate key error.
            try {
                static::firstOrCreate(
                    ['tenant_id' => $tenantId],
                    [
                        'users_count' => 0,
                        'products_count' => 0,
                        'orders_count' => 0,
                        'storage_kb' => 0,
                        'db_size_kb' => 0,
                    ]
                );
            } catch (QueryException $e) {
                // SQLSTATE 23000 = integrity constraint violation (duplicate key).
                // This is safe to ignore — the row was created by a concurrent request.
                if ((string) $e->getCode() !== '23000') {
                    throw $e;
                }
            }

            // Atomic SQL: UPDATE tenant_resource_usage SET column = column + delta WHERE tenant_id = ?
            static::where('tenant_id', $tenantId)->increment($column, $delta);
        } catch (\Throwable $e) {
            // Resource usage tracking is non-critical. Failures should not
            // propagate to the caller (e.g., a user creation request would
            // fail because the central DB was unreachable). The
            // CollectTenantMetrics job will recalculate counts.
            Log::warning('Failed to track resource usage for tenant {tenant}', [
                'tenant' => $tenantId,
                'column' => $column,
                'delta' => $delta,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

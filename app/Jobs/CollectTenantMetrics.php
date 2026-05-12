<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\TenantResourceUsage;
use Illuminate\Support\Facades\DB;

class CollectTenantMetrics extends TenantAwareJob
{
    protected function execute(): void
    {
        $tenant = $this->tenant;

        $usersCount = DB::table('users')->count();
        $productsCount = DB::table('products')->count();
        $ordersCount = DB::table('orders')->count();

        $dbSize = 0;
        try {
            $dbName = $tenant->database()->getName();
            $result = DB::connection('mysql')->select('
                SELECT ROUND(SUM(data_length + index_length) / 1024, 0) AS size_kb
                FROM information_schema.tables
                WHERE table_schema = ?
            ', [$dbName]);
            $dbSize = (int) ($result[0]->size_kb ?? 0);
        } catch (\Exception $e) {
            \Log::warning("Failed to collect DB size for tenant {$tenant->id}: {$e->getMessage()}");
        }

        $storageKb = 0;
        try {
            $tenantStorage = storage_path("tenant/{$tenant->id}");
            if (is_dir($tenantStorage)) {
                $size = 0;
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($tenantStorage, \RecursiveDirectoryIterator::SKIP_DOTS)
                );
                foreach ($iterator as $file) {
                    $size += $file->getSize();
                }
                $storageKb = (int) ceil($size / 1024);
            }
        } catch (\Exception $e) {
            \Log::warning("Failed to collect storage size for tenant {$tenant->id}: {$e->getMessage()}");
        }

        TenantResourceUsage::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'users_count' => $usersCount,
                'products_count' => $productsCount,
                'orders_count' => $ordersCount,
                'storage_kb' => $storageKb,
                'db_size_kb' => $dbSize,
                'collected_at' => now(),
            ]
        );
    }
}

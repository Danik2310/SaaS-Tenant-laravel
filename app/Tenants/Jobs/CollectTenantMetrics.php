<?php

declare(strict_types=1);

namespace App\Tenants\Jobs;

use App\Models\ResourceUsageHistory;
use App\Models\TenantResourceUsage;
use App\Shared\Jobs\TenantAwareJob;
use App\Tenants\Contracts\DatabaseServiceInterface;

class CollectTenantMetrics extends TenantAwareJob
{
    protected function execute(): void
    {
        $tenant = $this->tenant;
        $databaseService = app(DatabaseServiceInterface::class);

        $usersCount = \DB::table('users')->count();
        $productsCount = \DB::table('products')->count();
        $ordersCount = \DB::table('orders')->count();
        $warehousesCount = \DB::table('warehouses')->count();
        $categoriesCount = \DB::table('categories')->count();

        $dbSize = 0;
        try {
            $dbName = $tenant->database()->getName();
            $dbSize = $databaseService->getDatabaseSizeKb($dbName);
        } catch (\Exception $e) {
            \Log::warning("Failed to collect DB size for tenant {$tenant->id}: {$e->getMessage()}");
        }

        $storageKb = 0;
        try {
            $tenantStorage = storage_path();
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
                'warehouses_count' => $warehousesCount,
                'categories_count' => $categoriesCount,
                'storage_kb' => $storageKb,
                'db_size_kb' => $dbSize,
                'collected_at' => now(),
            ]
        );

        try {
            ResourceUsageHistory::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'snapshot_date' => now()->toDateString(),
                ],
                [
                    'users_count' => $usersCount,
                    'products_count' => $productsCount,
                    'orders_count' => $ordersCount,
                    'storage_kb' => $storageKb,
                    'db_size_kb' => $dbSize,
                ]
            );
        } catch (\Exception $e) {
            \Log::warning("Failed to record usage history for tenant {$tenant->id}: {$e->getMessage()}");
        }
    }
}

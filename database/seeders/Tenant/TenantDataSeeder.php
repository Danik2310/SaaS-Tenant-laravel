<?php

namespace Database\Seeders\Tenant;

use App\Models\Plan;

class TenantDataSeeder
{
    /**
     * Run all tenant-scoped data seeders.
     * Called inside tenant context ($tenant->run()).
     */
    public function run(string $tenantEmail, ?Plan $plan): void
    {
        $maxUsers = $plan ? ($plan->max_users ?? 3) : 2;
        $maxCategories = $plan ? ($plan->max_categories ?? 5) : 3;
        $maxWarehouses = $plan ? ($plan->max_warehouses ?? 1) : 1;
        $maxProducts = $plan ? ($plan->max_products ?? 25) : 10;

        $userCount = max(1, (int) round($maxUsers * 0.75));
        $customerCount = max(5, $maxUsers * 5);
        $categoryCount = max(3, (int) round($maxCategories * 0.8));
        $warehouseCount = max(1, (int) round($maxWarehouses * 0.8));
        $productCount = max(5, (int) round($maxProducts * 0.7));
        $orderCount = max(3, (int) round($productCount * 0.3));

        $this->call(UserSeeder::class, 'run', [$userCount, $tenantEmail]);
        $this->call(SettingSeeder::class, 'run', []);
        $this->call(CustomerSeeder::class, 'run', [$customerCount]);
        $this->call(CategorySeeder::class, 'run', [$categoryCount]);
        $this->call(WarehouseSeeder::class, 'run', [$warehouseCount]);
        $this->call(ProductSeeder::class, 'run', [$productCount]);
        $this->call(InventorySeeder::class, 'run', []);
        $this->call(OrderSeeder::class, 'run', [$orderCount]);
    }

    private function call(string $class, string $method, array $params): void
    {
        $instance = app($class);
        $instance->$method(...$params);
    }
}

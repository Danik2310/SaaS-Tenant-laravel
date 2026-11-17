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
        $cap = max(1, (int) config('seeding.demo.records_per_tenant_table', 10));

        // Plan limits take precedence when they are lower than the demo cap.
        $userCount = $this->cappedCount($plan?->max_users, $cap);
        $categoryCount = $this->cappedCount($plan?->max_categories, $cap);
        $warehouseCount = $this->cappedCount($plan?->max_warehouses, $cap);
        $productCount = $this->cappedCount($plan?->max_products, $cap);

        $this->call(UserSeeder::class, 'run', [$userCount, $tenantEmail]);
        $this->call(SettingSeeder::class, 'run', []);
        $this->call(CustomerSeeder::class, 'run', [$cap]);
        $this->call(CategorySeeder::class, 'run', [$categoryCount]);
        $this->call(WarehouseSeeder::class, 'run', [$warehouseCount]);
        $this->call(ProductSeeder::class, 'run', [$productCount]);
        $this->call(InventorySeeder::class, 'run', []);
        $this->call(OrderSeeder::class, 'run', [$cap]);
    }

    /**
     * Cap a plan-derived count at the demo profile value.
     *
     * A null or non-positive plan limit means "unlimited" and falls back to
     * the demo cap; otherwise the smaller of the two values is used.
     */
    private function cappedCount(?int $planLimit, int $cap): int
    {
        if ($planLimit === null || $planLimit <= 0) {
            return $cap;
        }

        return max(1, min($planLimit, $cap));
    }

    private function call(string $class, string $method, array $params): void
    {
        $instance = app($class);
        $instance->$method(...$params);
    }
}

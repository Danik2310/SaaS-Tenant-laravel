<?php

namespace Database\Seeders\Central;

use App\Models\Tenant;
use App\Models\TenantResourceUsage;
use Illuminate\Database\Seeder;

class TenantResourceUsageSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::with('plan')->get();

        foreach ($tenants as $tenant) {
            TenantResourceUsage::updateOrCreate(
                ['tenant_id' => $tenant->id],
                [
                    'users_count' => rand(1, max(2, $tenant->plan?->max_users ?? 10)),
                    'products_count' => rand(5, max(10, $tenant->plan?->max_products ?? 50)),
                    'orders_count' => rand(1, 30),
                    'storage_kb' => rand(1000, 50000),
                    'db_size_kb' => rand(5000, 100000),
                    'collected_at' => now(),
                ],
            );
        }
    }
}

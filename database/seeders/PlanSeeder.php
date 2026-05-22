<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'price' => 0,
                'max_users' => 2,
                'max_storage' => 100,
                'max_warehouses' => 1,
                'max_categories' => 5,
                'max_products' => 25,
                'features' => ['basic'],
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'price' => 29,
                'max_users' => 10,
                'max_storage' => 1024,
                'max_warehouses' => 10,
                'max_categories' => 50,
                'max_products' => 500,
                'features' => ['advanced', 'api_access', 'custom_domain'],
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'price' => 99,
                'max_users' => null,
                'max_storage' => null,
                'max_warehouses' => null,
                'max_categories' => null,
                'max_products' => null,
                'features' => ['all', 'api_access', 'custom_domain', 'white_label'],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }

    }
}

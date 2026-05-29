<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\PlanFeature;
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
                '_features' => ['basic'],
            ],
            [
                'name' => 'Growth',
                'slug' => 'growth',
                'price' => 15,
                'max_users' => 5,
                'max_storage' => 500,
                'max_warehouses' => 3,
                'max_categories' => 20,
                'max_products' => 200,
                '_features' => ['advanced', 'api_access'],
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
                '_features' => ['advanced', 'api_access', 'custom_domain'],
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
                '_features' => ['all', 'api_access', 'custom_domain', 'white_label'],
            ],
        ];

        foreach ($plans as $planData) {
            $features = $planData['_features'] ?? [];
            unset($planData['_features']);

            $planModel = Plan::updateOrCreate(['slug' => $planData['slug']], $planData);

            if (! empty($features)) {
                $featureKeys = is_array($features) ? $features : [$features];
                foreach ($featureKeys as $feature) {
                    PlanFeature::updateOrCreate([
                        'plan_id' => $planModel->id,
                        'feature_key' => $feature,
                    ], [
                        'is_enabled' => true,
                    ]);
                }
            }
        }

    }
}

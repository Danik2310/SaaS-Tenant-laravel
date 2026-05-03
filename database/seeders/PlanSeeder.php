<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Plan;

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
                'features' => json_encode(['basic']),
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'price' => 29,
                'max_users' => 10,
                'features' => json_encode(['advanced']),
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'price' => 99,
                'max_users' => null,
                'features' => json_encode(['all']),
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }

    }

}

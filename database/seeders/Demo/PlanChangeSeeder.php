<?php

namespace Database\Seeders\Demo;

use App\Models\Plan;
use App\Tenants\Contracts\TenantManagerInterface;
use Illuminate\Database\Seeder;

class PlanChangeSeeder extends Seeder
{
    public function run(): void
    {
        $tenantManager = app(TenantManagerInterface::class);

        $proPlan = Plan::where('slug', 'pro')->first();

        if (! $proPlan) {
            return;
        }

        $demoTenant = $tenantManager->provision([
            'name' => 'Demo Plan Change',
            'email' => 'demo-planchange@example.com',
            'domain' => 'demo-planchange.localhost',
            'plan' => 'free',
            'status' => 'Active',
        ]);

        $tenantManager->changePlan($demoTenant, $proPlan);
    }
}

<?php

namespace Database\Seeders\Demo;

use App\States\TenantStateManager;
use App\Tenants\Contracts\TenantManagerInterface;
use Illuminate\Database\Seeder;

class TenantStateSeeder extends Seeder
{
    public function run(): void
    {
        $tenantManager = app(TenantManagerInterface::class);

        $demoTenant = $tenantManager->provision([
            'name' => 'Demo State Transitions',
            'email' => 'demo-state@example.com',
            'domain' => 'demo-state.localhost',
            'plan' => 'free',
            'status' => 'Active',
        ]);

        TenantStateManager::transitionTo($demoTenant, 'Suspended');
        TenantStateManager::transitionTo($demoTenant, 'Active');
        TenantStateManager::transitionTo($demoTenant, 'Cancelled');
        TenantStateManager::transitionTo($demoTenant, 'Deleted');
        $demoTenant->delete();
    }
}

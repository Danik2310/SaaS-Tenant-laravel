<?php

namespace Database\Seeders\Demo;

use App\Contracts\TenantManagerInterface;
use App\States\TenantStateManager;
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

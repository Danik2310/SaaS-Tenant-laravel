<?php

namespace Database\Seeders;

use Database\Seeders\Central\ActivityLogSeeder;
use Database\Seeders\Central\GlobalSettingSeeder;
use Database\Seeders\Central\PaymentMethodSeeder;
use Database\Seeders\Central\StaffSeeder;
use Database\Seeders\Central\TenantResourceUsageSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Foundation: permissions, plans, roles
            CentralRolePermissionSeeder::class,
            PlanSeeder::class,

            // Central admin staff (must be after permissions)
            StaffSeeder::class,

            // Central scaffolding: settings, payment methods
            GlobalSettingSeeder::class,
            PaymentMethodSeeder::class,

            // Tenants: provisions each tenant with DB, migrations, permissions, and sample data
            TenantSeeder::class,

            // Post-tenant central data: usage metrics, activity log
            TenantResourceUsageSeeder::class,
            ActivityLogSeeder::class,
        ]);
    }
}

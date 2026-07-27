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
    /**
     * ┌─────────────────────────────────────────────────────────┐
     * │ Seeding Pipeline                                         │
     * ├─────────────────────────────────────────────────────────┤
     * │ 1. CentralRolePermissionSeeder — Spatie roles/permissions│
     * │    for admin guard (manage tenants, staff, plans, etc.)  │
     * │ 2. PlanSeeder — Free, Growth, Pro, Enterprise plans      │
     * │ 3. StaffSeeder — 4 AdminUser records                     │
     * │    ⚡ Requires ADMIN_PASSWORD env variable               │
     * │ 4. GlobalSettingSeeder — system-level settings           │
     * │ 5. PaymentMethodSeeder — Stripe, PayPal, Transfer        │
     * │ 6. TenantSeeder — provisions N tenants, each triggers:  │
     * │      a. CreateDatabase / MigrateDatabase / SeedDatabase │
     * │         (TenantUserRolePermissionSeeder — tenant roles)  │
     * │      b. TenantDataSeeder → User, Customer, Product, etc. │
     * │ 7. SubscriptionPaymentSeeder — payment history for plans  │
     * │ 8. TenantResourceUsageSeeder — usage metrics             │
     * │ 9. ActivityLogSeeder — sample activity log entries       │
     * └─────────────────────────────────────────────────────────┘
     */
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

            // Subscription payment history (needs tenants + subscriptions)
            SubscriptionPaymentSeeder::class,

            // Post-tenant central data: usage metrics, activity log
            TenantResourceUsageSeeder::class,
            ActivityLogSeeder::class,
        ]);
    }
}

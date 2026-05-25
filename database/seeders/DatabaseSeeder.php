<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Central seeders
        $this->call([
            // ensure permissions and roles exist before creating users
            CentralRolePermissionSeeder::class,
            PlanSeeder::class,
            AdminUserSeeder::class,
            TenantSeeder::class,
        ]);
    }
}

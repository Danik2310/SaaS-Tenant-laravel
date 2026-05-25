<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = [
            [
                'id' => 'empresa-abc',
                'name' => 'Empresa ABC',
                'email' => 'contacto@empresaabc.com',
                'domain' => 'empresaabc.localhost',
            ],
            [
                'id' => 'tienda-xyz',
                'name' => 'Tienda XYZ',
                'email' => 'admin@tiendaxyz.com',
                'domain' => 'tiendaxyz.localhost',
            ],
            [
                'id' => 'consultoria-123',
                'name' => 'Consultoría 123',
                'email' => 'info@consultoria123.com',
                'domain' => 'consultoria123.localhost',
            ],
        ];

        $defaultPlan = Plan::where('slug', 'free')->first();

        foreach ($tenants as $tenantData) {
            $tenant = Tenant::updateOrCreate(
                ['id' => $tenantData['id']],
                [
                    'name' => $tenantData['name'],
                    'email' => $tenantData['email'],
                    'status' => 'Active',
                    'plan_id' => $defaultPlan?->id,
                ]
            );

            if (empty($tenant->reference_id)) {
                $tenant->reference_id = Tenant::generateReferenceId();
                $tenant->save();
            }

            $tenant->domains()->updateOrCreate(
                ['domain' => $tenantData['domain']],
                ['domain' => $tenantData['domain']]
            );

            if (! $tenant->database()->manager()->databaseExists($tenant->database()->getName())) {
                $tenant->database()->manager()->createDatabase($tenant);
            }

            // Run tenant migrations using the native tenancy command.
            // Internally uses tenancy()->runForMultiple() + DatabaseTenancyBootstrapper
            // to switch to the tenant DB connection with the correct migration path.
            Artisan::call('tenants:migrate-fresh', [
                '--tenants' => [$tenant->id],
            ]);

            // Seed inside tenant context for Spatie permissions + admin user
            $tenant->run(function () use ($tenantData) {
                Artisan::call('db:seed', [
                    '--class' => TenantRolePermissionSeeder::class,
                    '--force' => true,
                ]);

                User::updateOrCreate(
                    ['email' => $tenantData['email']],
                    [
                        'name' => 'Admin '.$tenantData['name'],
                        'password' => bcrypt('password'),
                        'email_verified_at' => now(),
                    ]
                )->assignRole('tenant-admin');
            });
        }
    }
}

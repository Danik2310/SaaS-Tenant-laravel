<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear algunos tenants de ejemplo
        $tenants = [
            [
                'name' => 'Empresa ABC',
                'email' => 'contacto@empresaabc.com',
                'domain' => 'empresaabc.localhost',
            ],
            [
                'name' => 'Tienda XYZ',
                'email' => 'admin@tiendaxyz.com',
                'domain' => 'tiendaxyz.localhost',
            ],
            [
                'name' => 'Consultoría 123',
                'email' => 'info@consultoria123.com',
                'domain' => 'consultoria123.localhost',
            ],
        ];

        $defaultPlan = Plan::where('slug', 'free')->first();

        foreach ($tenants as $tenantData) {
            $tenant = Tenant::updateOrCreate(
                ['email' => $tenantData['email']],
                [
                    'name' => $tenantData['name'],
                    'status' => 'Active',
                    'plan_id' => $defaultPlan?->id,
                ]
            );

            $tenant->domains()->updateOrCreate(
                ['domain' => $tenantData['domain']],
                ['domain' => $tenantData['domain']]
            );

            // Crear credenciales de base de datos para el tenant
            $tenant->database()->makeCredentials();
            $tenant->save();
        }
    }
}
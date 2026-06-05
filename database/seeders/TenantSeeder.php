<?php

namespace Database\Seeders;

use App\Contracts\TenantManagerInterface;
use App\Models\Plan;
use App\Models\Tenant;
use Database\Seeders\Tenant\TenantDataSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $tenantManager = app(TenantManagerInterface::class);

        $plans = Plan::pluck('slug', 'slug')->toArray();

        $configs = $this->buildAllConfigs($plans);

        foreach ($configs as $config) {
            $existing = Tenant::withTrashed()->find($config['id']);

            if ($existing) {
                $this->handleExisting($existing, $config, $tenantManager);

                continue;
            }

            $this->createFresh($config, $tenantManager);
        }
    }

    private function buildAllConfigs(array $plans): array
    {
        $original = [
            [
                'id' => 'empresa-abc',
                'name' => 'Empresa ABC',
                'email' => 'contacto@empresaabc.com',
                'domain' => 'empresaabc.localhost',
                'plan' => 'free',
                'status' => 'Active',
            ],
            [
                'id' => 'tienda-xyz',
                'name' => 'Tienda XYZ',
                'email' => 'admin@tiendaxyz.com',
                'domain' => 'tiendaxyz.localhost',
                'plan' => 'pro',
                'status' => 'Active',
            ],
            [
                'id' => 'consultoria-123',
                'name' => 'Consultoría 123',
                'email' => 'info@consultoria123.com',
                'domain' => 'consultoria123.localhost',
                'plan' => 'enterprise',
                'status' => 'Active',
            ],
        ];

        $plansList = array_keys($plans);
        $statusCycle = ['Active', 'Active', 'Active', 'Active', 'Suspended', 'Trial', 'Deleted', 'Active', 'Active', 'Active'];

        $companies = [
            'Tech Solutions MX', 'Distribuidora del Norte', 'Farmacias Unidas',
            'Taller Mecánico Rápido', 'Papelería La Pluma', 'Restaurante El Sazón',
            'Constructora Horizonte', 'Consultoría Estratégica', 'Clínica Dental Care',
            'Abarrotes Don Juan', 'Mueblería El Hogar', 'Ferretería El Tornillo',
            'Lavandería EcoClean', 'Estética Glamour', 'Tienda Deportiva Fit',
            'Panadería El Trigo', 'Veterinaria Mascotas', 'Óptica Visión Clara',
            'Florería Los Girasoles', 'Cafetería El Aroma', 'Gimnasio PowerFit',
            'Librería El Saber', 'Zapatería Cómoda', 'Pastelería Dulce Tentación',
            'Taller de Costura Creativa', 'Agencia de Viajes Aventura', 'Estudio de Yoga Zen',
        ];

        $additional = [];

        foreach ($companies as $i => $company) {
            $slug = strtolower(str_replace(
                [' ', 'á', 'é', 'í', 'ó', 'ú', 'ñ', 'ç'],
                ['-', 'a', 'e', 'i', 'o', 'u', 'n', 'c'],
                $company
            ));
            $slug = preg_replace('/[^a-z0-9-]/', '', $slug);

            $additional[] = [
                'id' => $slug,
                'name' => $company,
                'email' => 'contacto@'.$slug.'.com',
                'domain' => $slug.'.localhost',
                'plan' => $plansList[array_rand($plansList)],
                'status' => $statusCycle[$i % count($statusCycle)],
            ];
        }

        return array_merge($original, $additional);
    }

    private function handleExisting(Tenant $tenant, array $config, TenantManagerInterface $tenantManager): void
    {
        $tenant->update(['status' => 'Active']);
        if ($tenant->trashed()) {
            $tenant->restore();
        }

        $tenant->load('plan');
        $plan = $tenant->plan;

        $this->seedTenantData($tenant, $config['email'], $plan);

        if ($config['status'] === 'Suspended') {
            $tenantManager->suspend($tenant);
        }
        if ($config['status'] === 'Deleted') {
            $tenantManager->delete($tenant);
        }
    }

    private function createFresh(array $config, TenantManagerInterface $tenantManager): void
    {
        $databaseName = config('tenancy.database.prefix').$config['id'].config('tenancy.database.suffix');
        DB::statement("DROP DATABASE IF EXISTS `{$databaseName}`");

        $data = [
            'id' => $config['id'],
            'name' => $config['name'],
            'email' => $config['email'],
            'status' => $config['status'] === 'Trial' ? 'Trial' : 'Active',
            'trial_ends_at' => $config['status'] === 'Trial' ? now()->addDays(14) : null,
            'domain' => $config['domain'],
            'plan' => $config['plan'],
        ];

        $tenant = $tenantManager->provision($data);
        $tenant->load('plan');
        $plan = $tenant->plan;

        $this->seedTenantData($tenant, $config['email'], $plan);

        if ($config['status'] === 'Suspended') {
            $tenantManager->suspend($tenant);
        }
        if ($config['status'] === 'Deleted') {
            $tenantManager->delete($tenant);
        }
    }

    private function seedTenantData(Tenant $tenant, string $email, ?Plan $plan): void
    {
        $tenant->run(function () use ($email, $plan) {
            app(TenantDataSeeder::class)->run($email, $plan);
        });
    }
}

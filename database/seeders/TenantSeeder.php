<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Tenant;
use App\Tenants\Contracts\TenantManagerInterface;
use App\Tenants\Contracts\TenantRepositoryInterface;
use Database\Seeders\Tenant\TenantDataSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedReferenceIdCounter();

        if (config('queue.default') !== 'sync') {
            throw new \RuntimeException(
                'TenantSeeder requires QUEUE_CONNECTION=sync. '
                .'The TenantCreated job pipeline (CreateDatabase, MigrateDatabase, SeedDatabase) '
                .'runs asynchronously and will not complete before seedTenantData() is called.'
            );
        }

        if (config('cache.default') === 'file') {
            throw new \RuntimeException(
                'TenantSeeder requires CACHE_DRIVER=array or redis. '
                .'The "file" driver does not support Cache::lock() (needed for reference ID generation) '
                .'or Cache::tags() (needed by the CacheTenancyBootstrapper).'
            );
        }

        $tenantManager = app(TenantManagerInterface::class);

        $configs = $this->buildAllConfigs();

        if ($configs === []) {
            throw new \RuntimeException(
                'No demo tenants configured. Check config/seeding.php.'
            );
        }

        $availablePlans = Plan::pluck('slug', 'slug')->toArray();
        $missingPlans = array_diff(array_column($configs, 'plan'), array_keys($availablePlans));

        if ($missingPlans !== []) {
            throw new \RuntimeException(
                'Missing plans for TenantSeeder: '.implode(', ', array_unique($missingPlans))
                .'. Ensure PlanSeeder has run before TenantSeeder.'
            );
        }

        foreach ($configs as $config) {
            $existing = $this->resolveExisting($config);

            if ($existing) {
                $this->handleExisting($existing, $config, $tenantManager);

                continue;
            }

            $this->createFresh($config, $tenantManager);
        }
    }

    /**
     * Resolve an already-provisioned tenant for a seed config.
     *
     * The seed configs are keyed by a human-readable slug, but real tenants get
     * sequential ids (`TEN-######`) from TenantManager::provision(). Looking the
     * tenant up by that slug therefore never matches and re-seeding would try to
     * provision a duplicate (failing on "domain already in use"). Instead we
     * resolve through stable, unique keys: the primary domain first, then the
     * billing email as a fallback.
     */
    private function resolveExisting(array $config): ?Tenant
    {
        return app(TenantRepositoryInterface::class)->findByDomain($config['domain'])
            ?? Tenant::withTrashed()->where('email', $config['email'])->first();
    }

    private function buildAllConfigs(): array
    {
        $suffix = config('tenancy.tenant_domain_suffix');

        return array_map(
            fn (array $config): array => [
                'key' => $config['key'],
                'name' => $config['name'],
                'email' => $config['email'],
                'domain' => $config['domain'].'.'.$suffix,
                'plan' => $config['plan'],
                'status' => $config['status'],
            ],
            config('seeding.demo.tenants', [])
        );
    }

    private function handleExisting(Tenant $tenant, array $config, TenantManagerInterface $tenantManager): void
    {
        // Normalise the tenant back to a usable state through the lifecycle
        // facade so the StateManager transitions (and their events/cache flushes)
        // run consistently instead of poking the status column directly.
        if ($tenant->trashed()) {
            $tenantManager->restore($tenant);
        } elseif ($tenant->status !== 'Active') {
            $tenantManager->setStatus($tenant, 'Active');
        }

        // Domains are hard-deleted when TenantManager::delete() is called.
        // Recreate the primary domain if missing so the tenant is accessible.
        if ($tenant->domains()->count() === 0) {
            $tenant->domains()->create([
                'domain' => $config['domain'],
                'is_primary' => true,
            ]);
        }

        $tenant->load('plan');
        $plan = $tenant->plan;

        // Existing tenants are the only ones that need an explicit migration:
        // re-running the seeder is how deployed migrations reach them. In
        // tests, provisioning already produces a fully migrated schema, so
        // skipping this avoids redundant `tenants:migrate` invocations for
        // every seeded tenant.
        if (config('tenancy.seeder_migrate_existing')) {
            $tenantManager->migrateTenant($tenant);
        }

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
        // Id and database name are allocated by TenantManager::provision() (and
        // any orphan database from a previous failed run is dropped there by
        // ensureDatabaseAvailable()). We must NOT force the slug as the tenant id
        // or guess a database name here — doing so produced an unusable id and a
        // DROP against a database name that never matched.
        $data = [
            'name' => $config['name'],
            'email' => $config['email'],
            'status' => $config['status'] === 'Trial' ? 'Trial' : 'Active',
            'trial_ends_at' => $config['status'] === 'Trial' ? now()->addDays(14) : null,
            'domain' => $config['domain'],
            'plan' => $config['plan'],
        ];

        $tenant = $tenantManager->provision($data);

        // provision() dispatches TenantCreated, whose pipeline already creates
        // and migrates the database (real MigrateDatabase in production, a
        // cloned pre-migrated schema in tests). Migrating again here re-ran
        // every tenant migration for each seeded tenant and dominated test
        // runtime.
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
            app(TenantUserRolePermissionSeeder::class)->run();
            app(TenantDataSeeder::class)->run($email, $plan);
        });
    }

    private function seedReferenceIdCounter(): void
    {
        $date = now()->format('Ymd');
        $prefix = 'TEN-'.$date.'-';

        $max = Tenant::withTrashed()
            ->where('reference_id', 'like', $prefix.'%')
            ->pluck('reference_id')
            ->map(fn (string $id) => (int) substr($id, strlen($prefix)))
            ->max() ?? 0;

        if ($max > 0) {
            Cache::put("ref_id_counter:{$date}", $max, now()->addDays(2));
        }
    }
}

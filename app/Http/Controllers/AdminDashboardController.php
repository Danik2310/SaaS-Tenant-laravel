<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    /**
     * Show the admin dashboard
     */
    public function index()
    {
        return view('admin.dashboard');
    }

    /**
     * Get all tenants as JSON (for React)
     */
    public function tenants()
    {
        $tenants = Tenant::with('domains')->get()->map(function ($tenant) {
            return [
                'id' => $tenant->id,
                'name' => $tenant->name ?? 'N/A',
                'email' => $tenant->email ?? 'N/A',
                'domain' => $tenant->domains->first()?->domain ?? 'N/A',
                'status' => $tenant->status,
                'created_at' => $tenant->created_at,
                'updated_at' => $tenant->updated_at,
            ];
        });

        return response()->json(['tenants' => $tenants]);
    }

    /**
     * Get a single tenant details
     */
    public function showTenant($id)
    {
        $tenant = Tenant::with('domains')->findOrFail($id);

        return response()->json([
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name ?? 'N/A',
                'email' => $tenant->email ?? 'N/A',
                'domain' => $tenant->domains->first()?->domain ?? 'N/A',
                'status' => $tenant->status,
                'created_at' => $tenant->created_at,
                'updated_at' => $tenant->updated_at,
            ],
        ]);
    }

    /**
     * Create a new tenant
     */
    public function createTenant(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:tenants,email',
            'domain' => 'required|string|unique:domains,domain',
        ]);

        $tenant = Tenant::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'status' => 'Active',
        ]);

        $tenant->domains()->create([
            'domain' => $validated['domain'],
        ]);

        $tenant->database()->makeCredentials();
        $tenant->save();

        // Run migrations automatically
        $migrationResult = $tenant->run(function () {
            $exit = \Artisan::call('migrate', ['--force' => true]);
            return [
                'output' => \Artisan::output(),
                'exit' => $exit,
            ];
        });

        // Seed basic roles and permissions for tenant
        try {
            $tenant->run(function () {
                \Artisan::call('db:seed', [
                    '--class' => \Database\Seeders\TenantRolePermissionSeeder::class,
                    '--force' => true,
                ]);
            });
        } catch (\Exception $e) {
            \Log::warning('Failed to seed tenant: ' . $e->getMessage(), ['tenant_id' => $tenant->id]);
        }

        return response()->json([
            'message' => 'Tenant created successfully',
            'tenant' => [
                'id' => $tenant->id,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'domain' => $validated['domain'],
                'status' => 'Active',
                'migrated' => $migrationResult['exit'] === 0,
            ],
        ], 201);
    }

    /**
     * Update tenant info
     */
    public function updateTenant($id, Request $request)
    {
        $tenant = Tenant::findOrFail($id);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:tenants,email,' . $id,
            'status' => 'nullable|in:Active,Suspended',
        ]);

        // Actualizar campos usando el modelo (ahora corregido para guardar en columnas)
        if (isset($validated['name'])) {
            $tenant->name = $validated['name'];
        }
        if (isset($validated['email'])) {
            $tenant->email = $validated['email'];
        }
        if (isset($validated['status'])) {
            $tenant->status = $validated['status'];
        }

        $tenant->save();

        // Recargar para asegurar que lee desde las columnas correctas
        $tenant->refresh();

        return response()->json([
            'message' => 'Tenant updated successfully',
            'tenant' => $tenant,
        ]);
    }

    /**
     * Delete a tenant
     */
    public function deleteTenant($id)
    {
        $tenant = Tenant::findOrFail($id);

        $tenant->delete();

        return response()->json(['message' => 'Tenant deleted successfully']);
    }

    /**
     * Return database credentials/config for a tenant
     */
    public function tenantDatabase($id)
    {
        $tenant = Tenant::findOrFail($id);
        $db = $tenant->database();

        return response()->json([
            'database' => [
                'name' => $db->getName(),
                // REMOVED: username, password - SECURITY RISK
                'connection' => $db->connection(),
                'host' => config('database.connections.tenant.host'),
                'port' => config('database.connections.tenant.port'),
            ],
        ]);
    }

    /**
     * Run migrations for a specific tenant
     */
    public function migrateTenant($id)
    {
        try {
            $tenant = Tenant::findOrFail($id);

            // Switch to the tenant context and run migrations
            $result = $tenant->run(function () {
                $exit = \Artisan::call('migrate', [
                    '--force' => true,
                ]);
                return [
                    'output' => \Artisan::output(),
                    'exit' => $exit,
                ];
            });

            return response()->json([
                'message' => 'Migrations executed',
                'output' => $result['output'],
                'exit' => $result['exit'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Migration failed',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Staff management placeholder
     */
    public function staff()
    {
        // Placeholder: return empty staff list for now
        return response()->json(['staff' => []]);
    }

    /**
     * Plans / settings placeholder
     */
    public function plans()
    {
        // Placeholder: return basic plans data
        return response()->json(['plans' => [
            ['id' => 'free', 'name' => 'Free', 'price' => 0],
            ['id' => 'pro', 'name' => 'Pro', 'price' => 2999],
        ]]);
    }

    /**
     * Impersonate a tenant (God Mode) - store tenant id in session and return domain
     */
    public function impersonateTenant(Request $request)
    {
        $validated = $request->validate([
            'tenant_id' => 'required|string|exists:tenants,id',
        ]);

        $tenant = Tenant::with('domains')->find($validated['tenant_id']);
        $domain = $tenant->domains->first()?->domain ?? null;

        if (!$domain) {
            return response()->json(['message' => 'Tenant has no domain configured'], 422);
        }

        // Mark impersonation in session
        session(['impersonate_tenant' => $tenant->id]);

        return response()->json(['message' => 'Impersonation started', 'domain' => $domain]);
    }

    public function stopImpersonation()
    {
        session()->forget('impersonate_tenant');
        return response()->json(['message' => 'Impersonation stopped']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected array $createdTenantDbNames = [];

    /**
     * ✅ Prueba: crear un tenant y asignarle un dominio.
     */
    public function test_tenant_and_domain_can_be_created()
    {
        $tenant = $this->createTestTenant();

        $this->assertInstanceOf(Tenant::class, $tenant);
        $this->assertNotEmpty($tenant->database()->getName());
        $this->createdTenantDbNames[] = $tenant->database()->getName();

        $domain = $tenant->domains()->create([
            'domain' => 'test.localhost',
        ]);

        $this->assertNotNull($domain->id);
        $this->assertDatabaseHas('domains', [
            'domain' => 'test.localhost',
            'tenant_id' => $tenant->id,
        ]);

        $this->assertCount(1, $tenant->domains);
    }

    /**
     * ✅ Prueba: ejecutar migraciones para el tenant.
     */
    public function test_tenant_migrations_run_successfully()
    {
        // Create a fresh tenant that hasn't had migrations run
        $tenant = Tenant::create([
            'id' => 'test-migration-'.uniqid(),
        ]);
        $tenant->database()->makeCredentials();
        $tenant->database()->manager()->createDatabase($tenant);
        $tenant->save();

        $this->createdTenantDbNames[] = $tenant->database()->getName();

        // run the migration endpoint (bypass middleware for test simplicity)
        $response = $this->withoutMiddleware()->post("/admin/api/tenants/{$tenant->id}/migrate");

        $response->assertStatus(200);
        $this->assertStringContainsString('Migrations executed', $response->json('message'));
        // Note: output might be empty if migrations are already up to date
        $this->assertArrayHasKey('output', $response->json());
        $this->assertArrayHasKey('exit', $response->json());
    }

    /**
     * ✅ Prueba: toggle status of tenant
     */
    public function test_tenant_toggle_active_status()
    {
        $role = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']);
        $permission = Permission::firstOrCreate(['name' => 'manage tenants', 'guard_name' => 'admin']);
        $role->givePermissionTo($permission);
        $user = AdminUser::factory()->create();
        $user->assignRole('super-admin');
        $this->actingAs($user, 'admin');

        $tenant = $this->createTestTenant();
        $this->createdTenantDbNames[] = $tenant->database()->getName();

        $originalStatus = $tenant->status;

        // Toggle the status
        $response = $this->put("/admin/api/tenants/{$tenant->id}", [
            'status' => $originalStatus === 'Active' ? 'Suspended' : 'Active',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Tenant updated successfully']);

        // Verify the tenant was updated in database
        $tenant->refresh();
        $this->assertEquals($originalStatus === 'Active' ? 'Suspended' : 'Active', $tenant->status);
    }

    /**
     * 🧪 Prueba específica: verificar que la API actualiza correctamente el status de Active a Suspended
     * y que el cambio se refleja en la base de datos (no se mantiene como Active)
     */
    public function test_tenant_status_update_api_changes_database_from_active_to_suspended()
    {
        // Crear tenant con status Active (estado inicial)
        $tenant = Tenant::create([
            'id' => 'test-status-update-'.uniqid(),
            'name' => 'Test Status Update Tenant',
            'email' => 'status@test.com',
            'status' => 'Active', // 👈 Estado inicial Active
        ]);

        $this->createdTenantDbNames[] = $tenant->database()->getName();

        // Verificar estado inicial
        $this->assertEquals('Active', $tenant->status, 'El estado inicial debe ser Active');

        $role = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']);
        $permission = Permission::firstOrCreate(['name' => 'manage tenants', 'guard_name' => 'admin']);
        $role->givePermissionTo($permission);
        $user = AdminUser::factory()->create();
        $user->assignRole('super-admin');
        $this->actingAs($user, 'admin');

        // Hacer llamada API para suspender (cambiar a Suspended)
        $response = $this->put("/admin/api/tenants/{$tenant->id}", [
            'status' => 'Suspended', // 👈 Intentamos cambiar a Suspended
        ]);

        // Verificar respuesta exitosa
        $response->assertStatus(200)
            ->assertJson(['message' => 'Tenant updated successfully']);

        // Consultar el tenant desde la base de datos nuevamente
        $tenant->refresh();

        // Verificar que el status cambió correctamente (NO se mantuvo como Active)
        $this->assertEquals('Suspended', $tenant->status,
            'El status debe cambiar de Active a Suspended después de la actualización API');

        // Verificación adicional: asegurar que no se mantuvo el estado original
        $this->assertNotEquals('Active', $tenant->status,
            'El status NO debe mantenerse como Active después de intentar suspender');
    }

    /**
     * 🧪 Prueba específica: verificar que la API actualiza correctamente el status de Suspended a Active
     * y que el cambio se refleja en la base de datos (no se mantiene como Suspended)
     */
    public function test_tenant_status_update_api_changes_database_from_suspended_to_active()
    {
        // Crear tenant con status Suspended (estado inicial)
        $tenant = Tenant::create([
            'id' => 'test-status-activate-'.uniqid(),
            'name' => 'Test Status Activate Tenant',
            'email' => 'activate@test.com',
            'status' => 'Suspended', // 👈 Estado inicial Suspended
        ]);

        $this->createdTenantDbNames[] = $tenant->database()->getName();

        // Verificar estado inicial
        $this->assertEquals('Suspended', $tenant->status, 'El estado inicial debe ser Suspended');

        $role = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']);
        $permission = Permission::firstOrCreate(['name' => 'manage tenants', 'guard_name' => 'admin']);
        $role->givePermissionTo($permission);
        $user = AdminUser::factory()->create();
        $user->assignRole('super-admin');
        $this->actingAs($user, 'admin');

        // Hacer llamada API para activar (cambiar a Active)
        $response = $this->put("/admin/api/tenants/{$tenant->id}", [
            'status' => 'Active', // 👈 Intentamos cambiar a Active
        ]);

        // Verificar respuesta exitosa
        $response->assertStatus(200)
            ->assertJson(['message' => 'Tenant updated successfully']);

        // Consultar el tenant desde la base de datos nuevamente
        $tenant->refresh();

        // Verificar que el status cambió correctamente (NO se mantuvo como Suspended)
        $this->assertEquals('Active', $tenant->status,
            'El status debe cambiar de Suspended a Active después de la actualización API');

        // Verificación adicional: asegurar que no se mantuvo el estado original
        $this->assertNotEquals('Suspended', $tenant->status,
            'El status NO debe mantenerse como Suspended después de intentar activar');
    }

    /**
     * 🧪 Prueba: verificar que la API falla cuando NO se tiene el permiso 'manage tenants'
     * (simulando el comportamiento real de la UI)
     */
    public function test_tenant_status_update_fails_without_manage_tenants_permission()
    {
        // Crear un usuario sin permisos
        $user = AdminUser::factory()->create();
        $this->actingAs($user, 'admin');

        // Crear tenant
        $tenant = $this->createTestTenant();
        $this->createdTenantDbNames[] = $tenant->database()->getName();

        // Intentar actualizar status SIN bypass de middleware
        $response = $this->put("/admin/api/tenants/{$tenant->id}", [
            'status' => 'Suspended',
        ]);

        // Debería fallar con 403 Forbidden porque no tiene permisos
        $response->assertStatus(403);

        // Verificar que el status NO cambió en la base de datos
        $tenant->refresh();
        $this->assertEquals('Active', $tenant->status, 'El status no debe cambiar sin permisos');
    }

    /**
     * 🧪 Prueba: verificar que la API funciona correctamente cuando el usuario SÍ tiene el permiso 'manage tenants'
     * (simulando el comportamiento esperado en la UI con usuario autenticado)
     */
    public function test_tenant_status_update_works_with_manage_tenants_permission()
    {
        // Crear el rol y permiso si no existen
        $role = Role::firstOrCreate(
            ['name' => 'super-admin', 'guard_name' => 'admin'],
            ['description' => 'Administrador con acceso completo']
        );
        $permission = Permission::firstOrCreate(
            ['name' => 'manage tenants', 'guard_name' => 'admin']
        );
        $role->givePermissionTo($permission);

        // Crear un usuario con el rol super-admin (que tiene permisos)
        $user = AdminUser::factory()->create();
        $user->assignRole('super-admin');
        $this->actingAs($user, 'admin');

        // Crear tenant
        $tenant = $this->createTestTenant();
        $this->createdTenantDbNames[] = $tenant->database()->getName();

        // Asegurar que tenga status Active
        $tenant->update(['status' => 'Active']);
        $tenant->refresh();

        // Verificar estado inicial
        $this->assertEquals('Active', $tenant->status);

        // Intentar actualizar status CON permisos
        $response = $this->put("/admin/api/tenants/{$tenant->id}", [
            'status' => 'Suspended',
        ]);

        // Debería funcionar con 200 OK
        $response->assertStatus(200)
            ->assertJson(['message' => 'Tenant updated successfully']);

        // Verificar que el status SÍ cambió en la base de datos
        $tenant->refresh();
        $this->assertEquals('Suspended', $tenant->status, 'El status debe cambiar con permisos');
    }

    /**
     * ✅ Prueba: obtener credenciales de la base de datos vía API
     */
    public function test_tenant_database_endpoint_returns_credentials()
    {
        $tenant = $this->createTestTenant();
        $this->createdTenantDbNames[] = $tenant->database()->getName();

        $response = $this->withoutMiddleware()->get("/admin/api/tenants/{$tenant->id}/database");
        $response->assertStatus(200)
            ->assertJsonStructure(['database' => ['name', 'connection']]);
    }

    /**
     * ✅ Prueba: verificar que la base de datos del tenant existe.
     */
    public function test_tenant_database_is_created()
    {
        $tenant = $this->createTestTenant();
        $dbName = $tenant->database()->getName();
        $this->createdTenantDbNames[] = $dbName;

        // Una conexión debe existir y ser accesible
        $this->assertNotEmpty($dbName);

        // Verificar que pueda conectarse
        // (no es null cuando la base de datos está configurada)
        $config = $tenant->database()->connection();
        $this->assertEquals($dbName, $config['database']);
    }

    /**
     * ✅ Prueba: creación y relaciones de datos de clientes y productos.
     */
    public function test_tenant_customer_and_product_data_flow()
    {
        $tenant = $this->createTestTenant();
        $this->createdTenantDbNames[] = $tenant->database()->getName();

        // Acceder a la BD del tenant
        $this->initializeTenant($tenant);

        // Insertar una categoría
        $categoryId = DB::connection('tenant')->table('categories')->insertGetId([
            'name' => 'Electrónicos',
            'slug' => 'electronicos',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insertar un producto vinculado a la categoría
        $productId = DB::connection('tenant')->table('products')->insertGetId([
            'name' => 'Laptop',
            'sku' => 'laptop-001',
            'price' => 999.99,
            'category_id' => $categoryId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insertar un cliente
        $customerId = DB::connection('tenant')->table('customers')->insertGetId([
            'name' => 'Juan Pérez',
            'email' => 'juan@example.com',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Verificar que los registros existen en sus tablas respectivas
        $this->assertDatabaseHas('categories', ['id' => $categoryId, 'name' => 'Electrónicos'], 'tenant');
        $this->assertDatabaseHas('products', ['id' => $productId, 'name' => 'Laptop'], 'tenant');
        $this->assertDatabaseHas('customers', ['id' => $customerId, 'name' => 'Juan Pérez'], 'tenant');

        // Verificar las relaciones mediante JOIN
        $product = DB::connection('tenant')
            ->table('products')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->where('products.id', $productId)
            ->first();

        $this->assertNotNull($product);
        $this->assertEquals('electronicos', $product->slug);
    }

    protected function tearDown(): void
    {
        foreach ($this->createdTenantDbNames as $name) {
            try {
                DB::statement("DROP DATABASE IF EXISTS `$name`");
            } catch (\Exception $e) {
                // ignorar si falla
            }
        }

        parent::tearDown();
    }
}

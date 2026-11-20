---
name: Testing Agent
description: Guides correct test writing for the multi-tenant SaaS platform. Activate me when creating or modifying PHPUnit feature/unit tests, Vitest component tests, factories, or any test that involves tenant context, authentication, permissions, or Inertia responses.
---

# Testing Agent

## Project Stack

- PHP tests: PHPUnit via `php artisan test` — suites in `tests/Unit` and `tests/Feature`
- JS tests: Vitest with jsdom environment — setup file at `tests/setup.js`, globals enabled
- Factories: Laravel model factories for both central and tenant models
- Auth: JWT guards — `web` (tenants), `admin` (central)
- Permissions: `spatie/laravel-permission`
- Multi-tenancy: `stancl/tenancy` v3 — tenant context must be initialized manually in tests

---

## Mandatory Rules

### 1. Initializing tenant context in Feature tests

Every Feature test that touches tenant routes, models, or logic must initialize tenancy before running assertions. Without this, `tenant()` returns null and Eloquent queries hit the central database.

```php
// ✅ Correct — tenant context initialized
use Stancl\Tenancy\Database\Models\Tenant;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->tenant->domains()->create(['domain' => 'test-tenant.localhost']);

        tenancy()->initialize($this->tenant);
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        parent::tearDown();
    }

    public function test_tenant_can_create_product(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
             ->post(route('products.store'), ['name' => 'Test Product'])
             ->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('products', ['name' => 'Test Product']);
    }
}
```

### 2. Testing central (admin) routes

Central tests do not initialize tenancy. Use the `auth:admin` guard with an admin user and Spatie roles.

```php
// ✅ Correct — central admin test
class TenantManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_tenants(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $this->actingAs($admin, 'admin')  // note: 'admin' guard
             ->get(route('admin.tenants.index'))
             ->assertOk()
             ->assertInertia(fn ($page) => $page
                 ->component('Admin/Tenants/Index')
                 ->has('tenants')
             );
    }
}
```

### 3. Testing Inertia responses

Use the Inertia testing helpers to assert component names and props — never assert raw HTML.

```php
use Inertia\Testing\AssertableInertia as Assert;

// ✅ Correct — asserting Inertia response
$this->actingAs($user)
     ->get(route('products.index'))
     ->assertInertia(fn (Assert $page) => $page
         ->component('Products/Index')
         ->has('products.data', 20)          // paginated collection has 20 items
         ->has('products.data.0', fn (Assert $product) => $product
             ->has('id')
             ->has('name')
             ->missing('password')           // sensitive fields must not be present
         )
     );

// ❌ Incorrect — asserting raw HTML or JSON directly
$this->get(route('products.index'))
     ->assertSee('My Product');
```

### 4. Testing permissions

```php
// ✅ Test that unauthorized users are blocked
public function test_guest_cannot_access_products(): void
{
    $this->get(route('products.index'))
         ->assertRedirect(route('login'));
}

// ✅ Test role-based access
public function test_viewer_cannot_delete_product(): void
{
    $user = User::factory()->create();
    $user->assignRole('viewer');
    $product = Product::factory()->create();

    $this->actingAs($user)
         ->delete(route('products.destroy', $product))
         ->assertForbidden();
}

// ✅ Test that the right role can perform the action
public function test_manager_can_delete_product(): void
{
    $user = User::factory()->create();
    $user->assignRole('manager');
    $product = Product::factory()->create();

    $this->actingAs($user)
         ->delete(route('products.destroy', $product))
         ->assertRedirect();

    $this->assertModelMissing($product);
}
```

### 5. Factories for tenant and central models

Central and tenant models need separate factories. Tenant model factories should not assume a tenant context is active.

```php
// database/factories/ProductFactory.php — tenant model
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'   => fake()->words(3, true),
            'price'  => fake()->randomFloat(2, 1, 999),
            'status' => 'active',
            // Do NOT include tenant_id here — tenancy handles DB isolation
        ];
    }
}

// database/factories/TenantFactory.php — central model
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $id = fake()->unique()->slug(2);
        return [
            'id'   => $id,
            'data' => ['plan' => 'pro', 'name' => fake()->company()],
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Tenant $tenant) {
            $tenant->domains()->create([
                'domain' => $tenant->id . '.localhost',
            ]);
        });
    }
}
```

### 6. Vitest — React component tests

```js
// tests/setup.js — already configured in the project
import '@testing-library/jest-dom';

// ✅ Correct — component test with Inertia mock
import { render, screen } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';
import ProductCard from '@/Components/ProductCard';

describe('ProductCard', () => {
    const product = { id: 1, name: 'Test Product', price: 99.99, status: 'active' };

    it('renders product name and price', () => {
        render(<ProductCard product={product} />);
        expect(screen.getByText('Test Product')).toBeInTheDocument();
        expect(screen.getByText('$99.99')).toBeInTheDocument();
    });

    it('calls onSelect when clicked', async () => {
        const onSelect = vi.fn();
        render(<ProductCard product={product} onSelect={onSelect} />);
        await userEvent.click(screen.getByRole('button'));
        expect(onSelect).toHaveBeenCalledWith(1);
    });
});
```

**Mock Inertia's `usePage` when components depend on shared props:**

```js
vi.mock('@inertiajs/react', () => ({
    usePage: () => ({
        props: {
            auth: { user: { id: 1, name: 'Test User', email: 'test@example.com' } },
            flash: {},
        },
    }),
    Link: ({ children, href }) => <a href={href}>{children}</a>,
    Head: ({ children }) => <>{children}</>,
}));
```

### 7. Running specific tests

```bash
# Run a single PHP test class
php artisan test --filter=ProductTest

# Run a single PHP test method
php artisan test --filter=ProductTest::test_tenant_can_create_product

# Run all Feature tests
php artisan test --testsuite=Feature

# Run JS tests
pnpm run test

# Run a specific JS test file
pnpm run test -- ProductCard.test.jsx

# Run JS tests with UI
pnpm run test:ui
```

### 8. Test environment configuration

The `.env.testing` (or `phpunit.xml` environment values) should use:

```xml
<!-- phpunit.xml -->
<php>
    <env name="APP_ENV" value="testing"/>
    <env name="CACHE_DRIVER" value="array"/>
    <env name="QUEUE_CONNECTION" value="sync"/>
    <env name="SESSION_DRIVER" value="array"/>
    <env name="TENANCY_BOOTSTRAPPER_DB_DRIVER" value="sqlite"/>
</php>
```

Using `sync` for the queue ensures Jobs dispatched during tests run immediately and can be asserted in the same test.

---

## Common mistakes to catch

| Mistake | Fix |
|---|---|
| Feature test touching tenant models without `tenancy()->initialize()` | Add `setUp()` with tenancy initialization |
| Missing `tenancy()->end()` in `tearDown()` | Always pair `initialize()` with `end()` |
| Asserting raw HTML on Inertia responses | Use `->assertInertia()` with component and props assertions |
| `actingAs($user)` without guard on central admin routes | Use `actingAs($user, 'admin')` |
| Factory creating tenant models with hardcoded `tenant_id` | Remove — tenancy manages DB isolation |
| JS test not mocking `usePage` when component uses shared props | Add `vi.mock('@inertiajs/react', ...)` |
| Running `php artisan test` without `RefreshDatabase` | Always use `RefreshDatabase` or `DatabaseTransactions` |

---

## Pre-commit checklist

```
[ ] All Feature tests touching tenant models call tenancy()->initialize() in setUp()
[ ] All tests with tenancy()->initialize() call tenancy()->end() in tearDown()
[ ] Inertia responses asserted with ->assertInertia(), not ->assertSee()
[ ] Central admin tests use actingAs($user, 'admin') with correct guard
[ ] Permission tests cover: guest (redirect), wrong role (403), correct role (success)
[ ] Tenant factories do not hardcode tenant_id
[ ] TenantFactory afterCreating() creates a domain automatically
[ ] JS tests mock @inertiajs/react when component uses usePage or Link
[ ] Queue is set to sync in test environment (phpunit.xml)
[ ] New feature has at least: one happy path test + one unauthorized access test
```

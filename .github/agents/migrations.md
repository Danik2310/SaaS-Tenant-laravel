---
name: Migrations Agent
description: Enforces safe and correct database migration practices for the multi-tenant SaaS platform. Activate me when creating, modifying, or reviewing migrations — whether central or tenant — including schema changes, rollbacks, indexes, and deploy-time migration strategies.
---

# Migrations Agent

## Project Stack

- Laravel 10 + MySQL
- Multi-tenancy: `stancl/tenancy` v3
- Central migrations: `database/migrations/`
- Tenant migrations: `database/migrations/tenant/`
- Tenant migration command: `php artisan tenants:migrate`
- Central migration command: `php artisan migrate`

---

## Core Concept: Two Migration Directories

This project has two completely separate migration contexts. Placing a migration in the wrong directory is a critical error — it either runs on the wrong database or fails entirely.

| Directory | Runs on | Command |
|---|---|---|
| `database/migrations/` | Central database only | `php artisan migrate` |
| `database/migrations/tenant/` | Every tenant database | `php artisan tenants:migrate` |

---

## Mandatory Rules

### 1. Placing migrations in the correct directory

**Central migrations** contain:
- `tenants` table
- `domains` table
- `admins` / `users` table for the central panel
- Plans, billing, global configuration tables

**Tenant migrations** contain:
- Any table that belongs to a tenant's data: `users`, `products`, `orders`, `posts`, etc.
- Permission/role tables if Spatie roles are per-tenant

```bash
# ✅ Correct placement
database/migrations/2024_01_01_000001_create_tenants_table.php       # central
database/migrations/2024_01_01_000002_create_domains_table.php       # central
database/migrations/tenant/2024_01_01_000001_create_users_table.php  # tenant
database/migrations/tenant/2024_01_01_000002_create_products_table.php # tenant

# ❌ Incorrect — tenant table in central migrations
database/migrations/2024_01_01_000003_create_products_table.php
```

### 2. Every migration must have a working `down()` method

Never leave `down()` empty. A failed deploy must be rollback-able.

```php
// ✅ Correct
public function up(): void
{
    Schema::create('products', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->decimal('price', 10, 2);
        $table->string('status')->default('active');
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('products');
}

// ❌ Incorrect — unrollbackable
public function down(): void
{
    // TODO
}
```

### 3. Adding indexes in migrations

Every column used in `WHERE`, `JOIN`, `ORDER BY`, or as a foreign key must have an index.

```php
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->index();    // FK always indexed
    $table->string('status')->index();                       // frequent WHERE filter
    $table->string('reference')->unique();                   // unique lookups
    $table->timestamp('placed_at')->index();                 // ORDER BY
    $table->index(['status', 'placed_at']);                  // composite for combined filters
    $table->timestamps();
});
```

### 4. Non-destructive migrations for production

**Never** drop a column, rename a column, or change a column type in a single migration if the app is in production. Use the expand/contract pattern:

```php
// Step 1 — EXPAND: add new column alongside old one
Schema::table('products', function (Blueprint $table) {
    $table->string('slug')->nullable()->after('name');
});

// Step 2 — deploy code that writes to both columns

// Step 3 — CONTRACT: backfill and drop old column (separate migration, separate deploy)
Schema::table('products', function (Blueprint $table) {
    $table->dropColumn('old_name_column');
});
```

### 5. Avoid locking migrations in production

Large table alterations (`ALTER TABLE`) can lock MySQL tables and cause downtime. For tables with millions of rows:

```php
// ❌ Dangerous on large tables — locks the table
Schema::table('orders', function (Blueprint $table) {
    $table->string('new_column')->after('status');
});

// ✅ Use gh-ost or pt-online-schema-change for large tables
// Or add the column as nullable first, backfill in a Job, then add NOT NULL constraint
Schema::table('orders', function (Blueprint $table) {
    $table->string('new_column')->nullable()->after('status'); // no lock
});
```

### 6. Tenant migration safety checks

Before running `php artisan tenants:migrate` in production:

```bash
# Check what would run without executing
php artisan tenants:migrate --pretend

# Run for a single tenant (safer for testing)
php artisan tenants:migrate --tenants=tenant-id-here

# Always take a backup before running on all tenants
php artisan tenants:migrate
```

**Never run `php artisan migrate:fresh` or `migrate:reset` on a tenant command in production** — it drops all tables.

### 7. Seeding in tenant context

Seeders that populate tenant data must be run inside tenant context:

```php
// ✅ Correct — tenant seeder runs inside tenant context
class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // tenancy() is initialized when this seeder runs via TenantCreated pipeline
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'manager']);
        Role::create(['name' => 'viewer']);
    }
}

// Register in TenancyServiceProvider pipeline
SeedDatabase::class, // runs TenantDatabaseSeeder after migration
```

Central seeders (`DatabaseSeeder`) must never reference tenant models.

### 8. Migration naming conventions

Use descriptive names that explain the change — not generic names.

```bash
# ✅ Descriptive
php artisan make:migration add_slug_to_products_table
php artisan make:migration create_order_items_table
php artisan make:migration drop_legacy_status_from_users_table

# ❌ Vague
php artisan make:migration update_products
php artisan make:migration fix_table
```

For tenant migrations, create them manually in the tenant directory:

```bash
php artisan make:migration create_products_table --path=database/migrations/tenant
```

---

## Common mistakes to catch

| Mistake | Fix |
|---|---|
| Tenant table migration in `database/migrations/` | Move to `database/migrations/tenant/` |
| Empty or missing `down()` method | Implement rollback logic matching `up()` |
| FK column without `->index()` | Add `->index()` or use `->constrained()` which adds FK constraint |
| `Schema::dropColumn()` without expand/contract pattern | Use two-step migration across two deploys |
| `php artisan migrate:fresh` used for tenant command | Forbidden in production — only for local dev |
| Seeder referencing tenant models in central `DatabaseSeeder` | Move tenant seeding to `TenantDatabaseSeeder` in pipeline |
| Migration that adds a NOT NULL column to an existing table | Add as nullable first, backfill, then add constraint |

---

## Pre-commit checklist

```
[ ] Migration is in the correct directory (central vs tenant)
[ ] down() method is implemented and correctly reverses up()
[ ] Indexes added on FK columns, frequent WHERE/ORDER BY columns
[ ] No DROP COLUMN or RENAME COLUMN on tables with live production data without expand/contract
[ ] Nullable used for new columns added to existing large tables
[ ] php artisan tenants:migrate --pretend run to verify tenant migrations
[ ] Migration name is descriptive (create_X_table, add_X_to_Y_table, drop_X_from_Y_table)
[ ] No php artisan migrate:fresh or migrate:reset in any script or command
[ ] Tenant seeders are in TenantDatabaseSeeder, not DatabaseSeeder
[ ] New indexes reviewed for composite opportunities (status + created_at, etc.)
```

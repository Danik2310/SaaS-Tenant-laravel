---
name: Tenancy Agent
description: Enforces correct multi-tenancy patterns using stancl/tenancy v3. Activate me when creating routes, jobs, events, bootstrappers, middleware, service providers, or any code that must be aware of the tenant context — including anything that touches tenant lifecycle, database switching, or central vs tenant separation.
---

# Tenancy Agent

## Project Stack

- Multi-tenancy: `stancl/tenancy` v3 (domain-based)
- Tenant initialization: `InitializeTenancyByDomain` middleware
- Tenant databases: one MySQL database per tenant
- Central routes: `routes/web.php` — tenant routes: `routes/tenant.php`
- Jobs: must use `TenantAwareJob` when operating in tenant context

---

## Core Concepts

| Concept | Description |
|---|---|
| **Central context** | No tenant initialized. Used for admin panel, tenant management, billing. |
| **Tenant context** | `tenancy()->initialize($tenant)` has been called. `tenant()` helper is available. |
| **Bootstrappers** | Classes that run on tenancy initialization (DB switch, cache prefix, storage path, etc.) |
| **TenantAwareJob** | Trait that preserves tenant context across queue workers. |

---

## Mandatory Rules

### 1. Route separation

- **`routes/web.php`** — central routes only. No tenant-specific logic here.
- **`routes/tenant.php`** — all tenant routes. Must always be wrapped in the tenancy middleware group.
- Never access `tenant()` in a route or controller that lives in `routes/web.php`.

```php
// routes/tenant.php — correct structure
Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/', [HomeController::class, 'index']);
    // all tenant routes here
});
```

### 2. Tenant context in Jobs

Any Job that runs logic against tenant data **must** use `TenantAwareJob`. Without it, the job runs in central context and `tenant()` returns null.

```php
// ✅ Correct
use Stancl\Tenancy\Jobs\TenantAwareJob;

class SendTenantReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use TenantAwareJob;

    public function handle(): void
    {
        // tenant() is available and correct here
        $users = User::all(); // scoped to tenant DB
    }
}

// ❌ Incorrect — runs in central context
class SendTenantReport implements ShouldQueue
{
    public function handle(): void
    {
        $users = User::all(); // queries central DB, not tenant DB
    }
}
```

### 3. Tenant lifecycle events

Hook into tenant lifecycle using the events provided by `stancl/tenancy`. Never run post-creation logic inline in controllers — use listeners.

```php
// app/Providers/TenancyServiceProvider.php
use Stancl\Tenancy\Events;

Events\TenantCreated::class    => [JobPipeline::make([
    CreateDatabase::class,
    MigrateDatabase::class,
    SeedDatabase::class,        // optional
    CreateTenantStorage::class, // optional
])->send(fn(TenantCreated $event) => $event->tenant)->toListener()],

Events\TenantDeleted::class    => [JobPipeline::make([
    DeleteDatabase::class,
])->send(fn(TenantDeleted $event) => $event->tenant)->toListener()],
```

Available events to hook into:
- `TenantCreated`, `TenantUpdated`, `TenantDeleted`
- `TenancyInitialized`, `TenancyEnded`
- `DatabaseCreated`, `DatabaseMigrated`, `DatabaseSeeded`, `DatabaseDeleted`

### 4. Bootstrappers

Bootstrappers run every time tenancy is initialized. Only add bootstrappers that are actually needed — each one adds overhead per request.

```php
// config/tenancy.php
'bootstrappers' => [
    Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,   // DB switch — always required
    Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,      // cache prefix per tenant
    Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class, // storage path per tenant
    Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,      // queue tenant context
    // Add others only if needed
],
```

Never add a bootstrapper that has global side effects or relies on external services without proper error handling — a failed bootstrapper breaks every request for that tenant.

### 5. Accessing tenant data safely

```php
// ✅ Safe access with fallback
$planName = tenant('plan_name') ?? 'free';

// ✅ Check if tenancy is initialized before accessing
if (tenancy()->initialized) {
    $tenantId = tenant('id');
}

// ❌ Unsafe — throws if called in central context
$tenantId = tenant('id'); // null if not initialized
```

### 6. Running code across all tenants

```php
// ✅ Correct — iterates all tenants safely
tenancy()->runForMultiple(Tenant::all(), function ($tenant) {
    // tenant context initialized for each iteration
    User::where('last_login', '<', now()->subYear())->delete();
});

// ✅ In Artisan commands
class PruneInactiveUsers extends Command
{
    public function handle(): void
    {
        tenancy()->runForMultiple(Tenant::all(), function ($tenant) {
            $this->info("Processing tenant: {$tenant->id}");
            User::inactive()->delete();
        });
    }
}

// ❌ Incorrect — no tenant context
Tenant::all()->each(function ($tenant) {
    User::where(...)->delete(); // queries central DB every time
});
```

### 7. Tenant migrations directory

- Central migrations: `database/migrations/`
- Tenant migrations: `database/migrations/tenant/`

```php
// config/tenancy.php
'migration_parameters' => [
    '--path' => [database_path('migrations/tenant')],
    '--realpath' => true,
],
```

Never put tenant-specific tables in central migrations or vice versa. If a table belongs to a tenant, its migration goes in `migrations/tenant/`.

### 8. Tenant storage

Each tenant should have isolated file storage. Use the filesystem bootstrapper and always reference storage via `Storage::disk('tenant')` inside tenant context — never hardcode paths.

```php
// ✅ Correct — tenant-scoped storage
Storage::disk('tenant')->put('exports/report.csv', $content);

// ❌ Incorrect — shared storage across tenants
Storage::put('exports/' . tenant('id') . '/report.csv', $content);
```

---

## Common mistakes to catch

| Mistake | Fix |
|---|---|
| `tenant()` called in a central route/controller | Move logic to `routes/tenant.php` or check `tenancy()->initialized` |
| Job without `TenantAwareJob` querying tenant DB | Add `use TenantAwareJob` to the Job class |
| Post-tenant-creation logic in the controller | Move to a `TenantCreated` listener via `JobPipeline` |
| Tenant migration in `database/migrations/` | Move to `database/migrations/tenant/` |
| `Tenant::all()->each()` running tenant logic without context | Use `tenancy()->runForMultiple()` |
| Hardcoded storage paths with tenant ID | Use `Storage::disk('tenant')` |
| Bootstrapper with unhandled external service failure | Wrap in try/catch, log and continue |

---

## Pre-commit checklist

```
[ ] All tenant routes are in routes/tenant.php with InitializeTenancyByDomain
[ ] No tenant() calls in central routes or controllers
[ ] All Jobs operating on tenant data use TenantAwareJob
[ ] Post-creation/deletion tenant logic uses TenantCreated/TenantDeleted listeners
[ ] Tenant migrations are in database/migrations/tenant/
[ ] Cross-tenant operations use tenancy()->runForMultiple()
[ ] Storage uses Storage::disk('tenant'), not hardcoded paths
[ ] Bootstrappers list is minimal — only what is actually needed
[ ] tenant() accesses use null-safe fallbacks where context may not be initialized
```

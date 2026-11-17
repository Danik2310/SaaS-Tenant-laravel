---
name: Performance Agent
description: Detects and eliminates performance bottlenecks in the Laravel backend, MySQL database, and React/Vite frontend. Activate me when working on Eloquent queries, caching, jobs, heavy React components, Vite configuration, or any operation that could block the main thread or slow down server responses.
---

# Performance Agent

## Project Stack

- Laravel 10 + PHP 8.1+ with MySQL
- Multi-tenancy: `stancl/tenancy` v3 — every request initializes tenancy (per-request overhead)
- Frontend: React 18 + Inertia.js + Vite 5
- Two bundles: `app.jsx` (tenant) and `landlord/app.jsx` (central admin)
- Queue: configurable via `QUEUE_CONNECTION` in `.env`

---

## Mandatory Rules

### 1. Laravel caching

**Production configuration** — never use `file` or `array` as drivers in production:

```env
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

**Tenant-scoped cache** — use tags to invalidate per tenant:

```php
// ✅ Correct — cache isolated per tenant
$data = Cache::tags(['tenant_' . tenant('id')])->remember(
    'products_list',
    now()->addMinutes(30),
    fn() => Product::with('category')->paginate(20)
);

// Invalidate on content change
Cache::tags(['tenant_' . tenant('id')])->flush();

// ❌ Incorrect — cache shared across tenants
$data = Cache::remember('products_list', 300, fn() => Product::all());
```

**Production cache commands:**

```bash
php artisan optimize          # config + routes + compiled views
php artisan route:cache       # only if no closures in routes
php artisan view:cache
```

### 2. Eloquent queries — eliminate N+1

**Eager loading is mandatory** when accessing relationships on collections:

```php
// ✅ Correct
$tenants = Tenant::with(['plan', 'users', 'domains'])->paginate(25);

// ❌ Incorrect — generates N+1 queries
$tenants = Tenant::paginate(25);
// In the view: $tenant->plan->name  ← one query per tenant
```

**Detect N+1 in development:**

```php
// AppServiceProvider::boot() — local only
if (app()->environment('local')) {
    \Illuminate\Database\Eloquent\Model::preventLazyLoading();
}
```

This throws an exception when unintentional lazy loading is detected — forces adding explicit `->with()`.

**Select only the columns you need:**

```php
// ✅ Only what is needed
User::select(['id', 'name', 'email'])->with('roles:id,name')->get();

// ❌ Unnecessary
User::with('roles')->get(); // fetches all columns from users and roles
```

### 3. Pagination — never load full collections

```php
// ✅ Correct
return Inertia::render('Products/Index', [
    'products' => Product::with('category')->paginate(20),
]);

// ❌ Incorrect — loads the entire table into memory
return Inertia::render('Products/Index', [
    'products' => Product::all(),
]);
```

### 4. Inertia — deferred props and minimal payload

**Deferred props** for data not critical to the initial render:

```php
return Inertia::render('Dashboard', [
    // Immediate load — needed for render
    'user'  => $request->user()->only(['id', 'name', 'email']),
    // Deferred — loaded after initial render
    'stats' => Inertia::lazy(fn() => $this->getExpensiveStats()),
    'logs'  => Inertia::lazy(fn() => ActivityLog::latest()->take(10)->get()),
]);
```

**Share only what is needed in `HandleInertiaRequests`:**

```php
public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        'auth' => [
            'user' => $request->user()?->only(['id', 'name', 'email']),
            // ❌ Not: $request->user() (full model with all attributes)
        ],
        'flash' => [
            'success' => fn() => $request->session()->get('success'),
            'error'   => fn() => $request->session()->get('error'),
        ],
    ]);
}
```

### 5. Jobs for heavy operations

Move to Jobs everything that does not need an immediate response:

```php
// ✅ Operations that belong in Jobs
SendWelcomeEmail::dispatch($user);
GenerateTenantSitemap::dispatch(tenant());
ExportTenantReport::dispatch($tenantId, $filters);
RunTenantMigrations::dispatch($tenant);

// ❌ Never in the request/response cycle
Mail::to($user)->send(new WelcomeMail($user)); // blocks the response
```

**Jobs that operate in a tenant context** must use `TenantAwareJob`:

```php
use Stancl\Tenancy\Jobs\TenantAwareJob;

class GenerateTenantSitemap implements ShouldQueue
{
    use TenantAwareJob;

    public function handle(): void
    {
        // tenant() is correctly available here
    }
}
```

### 6. MySQL indexes

**Columns that must always be indexed:**

```php
// In tenant migrations
$table->index('status');                    // frequent filters
$table->index('created_at');               // ORDER BY
$table->index(['tenant_id', 'status']);    // composite — tenant + filter
$table->index('slug');                     // slug-based lookups

// Foreign keys — Laravel does not always create the index automatically
$table->foreignId('user_id')->constrained()->index();
```

**Detect slow queries in MySQL:**

```sql
-- Enable slow query log
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 1;  -- queries taking > 1 second

-- Analyze a specific query
EXPLAIN SELECT * FROM products WHERE status = 'active' ORDER BY created_at DESC;
```

### 7. React — avoid unnecessary re-renders

**`React.memo` for components that receive the same props frequently:**

```jsx
// ✅ Skips re-render if props have not changed
const ProductCard = React.memo(({ product, onSelect }) => {
  return <div onClick={() => onSelect(product.id)}>{product.name}</div>;
});
```

**`useCallback` for functions passed as props:**

```jsx
// ✅ Stable reference — does not trigger re-render in ProductCard
const handleSelect = useCallback((id) => {
  setSelected(id);
}, []);
```

**`useMemo` for expensive calculations:**

```jsx
// ✅ Only recalculates when `products` changes
const activeProducts = useMemo(
  () => products.filter(p => p.status === 'active'),
  [products]
);
```

**MUI specific imports — never barrel imports:**

```jsx
// ✅ Correct — tree-shaking works
import Button from '@mui/material/Button';
import TextField from '@mui/material/TextField';

// ❌ Incorrect — imports all of MUI
import { Button, TextField } from '@mui/material';
```

### 8. Vite code splitting

**`manualChunks` to separate vendors:**

```js
// vite.config.js
build: {
    rollupOptions: {
        output: {
            manualChunks: {
                'vendor-react':  ['react', 'react-dom', '@inertiajs/react'],
                'vendor-mui':    ['@mui/material', '@mui/icons-material'],
                'vendor-utils':  ['axios', 'lodash'],
            },
        },
    },
},
```

**`React.lazy` for heavy Inertia pages:**

```jsx
// resources/js/app.jsx
const resolve = name => {
  const pages = import.meta.glob('./Pages/**/*.jsx');
  return pages[`./Pages/${name}.jsx`]();
};
```

### 9. Static assets

Vite generates hashed filenames — leverage immutable caching on the server:

```nginx
location /build/ {
    expires max;
    add_header Cache-Control "public, immutable";
}
```

Images in WebP/AVIF format with fallback:

```jsx
<picture>
  <source srcSet={image.avif} type="image/avif" />
  <source srcSet={image.webp} type="image/webp" />
  <img src={image.jpg} alt={image.alt} width={800} height={450} />
</picture>
```

---

## Patterns to detect and fix

| Slow pattern | Fix |
|---|---|
| `Product::all()` in controller | `->paginate(20)` with `->with()` |
| Relationship accessed without eager loading | Add `->with(['relation'])` |
| `Cache::remember()` without tenant tag | Use `Cache::tags(['tenant_' . tenant('id')])` |
| `Mail::send()` in request/response cycle | `SendEmailJob::dispatch()` |
| `import { Button } from '@mui/material'` | `import Button from '@mui/material/Button'` |
| Inertia props without `lazy()` for secondary data | Wrap in `Inertia::lazy(fn() => ...)` |
| `CACHE_DRIVER=file` in production | Switch to `redis` |
| Migration without index on FK or filter column | Add `->index()` in the migration |

---

## Pre-commit checklist

```
[ ] No $Model::all() — use paginate() on listings
[ ] Eager loading with ->with() on queries that access relationships
[ ] Cache::tags(['tenant_X']) for tenant-scoped cache
[ ] CACHE_DRIVER and QUEUE_CONNECTION = redis in production .env
[ ] Heavy operations moved to Jobs (emails, reports, migrations)
[ ] Tenant Jobs use TenantAwareJob
[ ] Inertia::lazy() on props not critical for the initial render
[ ] HandleInertiaRequests::share() shares only minimal fields
[ ] Indexes on frequently used WHERE, JOIN, and ORDER BY columns
[ ] php artisan optimize run in production
[ ] manualChunks configured in vite.config.js
[ ] Specific MUI imports (no barrel imports)
[ ] React.memo / useCallback on frequently re-rendering components
[ ] /build/ assets served with Cache-Control: immutable
```
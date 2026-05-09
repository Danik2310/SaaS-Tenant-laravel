# AGENTS.md

This file is read on **every task**, no exceptions.
Sub-agents in `.opencode/agents/` extend these rules for specific contexts — read them when their scope applies.
When multiple agents apply to a task, apply all of them. Their rules are complementary, not exclusive.

---

## Stack

- **Backend**: Laravel 10, PHP 8.1+, MySQL
- **Frontend**: React 18 + Inertia.js (JSX), Tailwind CSS, MUI v5
- **Build**: Vite 5 (port 5174, `strictPort` enabled)
- **Multi-tenancy**: `stancl/tenancy` v3 (domain-based isolation)
- **Auth & Permissions**: Spatie Laravel Permission, Sanctum
- **Testing**: PHPUnit (PHP), Vitest (JS)

---

## Commands

```bash
# Dev server (Vite on port 5174)
npm run dev

# Build assets
npm run build

# JS tests (Vitest)
npm run test
npm run test:ui   # with UI

# PHP tests (PHPUnit)
php artisan test
php artisan test --filter=TestName

# Code style
./vendor/bin/pint

# Clear all caches
php artisan optimize:clear

# Rebuild optimized cache (production)
php artisan optimize

# Tenant migrations
php artisan tenants:migrate

# Regenerate API docs
php artisan scribe:generate
```

---

## Architecture

- **Single-page app** via Inertia.js — no Blade views rendered directly; React components in `resources/js/Pages/`
- **Two Vite entry points**: `resources/js/app.jsx` (tenant-facing), `resources/js/landlord/app.jsx` (central admin)
- **Multi-tenant** via `stancl/tenancy` v3 — tenant routes in `routes/tenant.php` use domain-based tenancy (`InitializeTenancyByDomain`)
- **Central/admin routes** in `routes/web.php` use `auth:admin` guard with Spatie permission middleware
- **Path alias**: `@/` maps to `resources/js/` (configured in both `jsconfig.json` and `vitest.config.js`)
- **Two migration directories**: `database/migrations/` (central DB), `database/migrations/tenant/` (per-tenant DB)

---

## Key Packages

- `stancl/tenancy` — multi-tenancy with domain isolation
- `inertiajs/inertia-laravel` — SPA-like routing
- `spatie/laravel-permission` — role/permission system
- `spatie/laravel-data` — data transfer objects
- `tightenco/ziggy` — routes available in JS via `@/ziggy-js` alias
- `knuckleswtf/scribe` — API documentation generator

---

## Testing Notes

- PHPUnit: `tests/Unit` and `tests/Feature` suites; testing env uses array cache, sync queue, no Telescope/Pulse
- Vitest: jsdom environment, setup file at `tests/setup.js`, globals enabled
- Run single PHP test: `php artisan test --filter=TestName`
- Every Feature test that touches tenant data **must** call `tenancy()->initialize($tenant)` in `setUp()` and `tenancy()->end()` in `tearDown()`

---

## DB & Env

- Copy `.env.example` to `.env`; generate key with `php artisan key:generate`
- Tenant databases are created per-tenant by `stancl/tenancy` — central DB is the default MySQL connection
- Migrate tenants: `php artisan tenants:migrate` (or via API endpoint `/admin/api/tenants/{id}/migrate`)
- Production drivers: `CACHE_DRIVER=redis`, `QUEUE_CONNECTION=redis`, `SESSION_DRIVER=redis`

---

## Sub-agents

Read the relevant agent before starting any task in its scope.

| Agent | File | Read when… |
|---|---|---|
| **Patterns** | `.opencode/agents/patterns.md` | Creating or modifying **any** PHP class, service, job, or middleware |
| **Tenancy** | `.opencode/agents/tenancy.md` | Any code touching tenant context, routes, jobs, or lifecycle events |
| **Security** | `.opencode/agents/security.md` | Routes, controllers, auth, validation, guards, or data exposure |
| **Performance** | `.opencode/agents/performance.md` | Eloquent queries, cache, jobs, Vite config, or React components |
| **Testing** | `.opencode/agents/testing.md` | Creating or modifying any test file |
| **API** | `.opencode/agents/api.md` | API routes, controllers, JSON responses, or Scribe annotations |
| **Migrations** | `.opencode/agents/migrations.md` | Creating or modifying any migration file |
| **Billing** | `.opencode/agents/billing.md` | Plan gates, feature limits, subscriptions, or trial logic |
| **SEO** | `.opencode/agents/seo.md` | Public-facing pages, meta tags, sitemap, or robots.txt |
| **Dashboard UI** | `.opencode/agents/dashboard-ui.md` | React components in `Pages/Dashboard/` or `Components/Dashboard/` |

---

## Design Patterns

These patterns are the **agreed vocabulary** for this codebase.
Full implementations with code examples are in `.opencode/agents/patterns.md`.

### Decision table — use this before writing any new class

| You are writing… | Use this pattern | Lives in |
|---|---|---|
| A feature that behaves differently per plan | **Strategy** | `app/Services/Strategies/` |
| A class that creates objects based on plan or type | **Factory Method** | `app/Factories/` |
| A multi-step tenant setup or onboarding flow | **Builder** | `app/Builders/` |
| A single entry point hiding complex subsystems | **Facade** | `app/Services/TenantManager.php` |
| A class that adds cache/log/throttle on top of another | **Decorator** | `app/Decorators/` |
| A tenant lifecycle side effect (created, suspended, deleted) | **Observer** | `app/Listeners/` via `TenancyServiceProvider` |
| A middleware that validates before passing to the next | **Chain of Responsibility** | `app/Http/Middleware/` |
| A tenant with multiple states (trial, active, suspended, cancelled) | **State** | `app/States/` |
| An admin operation that needs to be reversible | **Command** | `app/Commands/Domain/` |
| An onboarding with fixed structure but variable steps | **Template Method** | `app/Onboarding/` |
| An integration with an external service (payment, SMS, storage) | **Adapter** | `app/Adapters/` |

### Folder structure for patterns

```
app/
├── Adapters/           # Adapter — external service integrations
├── Builders/           # Builder — multi-step construction flows
├── Commands/
│   └── Domain/         # Command — reversible domain operations
├── Contracts/          # Interfaces for every multi-implementation pattern
├── Decorators/         # Decorator — wraps repositories/services
├── Events/             # Observer — domain events
├── Factories/          # Factory Method — object creation by plan/type
├── Listeners/          # Observer — event handlers
├── Onboarding/         # Template Method — onboarding flows
├── Services/
│   ├── Strategies/     # Strategy — plan-based behavior variants
│   └── TenantManager.php  # Facade — unified tenant operations
└── States/             # State — tenant lifecycle states
```

### Hard rules — always enforced

1. **No `if/else` chains on `$plan` or `$type`** — replace with Strategy resolved from a Factory.
   ```php
   // ❌ Never
   if ($plan === 'pro') { ... } elseif ($plan === 'enterprise') { ... }

   // ✅ Always
   $strategy = NotificationStrategyFactory::make(tenant('plan'));
   $strategy->send($user, $message);
   ```

2. **No tenant setup logic in controllers** — use `TenantBuilder` or `TenantManager`.
   ```php
   // ❌ Never
   public function store(Request $request) {
       $tenant = Tenant::create([...]);
       $tenant->domains()->create([...]);
       Artisan::call('tenants:migrate', [...]);
       // ... 40 more lines
   }

   // ✅ Always
   public function store(StoreTenantRequest $request) {
       $tenant = app(TenantManager::class)->provision($request->validated());
       return redirect()->route('admin.tenants.show', $tenant);
   }
   ```

3. **No post-creation side effects inline** — use Observer (Listener via `TenancyServiceProvider` event pipeline).

4. **Interfaces first** — every pattern with multiple implementations must have a contract in `app/Contracts/`. Never type-hint concrete classes in controllers or services.
   ```php
   // ❌ Never — type-hinting the concrete class
   public function __construct(private StripeAdapter $payment) {}

   // ✅ Always — type-hinting the interface
   public function __construct(private PaymentGatewayInterface $payment) {}
   ```

5. **Resolve via Factory or container** — never instantiate strategy/adapter/decorator with `new` in controllers.

6. **Name classes after their pattern** — `CachedProductRepository`, `NotificationStrategyFactory`, `TenantBuilder`, `StripeAdapter`. The pattern name in the class name makes intent immediately clear.

---

## Global code rules

These apply to every file, every task, regardless of the active sub-agent.

### PHP / Laravel

- All write operations (store, update, destroy) use a `FormRequest` with `authorize()` implemented.
- Forbidden: `$request->all()` without a validated `FormRequest`.
- Eager load relationships — `->with([...])` is required when accessing relations on collections.
- Use `->paginate()` on all listings. `Model::all()` is forbidden in controllers.
- Jobs that operate in tenant context must use `TenantAwareJob`.
- Cache always uses `Cache::tags(['tenant_' . tenant('id')])` for tenant-scoped data.

### React / Frontend

- Import MUI components individually: `import Button from '@mui/material/Button'` — no barrel imports.
- Forms use `useForm` from `@inertiajs/react` — not `useState` per field.
- All Grid items define all four breakpoints: `xs`, `sm`, `md`, `lg`.
- Every `<IconButton>` must have a `<Tooltip>`.

### General

- Run `composer audit` and `npm audit --audit-level=high` before every deploy.
- Run `php artisan optimize` in production after any deploy.
- Every new feature needs at minimum: one happy path test + one unauthorized access test.
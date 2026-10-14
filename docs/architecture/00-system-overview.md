# 00 — System Overview

> **Scope**: the whole system at a glance — stack, module map, the central/tenant
> split, module boundaries, and how the general layer is organised. Detail lives
> in the sibling docs (`01`–`08`).

---

## 1. What this is

A multi-tenant SaaS platform built as a **modular monolith**: Laravel 12 on the
back end, React 18 + Inertia.js on the front end, with **database-per-tenant**
isolation via `stancl/tenancy` v3. A single deployment serves many independent
tenants plus one central administration panel.

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 12, PHP 8.2+ |
| Frontend | React 18, Inertia.js v2, MUI v7, Material React Table v3, Tailwind CSS 3 |
| Build | Vite 5 (port 5174, `strictPort`) |
| Multi-tenancy | `stancl/tenancy` v3 — database-per-tenant, domain-based identification |
| Admin auth | JWT (cookie-borne) via a custom `jwt` guard driver |
| Tenant auth | Session (`web` guard) |
| API auth | Sanctum |
| Permissions | `spatie/laravel-permission` v6 (guards: `admin`, `web`) |
| Testing | PHPUnit 11 (PHP), Vitest 4 (JS) |
| API docs | Scribe v5 |

---

## 2. The two worlds: central and tenant

The single most important split in the system is **central vs tenant**.

| | **Central** | **Tenant** |
|---|---|---|
| Served on | `config('tenancy.central_domains')` (e.g. `saas-app.test`) | the tenant's own domain(s) |
| Database | the central DB (`tenants`, admin users, plans, subscriptions, payments) | the tenant's own DB (`tenant{id}`) |
| Routes | `routes/web.php` (`/`, `/register`, `/central/*`, `/admin/*`) | `routes/tenant.php` (all tenant-facing routes) |
| Auth guard | `admin` (JWT cookie) | `web` (session) |
| Front end entry | `resources/js/landlord/app.jsx` | `resources/js/app.jsx` |
| Purpose | manage tenants, plans, staff, billing, settings | the tenant's own products/inventory/billing |

Boundary enforcement at runtime:

- `App\Shared\Middleware\EnsureCentralDomain` (`central.domain`) **404s** any request to a central route that did not arrive on a central domain.
- `Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains` stops central hosts from hitting tenant routes.
- `App\Shared\Middleware\CheckTenantState` (`tenant.state`) gates tenant routes on the tenant's lifecycle state.
- Because central and tenant routes can share a URI (`/`, `/register`), `routes/web.php` registers its copies **domain-constrained** — see `01-request-lifecycle.md §Routing`.

---

## 3. Module map

Code is organised **by domain first, pattern second**. Patterns live *inside* a
module, never at the top level.

```
app/
├── Billing/     Plans, subscriptions, payments, gateways (Stripe, MercadoPago)
├── Plans/       Plan definitions, feature flags, feature gating
├── Products/    Tenant product / category / warehouse / inventory CRUD
├── Tenants/     Tenant lifecycle, provisioning, metrics, states, domains
├── Onboarding/  Template-method onboarding flows
├── Shared/      Cross-cutting: middleware, contracts, services, jobs, observers
├── Http/        App-wide HTTP: base Controller, auth controllers, requests, resources, kernel
├── Models/      Eloquent models (central + tenant)
├── Observers/   Tenant-domain observers (resource usage)
├── Providers/   Framework + tenancy providers
└── Console/     Console kernel
```

### Module boundaries (enforced by convention)

- A module **must not import concrete classes from another module**.
- Cross-module communication happens **only** via:
  1. interfaces in `app/Shared/Contracts/`,
  2. Laravel **events**, or
  3. **service-container** bindings.
- `app/Shared/` is the only module importable by everyone.
- Each module owns a `ServiceProvider` that registers its bindings, listeners,
  and commands — see `02-providers-and-container.md`.

### Design patterns

| Pattern | Where |
|---------|-------|
| Strategy | plan-based behaviour, permission prerequisites |
| Factory | payment-gateway selection, plan feature flags |
| Adapter | Stripe / MercadoPago gateways |
| State | tenant lifecycle (Trial, Active, Suspended, Deleted) |
| Observer | tenant events, activity log, resource-usage counters |
| Command | reversible admin operations (suspend, restore) |
| Decorator | `CachedTenantRepository` |
| Template Method | onboarding flows |
| Builder | multi-step tenant provisioning |
| Facade | `TenantManager` entry point |

---

## 4. Layout of the general layer

The "general functions" documented in this set are everything a domain module
builds on:

| Concern | Where | Doc |
|---------|-------|-----|
| Application bootstrap, kernels | `bootstrap/app.php`, `app/Http/Kernel.php`, `app/Console/Kernel.php` | `01` |
| Service providers & container bindings | `app/Providers/*`, `app/*/…ServiceProvider`, `config/app.php` | `02` |
| Multi-tenancy machinery | `app/Providers/TenancyServiceProvider.php`, `config/tenancy.php` | `03` |
| Authentication | `app/Shared/Middleware/*`, `app/Shared/Support/JwtCookie.php`, `app/Shared/Http/Controllers/Admin/Auth/*` | `04` |
| Permissions & plan gating | `app/Shared/Constants/PermissionNames.php`, `app/Shared/Services/*`, `app/Shared/Middleware/RequiresPlanFeature.php` | `05` |
| Impersonation (god mode) | `app/Shared/Support/Impersonation*`, `app/Shared/Middleware/EnsureImpersonationValid.php` | `06` |
| Jobs, events, observers | `app/Shared/Jobs/*`, `app/Shared/Observers/ActivityLogObserver.php`, `app/Observers/Tenant/*` | `07` |
| Console & schedule | `app/Console/Kernel.php`, `app/Shared/Console/Commands/*` | `08` |

---

## 5. Migration & database layout

- **Two migration directories**: `database/migrations/` (central) and `database/migrations/tenant/` (per-tenant, path configured in `config/tenancy.php`).
- **Dev dual connection**: `mysql` (default) and `mysql_central` both point at the same database locally — a shortcut that makes `stancl/tenancy`'s connection switching a no-op in development. See `03-multi-tenancy.md §Connections`.
- Central tables: `tenants`, `domains`, `admin_users`, `plans`, `subscriptions`, `subscription_payments`, `payment_methods`, `global_settings`, `activity_log`, permissions/roles.
- Tenant tables: users, customers, products, categories, warehouses, inventory movements, orders, settings.

---

## 6. Front end at a glance

- **SPA via Inertia** — no Blade views except the central login shell (`view('admin.login')`).
- **Two Vite entry points**: tenant (`resources/js/app.jsx`) and landlord/admin (`resources/js/landlord/app.jsx`).
- **Root component**: `App\Shared\Middleware\HandleInertiaRequests` shares `auth`, flash, and app-wide props with every Inertia response — see `01-request-lifecycle.md`.

The front end is documented in a later pass.

---

## 7. Where to go next

- Follow a request end to end: [`01-request-lifecycle.md`](01-request-lifecycle.md).
- Understand what is bound where: [`02-providers-and-container.md`](02-providers-and-container.md).
- Understand the tenancy machinery: [`03-multi-tenancy.md`](03-multi-tenancy.md).

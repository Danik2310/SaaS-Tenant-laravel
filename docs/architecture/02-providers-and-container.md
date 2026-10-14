# 02 — Providers & Container

> **Scope**: every service provider, the order they boot in, what each one binds
> into the container, and the contract → implementation map that lets modules
> talk to each other without importing concrete classes.
>
> **Out of scope**: the runtime behaviour of the services themselves (module
> docs) and the tenancy provider's event wiring in depth (`03`).

---

## 1. Why providers matter here

This is a **modular monolith** with an enforced rule: *a module must not import
concrete classes from another module*. The container is how that rule is kept —
modules register **interfaces** (`app/*/Contracts`) and depend on those, while
the owning module binds a concrete implementation.

There are two provider flavours:

- **Framework/core providers** in `app/Providers/` — lifecycle, routing, events,
  tenancy.
- **Module providers** (`app/<Module>/<Module>ServiceProvider.php`) — each module
  is self-registering.

---

## 2. Registration order

Declared in [`config/app.php`](../../config/app.php) (`'providers' => …`). Order
matters: cross-cutting providers load before the modules that consume their
bindings, and tenancy loads last.

| # | Provider | Kind | Registers |
|---|----------|------|-----------|
| 0 | `Spatie\Permission\…\PermissionServiceProvider` | package | Spatie's base bindings (later overridden) |
| 1 | [`App\Providers\AppServiceProvider`](../../app/Providers/AppServiceProvider.php) | core | Schema string length, lazy-loading policy |
| 2 | [`App\Providers\AuthServiceProvider`](../../app/Providers/AuthServiceProvider.php) | core | *(nothing — no policies by design)* |
| — | `BroadcastServiceProvider` | core | **disabled** (commented out in config) |
| 3 | [`App\Providers\EventServiceProvider`](../../app/Providers/EventServiceProvider.php) | core | Model observers, `Registered` listener |
| 4 | [`App\Shared\SharedServiceProvider`](../../app/Shared/SharedServiceProvider.php) | module | Shared contract bindings, commands, tenant-aware permission registrar |
| 5 | [`App\Shared\Providers\JwtServiceProvider`](../../app/Shared/Providers/JwtServiceProvider.php) | core | `jwt` auth guard driver |
| 6 | [`App\Tenants\TenantServiceProvider`](../../app/Tenants/TenantServiceProvider.php) | module | Tenant lifecycle bindings, commands |
| 7 | [`App\Plans\PlansServiceProvider`](../../app/Plans/PlansServiceProvider.php) | module | Public plan catalog binding |
| 8 | [`App\Billing\BillingServiceProvider`](../../app/Billing/BillingServiceProvider.php) | module | Payment gateway binding, listener, observer, commands |
| 9 | [`App\Products\ProductsServiceProvider`](../../app/Products/ProductsServiceProvider.php) | module | *(nothing — empty by design)* |
| 10 | [`App\Providers\RouteServiceProvider`](../../app/Providers/RouteServiceProvider.php) | core | Middleware aliases, rate limiters, route files |
| 11 | [`App\Providers\TenancyServiceProvider`](../../app/Providers/TenancyServiceProvider.php) | core | Tenancy events, tenant routes, middleware priority |

> **Enable broadcasting** by un-commenting `BroadcastServiceProvider` in
> `config/app.php` and running `php artisan install:broadcasting`.

---

## 3. Container bindings

### 3.1 Shared module — `SharedServiceProvider`

`register()`:

| Contract | Implementation | Lifetime |
|----------|----------------|----------|
| `RoleServiceInterface` | `RoleService` | singleton |
| `PermissionServiceInterface` | `PermissionService` | singleton |
| `ExportServiceInterface` | `ExportService` | singleton |
| `ExchangeRateServiceInterface` | `ExchangeRateService` | singleton |
| `LocationCatalog` | `DatabaseLocationCatalog` | singleton |
| `ServerDiskInfo` | `LocalServerDiskInfo` or `CloudServerDiskInfo` | singleton (driver from `SERVER_DISK_DRIVER`) |

`boot()`:

1. Registers the five `App\Shared\Console\Commands\*` commands.
2. **Replaces** Spatie's `PermissionRegistrar` with
   `TenantAwarePermissionRegistrar`, so the permission cache is scoped per
   tenant/central context. *(This is the reason Shared must boot before any
   permission check runs.)*

### 3.2 Tenants module — `TenantServiceProvider`

| Contract | Implementation | Lifetime |
|----------|----------------|----------|
| `TenantManagerInterface` | `TenantManager` | singleton (the façade) |
| `TenantRepositoryInterface` | `CachedTenantRepository(TenantRepository)` | singleton (decorator) |
| `TenantBuilderInterface` | `TenantBuilder` | **bind** (stateful per provision) |
| `DatabaseServiceInterface` | `DatabaseService` | bind |

The builder is deliberately **not** a singleton — see the comment in
`TenantManager::provision()` (`03-multi-tenancy.md §Provisioning`).

### 3.3 Billing module — `BillingServiceProvider`

`register()` binds `PaymentGatewayInterface` to a gateway resolved by
`PaymentGatewayFactory::fromTenant($tenant)`. The factory reads the current
tenant (guarding the "no tenant context" case) and instantiates a
`StripePaymentAdapter` / `MercadoPagoAdapter` on demand — the **Factory + Adapter**
patterns.

`boot()` wires `PlanChanged → HandlePlanChange`, observes
`SubscriptionPayment` for payment-cache invalidation, and registers the module's
commands.

### 3.4 Plans module — `PlansServiceProvider`

Binds `PublicPlanCatalogInterface → PublicPlanCatalog` (the marketing-facing plan
list). Plan feature flags/normalisation live in `Plans/Support`.

### 3.5 Products module — `ProductsServiceProvider`

Empty on purpose — no bindings, listeners or commands. Present so every module
is self-registering.

### 3.6 JWT driver — `JwtServiceProvider`

`boot()` extends the auth system with a `jwt` driver, resolving the signing
secret per guard (`jwt.guard_secrets.{guard}`, fallback `jwt.secret`). See
`04-authentication.md`.

---

## 4. Contract → implementation map (cross-module seams)

| Contract (`app/…/Contracts`) | Implementation | Bound by |
|------------------------------|----------------|----------|
| `Shared\Contracts\RoleServiceInterface` | `Shared\Services\RoleService` | Shared |
| `Shared\Contracts\PermissionServiceInterface` | `Shared\Services\PermissionService` | Shared |
| `Shared\Contracts\ExportServiceInterface` | `Shared\Services\ExportService` | Shared |
| `Shared\Contracts\ExchangeRateServiceInterface` | `Shared\Services\ExchangeRateService` | Shared |
| `Shared\Contracts\LocationCatalog` | `Shared\Locations\DatabaseLocationCatalog` | Shared |
| `Shared\Contracts\ServerDiskInfo` | `Shared\Services\{Local,Cloud}ServerDiskInfo` | Shared |
| `Shared\Contracts\PublicPlanCatalogInterface` | `Plans\Services\PublicPlanCatalog` | Plans |
| `Shared\Contracts\PermissionPrerequisiteStrategyInterface` | via `PermissionPrerequisiteStrategyFactory` | Shared |
| `Shared\Contracts\CommandInterface` | reversible admin operations | (module commands) |
| `Billing\Contracts\PaymentGatewayInterface` | `StripePaymentAdapter` / `MercadoPagoAdapter` | Billing |
| `Billing\Contracts\ResourceEnforcementInterface` | `Billing\Strategies\*ResourceStrategy` | Billing |
| `Tenants\Contracts\TenantManagerInterface` | `Tenants\TenantManager` | Tenants |
| `Tenants\Contracts\TenantBuilderInterface` | `Tenants\Builders\TenantBuilder` | Tenants |
| `Tenants\Contracts\TenantRepositoryInterface` | `Tenants\Decorators\CachedTenantRepository` | Tenants |
| `Tenants\Contracts\DatabaseServiceInterface` | `Tenants\Services\DatabaseService` | Tenants |

Rules:

- **Interfaces live in the consuming `Shared/Contracts`** when the seam is
  cross-module; module-local contracts stay in the module (`Billing/Contracts`).
- Bind **interfaces, never concretes**, across module boundaries.
- The resource-enforcement strategies are selected by
  `Billing\Factories\ResourceEnforcementFactory` (Strategy + Factory), keyed on the
  tenant's plan.

---

## 5. Observers, listeners & middleware aliases

Registered outside `register()`/`bind()`:

- **Observers** — `EventServiceProvider::boot()` (activity log on all models;
  resource-usage observers on tenant models). See `07-jobs-events-observers.md`.
- **Listeners** — `TenancyServiceProvider::bootEvents()` and
  `BillingServiceProvider::boot()`.
- **Middleware aliases & rate limiters** — `RouteServiceProvider::boot()`; the
  aliases are declared on `App\Http\Kernel`. See `01-request-lifecycle.md §2.3`.

---

## 6. Key decisions & invariants

- **Shared before modules.** `SharedServiceProvider` installs the tenant-aware
  `PermissionRegistrar`; every module that checks permissions depends on it.
- **Tenancy last.** `TenancyServiceProvider` registers tenant routes and events
  once the rest of the app is wired.
- **Stateful services are `bind`, not `singleton`.** `TenantBuilder` is resolved
  per call to avoid leaking tenant state across provisions.
- **Factories guard missing tenant context.** `BillingServiceProvider`'s gateway
  binding and the permission registrar both tolerate running centrally.
- **No policies.** Authorization is route middleware + Spatie, not Gate classes —
  see `05-permissions-and-plan-gating.md`.

---

## 7. Cross-references

- [`03-multi-tenancy.md`](03-multi-tenancy.md) — the tenancy provider in detail.
- [`04-authentication.md`](04-authentication.md) — the JWT driver.
- [`05-permissions-and-plan-gating.md`](05-permissions-and-plan-gating.md) — the permission registrar and plan strategies.
- [`07-jobs-events-observers.md`](07-jobs-events-observers.md) — listeners and observers.

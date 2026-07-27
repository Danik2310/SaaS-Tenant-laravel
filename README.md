# SaaS App

Multi-tenant SaaS platform built with Laravel 12 and React 18, using a **modular monolith** architecture with domain-driven organization. Powered by `stancl/tenancy` v3 for database-per-tenant isolation.

## Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 12, PHP 8.2+ |
| Frontend | React 18, Inertia.js v2, Tailwind CSS 3 |
| UI Components | MUI v7, Material React Table v3 |
| Auth & Permissions | Spatie Laravel Permission v6, Sanctum v4 |
| Multi-tenancy | stancl/tenancy v3 (database-per-tenant) |
| Build | Vite 5 (port 5174, strict) |
| Testing | PHPUnit 11 (PHP), Vitest 4 (JS) |
| API Docs | Scribe v5 |
| Charts | Recharts v3 |

## Prerequisites

- PHP 8.2+
- Node.js 18+
- MySQL 8.0+
- Composer, npm

## Setup

```bash
# Install dependencies
composer install
npm install

# Environment
cp .env.example .env
php artisan key:generate

# Configure DB credentials in .env, then:
php artisan migrate

# Seed the database (plans, tenants, payments, etc.)
php artisan db:seed
```

### Environment Variables

| Variable | Purpose | Default |
|----------|---------|---------|
| `ADMIN_EMAIL` | Admin login email | `admin@example.com` |
| `ADMIN_PASSWORD` | Admin login password | _(set in .env)_ |
| `TENANT_PASSWORD` | Default password for seeded tenants | _(set in .env)_ |
| `QUEUE_CONNECTION` | Must be `sync` for seeding | `sync` |
| `CACHE_DRIVER` | Must be `array` or `redis` for tenancy | `redis` |

## Development

```bash
# Start Vite dev server (port 5174)
npm run dev

# In another terminal — Laravel via Laragon/Nginx/Valet
# No separate artisan serve needed when using Laragon
```

## Commands

```bash
npm run dev              # Vite dev server
npm run build            # Production build
npm run test             # Vitest (JS)
npm run test:ui          # Vitest with UI

php artisan test                 # PHPUnit (all tests)
php artisan test --filter=Name   # Single test
./vendor/bin/pint                # Code style fixer

php artisan db:seed              # Seed full database
php artisan tenants:migrate      # Migrate all tenant DBs
php artisan optimize:clear       # Clear all caches
php artisan optimize             # Rebuild optimized cache (production)
```

## Architecture

### Modular Monolith

Code is organized by **business domain** with GoF design patterns applied within each module. Not a layered monolith — controllers, services, and repositories are not grouped at the top level.

```
app/
├── Billing/          # Plans, subscriptions, payments, gateways
├── Plans/            # Plan definitions and feature gating
├── Products/         # Tenant product management
├── Tenants/          # Tenant lifecycle and management
└── Shared/           # Cross-cutting (middleware, contracts, exceptions)
```

### Module Boundaries

- Modules communicate **only** through interfaces in `Shared/Contracts/`, events, or the service container
- No cross-module concrete imports — always use interfaces or events
- `Shared/` is the only module importable by all others
- Each module has its own `ServiceProvider` binding interfaces, registering listeners, and artisan commands

### Design Patterns

| Pattern | Usage |
|---------|-------|
| **Strategy** | Plan-based behavior (resource limits, notifications) |
| **Factory** | Payment gateway selection, plan features |
| **Adapter** | External services (Stripe, MercadoPago) |
| **State** | Tenant lifecycle (Trial, Active, Suspended, Deleted) |
| **Observer** | Tenant events (provisioned, suspended, reactivated) |
| **Command** | Reversible admin operations (suspend, restore) |
| **Decorator** | Cached repository (CachedTenantRepository) |
| **Template Method** | Onboarding flows |
| **Builder** | Multi-step tenant provisioning |
| **Facade** | TenantManager entry point |

### Application Structure

- **SPA** via Inertia.js — React components in `resources/js/Pages/` and `resources/js/modules/`
- **Two Vite entry points**: `resources/js/app.jsx` (tenant-facing), `resources/js/landlord/app.jsx` (admin panel)
- **Path alias**: `@/` → `resources/js/`
- **Two migration directories**: `database/migrations/` (central), `database/migrations/tenant/` (per-tenant)
- **Dual DB connections**: `mysql` (default tenant), `mysql_central` (explicit central ops)

### Domain Modules

| Module | Purpose | Key Classes |
|--------|---------|-------------|
| **Billing** | Subscriptions, payments, payment gateways | SubscriptionPaymentController, StripePaymentAdapter, MercadoPagoAdapter, PaymentGatewayFactory, ResourceEnforcementFactory |
| **Plans** | Plan definitions, feature gating, limits | PlanController, Plan model with PlanFeature relations |
| **Products** | Tenant product CRUD, categories, warehouses, inventory | ProductController, CategoryController, WarehouseController, InventoryMovementController |
| **Tenants** | Lifecycle, provisioning, metrics, states | TenantManager, TenantBuilder, TenantStateManager, CachedTenantRepository, DatabaseService |
| **Shared** | Middleware, contracts, permissions, exports | CheckTenantState, RequiresPlanFeature, TenantAwarePermissionRegistrar, ExportService |

## Admin Panel

### Features

| Section | Features |
|---------|----------|
| **Overview** | Dashboard with stats cards, tenant growth chart, status donut chart, recent tenants table |
| **Tenant Management** | Create/edit/delete tenants, bulk operations, migrate DB, view database info, change plan, suspend/activate |
| **Resource Usage** | Tenant resource metrics (users, storage, products, warehouses) |
| **Staff Management** | CRUD staff accounts, toggle active status, assign roles/permissions |
| **Roles & Permissions** | CRUD roles and permissions with prerequisite validation |
| **Subscriptions** | Subscription listing with tenant info, payment history per subscription, record payments inline |
| **Payment Methods** | CRUD payment methods, toggle active/inactive, Stripe/PayPal/bank transfer |
| **Plans** | Plan management with resource limits (users, storage, warehouses, products), feature flags |
| **System Settings** | Global application settings |
| **Activity Logs** | Filterable activity log viewer with log name and causer filters |
| **God Mode** | Impersonate any tenant for support/debugging |
| **My Profile** | Admin profile and password management |

### Permissions (9)

`manage tenants`, `manage staff`, `manage plans`, `manage subscriptions`, `manage payment methods`, `manage settings`, `view activity logs`, `impersonate tenants`, `manage profile`

## Multi-Tenancy

### Database-per-Tenant

Each tenant gets its own MySQL database (`tenant{tenant_id}`), provisioned automatically via `stancl/tenancy`. The central database stores tenant registry, admin users, plans, and subscriptions.

### Bootstrappers

1. `DatabaseTenancyBootstrapper` — switches DB connection
2. `CacheTenancyBootstrapper` — tenant-scoped cache tags
3. `FilesystemTenancyBootstrapper` — tenant-scoped storage paths
4. `QueueTenancyBootstrapper` — tenant context for queued jobs

### Tenant Lifecycle States

`Trial` → `Active` → `Suspended` → `Active` (reactivate) or `Deleted`

### Seeding Pipeline

```bash
php artisan db:seed
```

Executes 9 seeders in order:

1. **CentralRolePermissionSeeder** — Spatie roles and permissions for admin guard
2. **PlanSeeder** — 4 plans: Free ($0), Growth ($15), Pro ($29), Enterprise ($99)
3. **StaffSeeder** — 4 admin users (requires `ADMIN_PASSWORD` env)
4. **GlobalSettingSeeder** — System-level settings
5. **PaymentMethodSeeder** — Stripe, PayPal, bank transfer methods
6. **TenantSeeder** — 30 tenants with provisioning pipeline (create DB → migrate → seed tenant data)
7. **SubscriptionPaymentSeeder** — Realistic payment history across subscriptions
8. **TenantResourceUsageSeeder** — Usage metrics
9. **ActivityLogSeeder** — Sample activity log entries

Each tenant seeder runs: TenantUserRolePermissionSeeder → TenantDataSeeder (users, customers, products, orders, warehouses, categories, settings).

## API Endpoints

All admin endpoints are under `/admin` with `auth:admin` guard + Spatie permission middleware.

| Group | Endpoints | Permission |
|-------|-----------|------------|
| Auth & Dashboard | 5 | — |
| Admin Profile | 4 | `manage profile` |
| Tenant Management | 12 | `manage tenants` |
| Dropdown Lists | 2 | — |
| Staff Management | 11 | `manage staff` |
| Plans | 5 | `manage plans` |
| Payment Methods | 6 | `manage payment methods` |
| Subscriptions | 4 | `manage subscriptions` |
| Roles & Permissions | 8 | `manage staff` |
| Activity Logs | 4 | `view activity logs` |
| Global Settings | 3 | `manage settings` |
| Data Export | 3 | `manage tenants` |
| Resource Usage | 2 | `manage tenants` |
| Impersonation | 2 | `impersonate tenants` |
| **Total** | **69** | |

## Testing

### Structure

```
tests/
├── Feature/           # 34 test files (admin, tenants, billing, security, etc.)
├── Unit/              # 9 test files (model tests)
tests/frontend/
├── components/        # 10 component test files
├── hooks/             # 1 hook test file
```

**Total: 54 test files**

### Running Tests

```bash
# PHP — all tests
php artisan test

# PHP — single test
php artisan test --filter=SubscriptionPaymentTest

# JS — all tests
npm run test

# JS — single test file
npx vitest run tests/frontend/components/Subscriptions.test.jsx
```

### Test Configuration

- **PHPUnit**: standard Laravel structure, testing env uses array cache + sync queue
- **Vitest**: jsdom environment, setup at `tests/setup.js`, globals enabled
- **Coverage**: V8 provider (text/html/lcov)

### Testing Notes

- Feature tests that touch tenant data must call `tenancy()->initialize($tenant)` in `setUp()` and `tenancy()->end()` in `tearDown()`
- Tests are co-located by module: `tests/Feature/Billing/`, `tests/Feature/Admin/`, etc.

## Key Packages

| Package | Purpose |
|---------|---------|
| `stancl/tenancy` v3 | Multi-tenancy with database-per-tenant isolation |
| `inertiajs/inertia-laravel` v2 | SPA-like routing between Laravel and React |
| `spatie/laravel-permission` v6 | Role/permission system for admin guard |
| `spatie/laravel-data` v4 | Data transfer objects |
| `spatie/laravel-activitylog` v4 | Activity logging |
| `tightenco/ziggy` v2 | Laravel routes available in JavaScript |
| `knuckleswtf/scribe` v5 | API documentation generator |
| `material-react-table` v3 | MUI-powered data tables with filtering, sorting, pagination |
| `recharts` v3 | Charts for dashboard overview |

## Production

```bash
# Clear caches
php artisan optimize:clear

# Rebuild optimized cache
php artisan optimize

# Run migrations
php artisan migrate --force

# Seed (if needed)
php artisan db:seed

# Build frontend assets
npm run build
```

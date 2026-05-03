# SaaS App

Multi-tenant SaaS platform built with Laravel 10 and React 18, powered by `stancl/tenancy` v3 for domain-isolated tenant management.

## Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 10, PHP 8.1+ |
| Frontend | React 18, Inertia.js (JSX), Tailwind CSS, MUI |
| Auth & Permissions | Spatie Laravel Permission, Sanctum |
| Multi-tenancy | stancl/tenancy v3 (domain-based) |
| Build | Vite 5 (port 5174) |
| Testing | PHPUnit (PHP), Vitest (JS) |
| API Docs | Scribe |

## Prerequisites

- PHP 8.1+
- Node.js 18+
- MySQL 5.7+ / 8.0+
- Composer, npm/pnpm

## Setup

```bash
# Install PHP dependencies
composer install

# Install JS dependencies
npm install   # or: pnpm install

# Environment
cp .env.example .env
php artisan key:generate

# Configure DB credentials in .env, then:
php artisan migrate

# Create a tenant (for development)
php artisan tenants:create

# Or migrate an existing tenant
php artisan tenants:migrate
```

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
```

## Architecture

- **Tenant routes** — `routes/tenant.php` (domain-isolated via `InitializeTenancyByDomain`)
- **Central/admin routes** — `routes/web.php` (uses `auth:admin` guard + Spatie permissions)
- **Inertia SPA** — React components in `resources/js/Pages/`, no Blade views served directly
- **Two Vite entry points**:
  - `resources/js/app.jsx` — tenant-facing app
  - `resources/js/landlord/app.jsx` — central admin panel
- **Path alias**: `@/` → `resources/js/` (JS imports)

## Key Admin Features

- Admin authentication with role/permission system
- Tenant management (create, update, delete, migrate DB, impersonate)
- Staff management with role/permission assignment
- Plan and payment method management
- Activity logging via `spatie/laravel-activitylog`

## Testing

- **PHPUnit**: `tests/Unit/` and `tests/Feature/` suites; testing env uses array cache, sync queue
- **Vitest**: jsdom environment, setup at `tests/setup.js`, globals enabled
- Run a single PHP test: `php artisan test --filter=TestName`

## Tenancy Notes

Tenant databases are created per-tenant automatically by `stancl/tenancy`. The central DB (configured in `.env`) stores tenant registry and admin users. Migrate tenants via:

```bash
php artisan tenants:migrate
# or via API: POST /admin/api/tenants/{id}/migrate
```

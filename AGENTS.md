# AGENTS.md

## Stack
- **Backend**: Laravel 10, PHP 8.1+, MySQL
- **Frontend**: React 18 + Inertia.js (JSX), Tailwind CSS, MUI
- **Build**: Vite 5 (port 5174, strictPort enabled)
- **Testing**: PHPUnit (PHP), Vitest (JS)

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
php artisan test --filter=TestName   # single test

# Code style
./vendor/bin/pint
```

## Architecture
- **Single-page app** via Inertia.js — no Blade views rendered directly; React components in `resources/js/Pages/`
- **Two Vite entry points**: `resources/js/app.jsx` (tenant-facing), `resources/js/landlord/app.jsx` (central admin)
- **Multi-tenant** via `stancl/tenancy` v3 — tenant routes in `routes/tenant.php` use domain-based tenancy (`InitializeTenancyByDomain`)
- **Central/admin routes** in `routes/web.php` use `auth:admin` guard with Spatie permission middleware
- **Path alias**: `@/` maps to `resources/js/` (configured in both `jsconfig.json` and `vitest.config.js`)

## Key Packages
- `stancl/tenancy` — multi-tenancy with domain isolation
- `inertiajs/inertia-laravel` — SPA-like routing
- `spatie/laravel-permission` — role/permission system
- `spatie/laravel-data` — data transfer objects
- `tightenco/ziggy` — routes available in JS via `@/ziggy-js` alias
- `knuckleswtf/scribe` — API documentation generator

## Testing Notes
- PHPUnit: `tests/Unit` and `tests/Feature` suites; testing env uses array cache, sync queue, no Telescope/Pulse
- Vitest: jsdom environment, setup file at `tests/setup.js`, globals enabled
- Run single PHP test: `php artisan test --filter=TestName`

## DB & Env
- Copy `.env.example` to `.env`; generate key with `php artisan key:generate`
- Tenant databases are created per-tenant by `stancl/tenancy` — central DB is the default MySQL connection
- Migrate tenants: `php artisan tenants:migrate` (or via API endpoint `/admin/api/tenants/{id}/migrate`)

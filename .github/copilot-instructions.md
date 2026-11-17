# Copilot instructions for this repository

Copilot CLI loads repository instructions from `AGENTS.md`, this file, and
`.github/instructions/*.instructions.md`. Read the applicable scoped instruction
file for the code being changed. The files under `.github/agents/` are
task-specific guidance references; consult the matching file when relevant, but
do not assume they are automatically loaded as instructions.

`AGENTS.md` is the authoritative source for repository guardrails and current
stack details. Keep these instructions aligned with it and with
`docs/architecture/README.md`.

## Repository snapshot

This is a Laravel 12 + React 18 multi-tenant SaaS app built as a modular monolith. The important split is between:

- central app data and admin flows
- tenant data and tenant-facing routes
- domain modules under `app/{Billing,Plans,Products,Tenants,Shared}`

The repo relies on `stancl/tenancy` for database-per-tenant isolation, Inertia for the SPA shell, and Spatie permissions for guard-aware access control. The implementation and the architectural docs should be read together: `docs/architecture/README.md` is the main source for request flow and tenancy behavior, while `AGENTS.md` captures repo-specific guardrails.

## Build, lint, and test commands

Use the repo’s actual commands rather than guessing:

```bash
# install
composer install
pnpm install

# environment
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan world:seeder   # run once to populate countries/states/cities

# dev servers
php artisan serve          # Laravel + Vite + Reverb when DEV_STACK=true
pnpm run dev              # Vite only

# build
pnpm run build

# lint / quality checks
./vendor/bin/pint
./vendor/bin/pint --test
php scripts/check-doc-anchors.php

# php tests
php artisan test
php artisan test --filter=Name
php artisan test tests/Feature/Admin/<TestName>.php

# js tests
pnpm exec vitest run
pnpm run test:coverage
```

Important CI expectations:

- PHPUnit is intentionally sharded in `.github/workflows/ci.yml`; any new `*Test.php` file must be assigned to exactly one shard.
- Full-page Inertia tests require a built Vite manifest; stale `public/hot` or missing build assets can cause 500s.
- The repo expects MySQL-backed testing (`saas_app_testing`), not SQLite.

## High-level architecture

### Modular monolith boundaries

The app is organized by business domain, not by layer:

- `app/Billing` — subscriptions, payments, gateway logic
- `app/Plans` — plan definitions and feature gating
- `app/Products` — product and inventory management
- `app/Tenants` — tenant lifecycle, provisioning, metrics, state machine
- `app/Shared` — shared contracts, middleware, utilities, cross-cutting behavior

Cross-module communication should prefer interfaces in `app/Shared/Contracts/`, container bindings, or events; do not add new concrete cross-module imports.

### Central vs tenant runtime

- Central database: admin users, tenant registry, plans, subscriptions, shared settings
- Tenant databases: tenant-specific application data
- Frontend entry points are split between `resources/js/app.jsx` (tenant-facing) and `resources/js/landlord/app.jsx` (admin panel)
- Inertia pages live under `resources/js/Pages/`; feature UI should live under `resources/js/modules/`
- Migrations live in `database/migrations/` for central schema and `database/migrations/tenant/` for tenant schema

### Auth and permissions model

- JWT auth is used for both tenant `web` and admin `admin` guards, with separate secrets (`JWT_SECRET` and `JWT_ADMIN_SECRET`)
- Permissions are run through Spatie and guard-aware route middleware; no policy-class pattern is used here
- Admin routes are protected with explicit permission middleware names from `app/Shared/Constants`

### Request flow and behavior docs

For non-trivial flow changes, read the architecture docs before editing:

- `docs/architecture/README.md`
- `docs/architecture/01-request-lifecycle.md`
- `docs/architecture/03-multi-tenancy.md`
- `docs/architecture/04-authentication.md`
- `docs/architecture/05-permissions-and-plan-gating.md`

These docs are the source of truth for the request lifecycle, tenancy bootstrapping, auth, and plan-gating behavior.

## Key conventions specific to this repo

### Backend

- Writes should use a FormRequest with `authorize()` and explicit validation; do not pass `$request->all()` around.
- Listings should use `->paginate()`; avoid `Model::all()` in controllers.
- Eager-load relations that are accessed on collections.
- Tenant-aware jobs extend `App\Shared\Jobs\TenantAwareJob`.
- Tenant cache keys are scoped with `tenant_<tenantKey>` and must stay aligned with the tenancy cache configuration.
- No plan/type branching with raw `if/else`; use the Strategy/Factory pattern already established in the codebase.
- Central-table Eloquent models must declare their connection explicitly when they are not in the allowlist; MySQL central connection rules are enforced by tests.

### Frontend

- Import MUI components individually instead of from a barrel.
- Use `useForm` for Inertia forms; avoid ad hoc per-field `useState` for form state.
- Every `IconButton` should be wrapped in `Tooltip`.
- Material React Table v3 requires `enableRowActions` when using `renderRowActions` and `getRowId` when row selection is used.
- Tooltip assertions in tests should target `data-testid` rather than `getByTitle`, because MUI renders tooltips into a portal under jsdom.
- For Autocomplete, merge both `params.InputProps.ref` and `params.inputProps.ref`; do not spread `inputProps` after `InputProps`.

### Testing and CI

- Follow the existing pattern in `Tests\TestCase`; do not reimplement central leak cleanup or tenant teardown logic.
- Prefer the tenant test helpers (`createTestTenant()`, `initializeTenant($tenant)`, `forgetTenant()`) over raw tenancy calls.
- Route names must be globally unique; do not re-use names in the central-domain loop.
- Every `inertia('X')` call needs a matching `resources/js/Pages/X.jsx` file.
- Migration changes must remain reversible; rollback tests run against the central and tenant schemas.
- Keep the default Vitest pool and per-file `isolate: true`; disabling isolation led to leaked state.

### Operational caveats

- Do not initialize tenancy inside `flushTenantCache()` after a tenant has been deleted; it can corrupt cache/state bootstrapping.
- Wrap tenant initialization in `try/catch` and call `tenancy()->end()` in `finally`.
- Avoid multiple `$model->save()` calls inside a single `DB::transaction()`; consolidate to one save and dispatch model events afterward.
- Keep `public/hot` out of a stale state; CI removes it and rebuilds the Vite manifest.

## Repo-specific guidance for future Copilot sessions

- Read `AGENTS.md` before starting larger edits.
- Check `docs/architecture/README.md` before changing request lifecycle, tenancy, auth, permissions, jobs, or scheduled behaviors.
- Treat the build/test commands above as the canonical project workflow; they reflect the repo’s real setup and CI contract.
- When adding tests, make sure they are assigned to exactly one PHPUnit shard in `.github/workflows/ci.yml`.
- Prefer existing patterns and service providers already in the repo over introducing ad hoc architecture.

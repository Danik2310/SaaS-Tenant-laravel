---
applyTo: "app/**/*.php, routes/**/*.php, database/migrations/**/*.php"
---

# Tenancy instructions

This repo uses `stancl/tenancy` v3 with a central database plus per-tenant databases.

- Keep central routes in `routes/web.php` and tenant routes in `routes/tenant.php`.
- Tenant routes must initialize tenancy via the appropriate tenancy middleware.
- Never call `tenant()` in central-only code without checking whether the tenant context is initialized.
- Use tenant-aware jobs for work that touches tenant-scoped data.
- Keep tenant migrations under `database/migrations/tenant/` and central migrations under `database/migrations/`.
- Wrap tenancy initialization in `try/catch` and always call `tenancy()->end()` in `finally` when appropriate.
- Do not flush tenant cache by re-initializing tenancy after a tenant is deleted.
- Prefer `tenancy()->runForMultiple()` for operations that iterate across all tenants.

Before changing tenant lifecycle logic, read `docs/architecture/03-multi-tenancy.md` and the relevant `Tenants` code.

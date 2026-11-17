---
applyTo: "**/*.php"
---

# Backend instructions

Follow the project rules in `AGENTS.md` and the architecture docs in `docs/architecture/README.md` before making backend changes.

- This is a Laravel 12 / PHP 8.3 multi-tenant SaaS app with a modular monolith.
- Keep cross-module communication through `app/Shared/Contracts/`, events, or service container bindings.
- Do not add concrete cross-module imports when an interface or event exists.
- Prefer existing Strategy/Factory/Builder patterns over new ad hoc logic.
- Writes must use a `FormRequest` with `authorize()`.
- Do not use `$request->all()` in controllers or services without explicit validation.
- Lists should use `->paginate()` and eager-load relations used on collections.
- Tenant-aware jobs should extend `App\Shared\Jobs\TenantAwareJob`.
- Keep tenant cache tags aligned with the tenancy cache configuration (`tenant_<tenantKey>`).
- Central models that are not allowlisted must declare `protected $connection = 'mysql_central'`.
- Route names must be unique across the app and Inertia pages must have matching `resources/js/Pages/*` components.

When in doubt, read the relevant flow doc in `docs/architecture/` before touching lifecycle, auth, tenancy, or jobs.

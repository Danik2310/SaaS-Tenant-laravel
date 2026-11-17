---
applyTo: "app/**/*.php, routes/**/*.php"
---

# Security instructions

Respect the security and permission design baked into this repo.

- Guard-aware permissions are enforced through middleware and constants, not policy classes.
- Keep admin and tenant auth guards distinct: `auth:admin` for central/admin routes and the tenant guard for tenant-scoped routes.
- Use `FormRequest` validation and `authorize()` consistently; never rely on unvalidated `$request->all()` data.
- Keep tenant resource access scoped to the active tenant before mutating records.
- Do not expose sensitive user data in Inertia props or shared response payloads.
- Keep CSRF and HTTP security headers in line with the app’s server setup and expected behavior.

Before making security-sensitive changes, read `AGENTS.md` and the architecture docs for auth and permission flow.

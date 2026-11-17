---
applyTo: "tests/**/*.php, resources/js/**/*.{test,spec}.{js,jsx,ts,tsx}"
---

# Testing instructions

Use the repo’s test conventions and CI rules.

- Prefer the tenant test helpers: `createTestTenant()`, `initializeTenant($tenant)`, and `forgetTenant()`.
- Do not reimplement teardown logic already handled by `Tests\TestCase`.
- PHP tests in CI are explicitly sharded in `.github/workflows/ci.yml`; every new `*Test.php` file must be assigned to exactly one shard.
- New features should include or update a focused test that exercises the changed behavior.
- For tenant flows, initialize tenant context before assertions and end it cleanly.
- Inertia responses should be asserted as Inertia pages, not by raw HTML fragments.
- Vitest keeps the default pool and `isolate: true`.

When adding tests, read the relevant current test file patterns before writing new ones.

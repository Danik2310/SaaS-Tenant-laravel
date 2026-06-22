# Seeder Credential Issues

> Reviewed: 2026-06-19
> Scope: `database/seeders/Tenant/UserSeeder.php`, `database/seeders/Central/StaffSeeder.php`, `database/seeders/Central/PaymentMethodSeeder.php`

---

## Finding 1: CRITICAL — Cross-tenant credential reuse in UserSeeder

**File:** `database/seeders/Tenant/UserSeeder.php`

**Issue:**
Two tenant users are seeded with fixed, non-unique emails in **every** tenant:

| Email | Role |
|---|---|
| `gerente@example.com` | `manager` |
| `cajero@example.com` | `cashier` |

Both use the **same password** (`TENANT_PASSWORD` env var, defaulting to `password` in non-production). This means:

- Anyone who knows `TENANT_PASSWORD` (developers, CI/CD pipelines, deployment tools) can log in as `gerente@example.com` or `cajero@example.com` on **any** tenant in the system.
- The password is shared across **all** tenants — there is no per-tenant salt, unique email prefix, or MFA requirement.
- A credential leak exposes every tenant's dashboard simultaneously.

**Recommendation:**
- Generate unique credentials per tenant during provisioning (e.g., `gerente-{tenant_ref}@example.com` with a tenant-specific password).
- Alternatively, enforce MFA for these pre-seeded accounts in production.
- Remove the password-sharing pattern: each tenant should have an independent credential for these roles.

---

## Finding 2: HIGH — Shared admin password in StaffSeeder

**File:** `database/seeders/Central/StaffSeeder.php`

**Issue:**
Four admin/staff users are all created with the **same password** (`ADMIN_PASSWORD` env var, defaulting to `password` in non-production):

| Name | Email | Role | Active |
|---|---|---|---|
| System Administrator | `admin@example.com` | `super-admin` | Yes |
| Staff Manager | `manager@example.com` | `staff` | Yes |
| Staff Viewer | `viewer@example.com` | `staff` | Yes |
| Inactive Staff | `inactive@example.com` | `staff` | No |

- All four distinct roles use a single shared `ADMIN_PASSWORD`.
- Compromise of any one admin account (e.g., the low-privilege `viewer@example.com`) gives an attacker the password for the `super-admin` account as well.
- The `ADMIN_EMAIL` env var overrides only the first user's email — but all share the same password.

**Recommendation:**
- Generate unique passwords per staff user at seed time, or use per-role env vars (`ADMIN_PASSWORD`, `MANAGER_PASSWORD`, etc.).
- Even with unique passwords, consider whether all staff should be seeded automatically in production, or only the initial super-admin.
- Rotate credentials immediately after initial provisioning.

---

## Finding 3: MEDIUM — Placeholder payment keys in PaymentMethodSeeder

**File:** `database/seeders/Central/PaymentMethodSeeder.php`

**Issue:**
Payment gateway credentials are stored as plaintext placeholder strings in the `payment_methods` table:

| Provider | api_key | secret_key |
|---|---|---|
| Stripe (test) | `sk_test_placeholder_stripe` | `pk_test_placeholder_stripe` |
| Stripe (live) | `sk_live_placeholder_stripe` | `pk_live_placeholder_stripe` |
| PayPal (test) | `sb_test_placeholder_paypal` | `sb_test_secret_placeholder_paypal` |

- While these are clearly placeholders and not real secrets, the **pattern** normalises storing plaintext keys in the database.
- If production keys are ever stored in this table in plaintext, they become accessible to any user or process with database read access.
- Migration `2026_05_26_230926` already partially addresses this with encryption support at the column level.

**Recommendation:**
- Ensure real payment keys are **never** stored in plaintext — use Laravel's encryption (`Crypt::encryptString()`) or a dedicated secrets manager (e.g., HashiCorp Vault, AWS Secrets Manager).
- Remove the placeholder values from the seeder (or use clearly dummy values that cannot be mistaken for real keys).
- Add a `$casts` property or accessor on the `PaymentMethod` model to auto-encrypt/decrypt these fields.

---

## Summary Table

| Finding | Severity | File | Key Risk |
|---|---|---|---|
| 1 | CRITICAL | `database/seeders/Tenant/UserSeeder.php` | Same credentials across all tenants; leak = total compromise |
| 2 | HIGH | `database/seeders/Central/StaffSeeder.php` | Single password for all admin roles; horizontal privilege escalation |
| 3 | MEDIUM | `database/seeders/Central/PaymentMethodSeeder.php` | Plaintext credential pattern encourages unsafe storage |

## Cross-reference

- `.env.example` documents both `TENANT_PASSWORD` and `ADMIN_PASSWORD` with production warnings.
- All three seeders will refuse to run in production without the respective env var set (they throw `RuntimeException`), but in non-production they silently default to `'password'`.

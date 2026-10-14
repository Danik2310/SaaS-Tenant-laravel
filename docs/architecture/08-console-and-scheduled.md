# 08 — Console & Scheduled Tasks

> **Scope**: Artisan command registration, the command catalogue, and the
> recurring schedule driven by `php artisan schedule:run`.
>
> **Out of scope**: queue job internals (`07`), tenant state transitions (`03`).

---

## 1. Kernel & registration

[`App\Console\Kernel`](../../app/Console/Kernel.php) extends Laravel's
`ConsoleKernel`:

- `commands()` `:59` loads `app/Console/Commands` and requires
  `routes/console.php` (closure commands only — currently just `inspire`).
- `schedule()` `:33` declares the recurring tasks below.

**Module commands are registered by their own service providers**, so the core
Kernel stays thin:

| Provider | Commands |
|----------|----------|
| [`SharedServiceProvider::boot()`](../../app/Shared/SharedServiceProvider.php) `:68` | `AssignStaffRole`, `AssignSuperAdminRole`, `CheckAdminPermissions`, `CheckPermissionsData`, `GenerateTenantReferenceIds` |
| [`TenantServiceProvider`](../../app/Tenants/TenantServiceProvider.php) `:49` | `CollectTenantMetricsCommand`, `ExpireTrialsCommand` |
| [`BillingServiceProvider::boot()`](../../app/Billing/BillingServiceProvider.php) `:57` (console only) | `ExpireSubscriptionsCommand`, `SyncTenantSubscriptions`, `UpdatePaymentMethod` |

---

## 2. Schedule

| Cadence | Task | Purpose |
|---------|------|---------|
| hourly | `ExportService::cleanupExpired()` | Delete export files past their 24h expiry (`07` §3) |
| hourly | `tenants:collect-metrics` | Snapshot tenant resource usage |
| daily | `tenants:expire-trials` | Move lapsed trials out of `Trial` |
| daily | `tenants:expire-subscriptions` | Expire lapsed subscriptions + suspend tenants |

All tasks are **idempotent** — safe to re-run. In production a single cron entry
runs `php artisan schedule:run` every minute.

---

## 3. Command catalogue

### Tenant lifecycle (`App\Tenants`, `App\Shared`)

| Command | Effect |
|---------|--------|
| `tenants:expire-trials [--dry-run]` | Suspend `Trial` tenants past `trial_ends_at` via `TenantStateManager` |
| `tenants:collect-metrics [--tenant=*]` | Dispatch `CollectTenantMetrics` for each selected tenant |
| `tenants:generate-reference-ids [--dry-run]` | Backfill `reference_id` on tenants (incl. trashed) |

### Billing (`App\Billing`)

| Command | Effect |
|---------|--------|
| `tenants:expire-subscriptions [--dry-run]` | Mark subscriptions `expired`, suspend their tenants |
| `tenants:sync-subscriptions [--dry-run] [--tenant=]` | Create subscriptions for plan-holding tenants lacking one |
| `payment-method:update {id} [--name= --provider= --api_key= --secret_key= --mode= --active=]` | Update a stored payment method from the CLI |

### Admin & permissions (`App\Shared`)

| Command | Effect |
|---------|--------|
| `admin:assign-super-admin {email?}` | Grant the `super-admin` role on the `admin` guard |
| `admin:assign-staff {email?}` | Grant the `staff` role on the `admin` guard |
| `check:permissions-data` | Print all permissions + roles (diagnostic) |
| `check:admin-permissions` | Print the first admin's roles + key permissions (diagnostic) |
| `inspire` | Framework default quote (closure in `routes/console.php`) |

> The `admin:assign-*` commands require `CentralRolePermissionSeeder` to have run
> first; they resolve roles on the **`admin` guard**.

---

## 4. Key decisions & invariants

- **Registration lives with the module** that owns the command — the core Kernel
  only knows about the app's own `Commands` directory and closure routes.
- **Billing commands register only in console** (`runningInConsole()`) to keep the
  web request container lean.
- **Scheduling is idempotent and dry-run capable** — operational safety first.
- **State changes go through `TenantStateManager`** (never raw status writes), so
  lifecycle events (`TenantSuspended`, etc.) fire consistently (`03`).

---

## 5. Cross-references

- [`02-providers-and-container.md`](02-providers-and-container.md) — how module providers register commands.
- [`03-multi-tenancy.md`](03-multi-tenancy.md) — the state machine the expiring commands drive.
- [`07-jobs-events-observers.md`](07-jobs-events-observers.md) — `ExportService` and `CollectTenantMetrics`.
- Source: [`Kernel`](../../app/Console/Kernel.php), [`routes/console.php`](../../routes/console.php).

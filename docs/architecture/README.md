# Architecture Documentation

Narrative documentation of **how the system's functions flow** — the request
lifecycle, the tenancy machinery, authentication, permissions, jobs and
observers — paired with inline PHPDoc on the source itself.

> **Companion to the code.** Every flow described here links to the class or
> method that implements it using `file:line` references. If a flow doc and the
> source disagree, the source wins — fix the doc.

---

## How to read this set

Each flow doc follows the same shape:

1. **Scope** — what the doc covers and what it deliberately leaves out.
2. **Diagram** — a Mermaid sequence / state / flowchart of the happy path.
3. **Step-by-step flow** — numbered, referencing `Class::method()` and `file:line`.
4. **Key decisions & invariants** — the non-obvious rules ("why" notes).
5. **Cross-references** — related docs and source files.

---

## Contents

| Doc | Covers |
|-----|--------|
| [`00-system-overview.md`](00-system-overview.md) | Stack, modular monolith, central vs tenant, module boundaries, documentation standard |
| [`01-request-lifecycle.md`](01-request-lifecycle.md) | Bootstrap → HTTP kernel → middleware pipeline → controller → exception handler → response |
| [`02-providers-and-container.md`](02-providers-and-container.md) | Every service provider, boot order, container bindings, contracts → implementations |
| [`03-multi-tenancy.md`](03-multi-tenancy.md) | Central/tenant split, bootstrappers, provisioning pipeline, tenant state machine |
| [`04-authentication.md`](04-authentication.md) | Admin JWT-cookie auth, tenant session auth, guards, session expiry |
| [`05-permissions-and-plan-gating.md`](05-permissions-and-plan-gating.md) | Spatie permissions, tenant-aware registrar, plan feature gating, limits |
| [`06-impersonation-god-mode.md`](06-impersonation-god-mode.md) | God-mode handoff token, entry/exit, read-only guard |
| [`07-jobs-events-observers.md`](07-jobs-events-observers.md) | Queue jobs, export pipeline, activity log, resource-usage observers |
| [`08-console-and-scheduled.md`](08-console-and-scheduled.md) | Console kernel schedule and artisan commands |

Docs `00`–`08` cover the **general (cross-cutting) layer**. Domain modules
(Tenants, Billing, Plans, Products) and the React/Inertia front end are
documented in later passes.

---

## Documentation standard

Documentation lives in **two places**, each with a distinct job.

### 1. Flow docs (`docs/architecture/*.md`) — the narrative

Explains *how the system behaves end to end*: which functions run, in what
order, why they are ordered that way, and what invariants hold. Use Mermaid for
sequence and state flows. Keep them at the flow level — do not restate every
signature (that is what the PHPDoc is for).

### 2. Inline PHPDoc (in the source) — the contract

Attached to the class or method it describes, so it is visible in an IDE and
next to the code. Kept at the **contract / why** level so it does not rot.

**Class-level template**

```php
/**
 * One-line summary of the class.
 *
 * Its role in the system and where it sits in the request/job lifecycle.
 *
 * Flow:
 *  1. First meaningful step.
 *  2. Second step.
 *  3. ...
 *
 * @see \App\Shared\...\RelatedClass
 */
```

**Method-level template**

```php
/**
 * What the method does (one line).
 *
 * Flow:
 *  1. First step.
 *  2. Second step.
 *
 * @param  Type  $name  Meaning of the argument.
 * @return Type  What is returned.
 * @throws \Throwable  When it is thrown.
 *
 * Side effects: <DB writes / events fired / cache keys touched>.
 */
```

**Trivial methods** (accessors, `authorize()`, `rules()`, `toArray()` of a thin
resource) get a single summary line — no ceremony.

**Rules of thumb**

- Prefer "why" over "what" when the code already says *what*.
- Name the side effects: rows written, events dispatched, cache keys cleared.
- Never document secret values, credentials, or `.env` contents.
- Preserve existing explanatory comments; extend, do not delete.
- Documentation must be **behavior-neutral** — comments and docblocks only.

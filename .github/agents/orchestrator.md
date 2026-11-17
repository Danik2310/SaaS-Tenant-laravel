# Repository task routing

Use this guide to choose task-specific context. Copilot CLI loads repository
instructions independently; this file does not control automatic instruction
loading or replace `AGENTS.md`.

---

## Step 1 — Classify the task

Before editing, estimate the scope and identify the relevant domain and work
type:

1. **How many files will change?**
   - 1 file → Tier 1 (minimal context)
   - 2–5 files, same module → Tier 2 (single domain)
   - 5+ files or multiple modules → Tier 3 (list exact files, split the work,
     and wait for confirmation)

2. **Which domain does it belong to?**
   Tenants | Billing | Plans | Products | Shared | Frontend | Infrastructure

3. **What type of work is it?**
   CRUD | Migration | Test | Refactor | UI | Auth/Security | CI/Deploy

---

## Step 2 — Choose relevant context

| Tier | Scope | Context |
|------|-------|---------|
| **Tier 1** | 1 file | `AGENTS.md` conventions and the applicable scoped instructions |
| **Tier 2** | 2–5 files, one domain | One matching `.github/agents/` guide and, when useful, one relevant skill |
| **Tier 3** | 5+ files or multiple modules | Stop; list exact files, group by domain, and propose smaller tasks for confirmation |

Keep task-specific context focused: at most two agent guides and one skill.
Do not load unrelated guides or invent skill paths.

---

## Step 3 — Split if Tier 3

If the task is Tier 3, do not start editing until the user confirms a split.

1. List the exact files expected to change.
2. Group them by domain or concern.
3. Propose bounded subtasks, each with its files.
4. Wait for confirmation before proceeding

Do not split work into arbitrary pieces if it can be handled within the agreed
scope. If the task spans more than two modules, stop and report.

---

## Step 4 — Domain → guidance mapping

Consult only the guide matching the primary domain. File spelling and case
below match the repository:

| Primary domain | Agent to load | Skip these |
|----------------|---------------|------------|
| Tenants (lifecycle, states, builder, domain) | `.github/agents/tenancy.md` | unrelated domain guides |
| Billing (subscription, payment, gateway) | `.github/agents/billing.md` | unrelated domain guides |
| Plans (feature gates, plan limits) | `.github/agents/billing.md` | unrelated domain guides |
| Products (product, category, warehouse, inventory) | `.github/agents/Patterns.md` | unrelated domain guides |
| Shared (middleware, contracts, exceptions) | `.github/agents/segurity.md` | unrelated domain guides |
| Auth / security / guards | `.github/agents/segurity.md` | unrelated domain guides |
| Migration file | `.github/agents/migrations.md` | unrelated domain guides |
| Test file | `.github/agents/testing.md` | unrelated domain guides |
| API route / JSON response | `.github/agents/api.md` | unrelated domain guides |
| React dashboard component | `.github/agents/dashboard-ui.md` | unrelated domain guides |
| CI/CD, GitHub Actions | `.github/agents/Ci.md` | unrelated domain guides |
| SEO, public pages | `.github/agents/seo.md` or `.github/agents/web-design.md` | unrelated domain guides |
| New PHP class (any domain) | `.github/agents/Patterns.md` | unrelated domain guides |

---

## Step 5 — Skill → task type mapping

Use at most one relevant skill, if it is available in the current Copilot
environment. Repository skill sources are under `.agents/skills/`; verify the
skill file exists before referencing it.

| Work type | Skill |
|-----------|-------|
| React component, UI, styling | `frontend-design` |
| React performance, memoization | `vercel-react-best-practices` |
| Laravel controller, service, Eloquent | `laravel-patterns` |
| PHP strict typing, DTOs, PHPStan | `php-pro` |
| Vitest test | `vitest` |
| Stripe integration | `stripe-best-practices` |
| Tailwind layout | `tailwind-css-patterns` |
| Vite config / build | `vite` |

If no available skill matches, proceed without one.

---

## Step 6 — Pre-flight checklist (before writing any code)

Before the first edit:

- [ ] Files to read: listed and scoped (no wildcards, no full directories)
- [ ] Module boundary check: does any file import a concrete class from another module?
- [ ] Pattern check: does a Factory/Strategy/Builder already exist for this domain?
- [ ] Test check: which test file will cover this change?

Follow `AGENTS.md` and the applicable `.github/instructions/` files. If a
cross-module dependency or architectural choice is unclear, stop and resolve it
before editing. Extend an existing Factory/Strategy/Builder rather than
duplicating it. Add focused tests for new or changed behavior; do not create
tests for documentation-only changes.

---

## Step 7 — Execution rules

- Read enough relevant code and documentation to understand the behavior; the
  pre-flight list is a scope guide, not a ban on necessary investigation.
- Make a coherent change, then run the smallest relevant test or check.
- If investigation reveals a broader scope than agreed, stop and report it.
- Follow the repository's Git restrictions in `AGENTS.md`.

---

## Token budget summary

| Situation | Max context loaded |
|-----------|-------------------|
| 1 file change | `AGENTS.md` + applicable scoped instructions |
| Single domain, 2–5 files | `AGENTS.md` + one guide + optional one skill |
| Cross-domain (2 modules) | `AGENTS.md` + up to two guides |
| Cross-domain (3+ modules) | Stop — split and confirm |
| Migration only | `AGENTS.md` + `.github/agents/migrations.md` |
| Test only | `AGENTS.md` + `.github/agents/testing.md` |

**Every extra agent loaded = ~500–1500 tokens per message burned on context you don't need.**

---

## Quick reference — task examples

| Task | Tier | Agents | Skill |
|------|------|--------|-------|
| Fix validation in `StoreTenantRequest` | 1 | none | none |
| Add CSV export to `ProductController` | 2 | `Patterns.md` | `laravel-patterns` |
| New payment gateway adapter | 2 | `billing.md` | `laravel-patterns` |
| Add tenant state transition test | 2 | `testing.md` | none |
| Refactor `TenantManager` + update `BillingService` | 3 | **STOP — split** | — |
| New migration for subscriptions | 1 | `migrations.md` | none |
| Fix KPI card in Dashboard | 2 | `dashboard-ui.md` | `frontend-design` |
| Add Stripe webhook handler | 2 | `billing.md` | `stripe-best-practices` |
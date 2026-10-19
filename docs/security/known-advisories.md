# Known Dependency Advisories

> **Scope**: Third-party dependency advisories that are **knowingly accepted**
> (no fix available, or no realistic exploit path in this codebase).
>
> **Purpose**: Give every accepted advisory a documented owner, rationale, and
> expiry condition, so `composer audit` / `pnpm audit` output stays meaningful —
> anything **not** listed here is treated as a real finding.
>
> **Applies to**: All PHP and JavaScript dependencies.

---

## How to read this register

Each entry records:

| Field | Meaning |
|---|---|
| Advisory | The GHSA / CVE identifier and severity |
| Dependency | The package, its version, and the dependency chain that pulls it in |
| Exploit path | Where (if anywhere) the vulnerable code is reachable at runtime |
| Risk | Why the advisory is **not** exploitable in this system |
| Action | What we did (waive, pin, patch, monitor) |
| Review trigger | The condition that forces us to revisit the entry |

An entry is only valid while its **Review trigger** has not fired. When a
patched version ships, the entry must be removed and the dependency upgraded —
do not let it linger.

---

## JS-0001 — `braces` stack-exhaustion denial of service

| | |
|---|---|
| **Advisory** | [`GHSA-vfj7-8cjw-p6xm`](https://github.com/advisories/GHSA-vfj7-8cjw-p6xm) — **High** |
| **CWE** | [CWE-1333](https://cwe.mitre.org/data/definitions/1333.html) — Inefficient Regular Expression Complexity (ReDoS / stack exhaustion) |
| **Dependency** | `braces@3.0.3` |
| **Vulnerable range** | `<=3.0.3` |
| **Patched versions** | **None published** (as of the last review) |
| **Introduced via** | `tailwindcss` → (`fast-glob` / `micromatch` / `chokidar`) → `braces` |
| **Type** | Dev / build-time dependency only (not shipped in the production bundle) |

### The vulnerability

`braces` expands glob patterns. A pattern with many **nested brace groups**
(e.g. `{a,b}{a,b}{a,b}{a,b}…`) causes exponential backtracking while parsing,
which exhausts the call stack and hangs the process — a denial-of-service
condition. The trigger is a hostile **glob pattern**, not a hostile string value.

### Exploit path in this codebase

`braces` is reached **only** when Tailwind expands the `content` globs declared
in [`tailwind.config.js`](../../tailwind.config.js):

```js
content: [
    './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
    './storage/framework/views/*.php',
    './resources/views/**/*.blade.php',
    './resources/js/**/*.jsx',
    './resources/js/**/*.js',
],
```

That array is **static and author-written**. There is:

- **no** `safelist`, and **no** dynamic / computed `content` entry;
- **no** HTTP request path that reaches Tailwind, PostCSS, `micromatch`, or `braces` — the toolchain runs at `vite build` / `vite dev` time only;
- nothing in `resources/js/**`, `vite.config.js`, or `postcss.config.js` that forwards user input into a glob.

Therefore an attacker would need the ability to edit `tailwind.config.js`
(i.e. already have write access to the repository or the build host) to reach the
vulnerable code — at which point they can run arbitrary code directly and this
advisory is irrelevant.

### Risk assessment

**Negligible in production.** The vulnerable library is a build-time
transitive dependency, invoked with developer-authored patterns on a trusted
build host, never with data derived from an inbound request. The advisory is
recorded here rather than "fixed" because **no patched version of `braces`
exists** — there is nothing to upgrade to.

### Action taken

1. **Waived** in the audit gate (`auditConfig.ignoreGhsas` in
   [`pnpm-workspace.yaml`](../../pnpm-workspace.yaml)) so a *known, accepted*
   advisory does not mask *new* ones. The waiver lists the single GHSA id — not
   `--ignore-unfixable` — so any **other** advisory still fails the gate.
2. **Enforced in CI** (`.github/workflows/ci.yml`): the `lint` job runs
   `composer audit --locked --no-interaction` and the `frontend` job runs
   `pnpm audit --audit-level=high`. Both exit `0` with this waiver in place; a
   newly published advisory of any severity at/above the threshold fails the
   build.
3. **Defense-in-depth guard** added in `tailwind.config.js`:
   `assertGlobsAreSane()` validates `content` patterns at config-load time,
   rejecting any pattern with excessive brace **nesting** or brace **group
   count** before it can reach `braces`. This does not (and cannot) protect
   against request input — there is none — but it stops a future contributor
   from accidentally introducing a pathological pattern.
4. **Documented** here, with the exact chain, so the decision is auditable.

### Review trigger

Remove this entry and upgrade as soon as **any** of the following becomes true:

- A patched `braces` version is published (watch the advisory's "Patched
  versions" field).
- `tailwindcss` (or its glob dependencies) is upgraded to a line that no longer
  resolves `braces@<=3.0.3`.
- Any code change introduces a **dynamic / user-derived** entry in the Tailwind
  `content` array or a `safelist` built from request data — at that point the
  "no untrusted input" assumption is void and this must be re-assessed
  immediately.

---

## See also

- [`docs/snippets/migration-seeder-security.md`](../snippets/migration-seeder-security.md) — seeder, encryption, and raw-SQL security rules.

---

> **Last reviewed**: 2026-10-19
> **Maintainers**: Platform Team

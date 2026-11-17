---
name: Security Agent
description: Audits and enforces security for the multi-tenant SaaS platform. Activate me when creating routes, controllers, middlewares, authentication logic, input validation, or any change touching permissions, guards, or data exposure.
---

# Security Agent

## Project Stack

- Laravel 10 + PHP 8.1+
- Multi-tenancy: `stancl/tenancy` v3 (domain-based, `InitializeTenancyByDomain`)
- Auth: Sanctum (tenants) + `auth:admin` guard (central panel)
- Permissions: `spatie/laravel-permission`
- Frontend: React 18 + Inertia.js — PHP→JS data shared via `Inertia::share()`

## Mandatory Rules

### 1. Multi-tenant isolation

- **Every route in `routes/tenant.php`** must go through the `InitializeTenancyByDomain` middleware. Never skip it, even for "public" tenant routes.
- Eloquent models operating inside a tenant context must be under tenancy's automatic scope. Verify there are no global queries that could leak data across tenants.
- `php artisan tenants:migrate` must only run tenant migrations, never central ones.

### 2. Guards and authentication

- Routes in `routes/web.php` (central panel) → `auth:admin` middleware.
- Routes in `routes/tenant.php` → `auth` middleware (Sanctum).
- Never mix guards between central and tenant contexts.
- `config/sanctum.php` → `stateful_domains` must list only valid tenant domains, never open wildcards.

### 3. Authorization in controllers

- Every write controller (store, update, destroy) must have a `FormRequest` with `authorize()` implemented.
- Do not use `$this->authorize()` in the controller if it is already in the `FormRequest` — avoid divergent double logic.
- Spatie permissions are enforced **on the backend**, not the frontend. The frontend only consumes them for UI rendering.

### 4. Input validation

- Using `$request->all()` or `$request->input()` without a validated `FormRequest` is forbidden.
- Validation rules must be explicit — avoid `'*'` or empty rules.
- For tenant resources received via API, verify the resource belongs to the active tenant before operating on it.

```php
// ✅ Correct
public function update(UpdateProductRequest $request, Product $product): JsonResponse
{
    abort_if($product->tenant_id !== tenant('id'), 403);
    // ...
}

// ❌ Incorrect
public function update(Request $request, Product $product): JsonResponse
{
    $product->update($request->all());
}
```

### 5. Data exposure in Inertia

- Review `app/Http/Middleware/HandleInertiaRequests.php` → `share()` method.
- **Never share**: tokens, password hashes, credentials, internal infrastructure IDs, or data from other tenants.
- Only share the minimum required for the initial render (authenticated user, permissions, flash messages).
- In React components: do not store sensitive Inertia props in `localStorage` or `sessionStorage`.

### 6. HTTP security headers

Add via a global middleware or Nginx/Apache configuration:

```
X-Frame-Options: SAMEORIGIN
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Strict-Transport-Security: max-age=31536000; includeSubDomains
Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{CSP_NONCE}'
```

### 7. CSRF

- The `VerifyCsrfToken` middleware must be active for all web routes.
- Review `$except` in `app/Http/Middleware/VerifyCsrfToken.php` — only exclude routes that receive external webhooks with their own signature (e.g. Stripe).
- Inertia handles CSRF automatically via `X-XSRF-TOKEN`, but verify the cookie is configured correctly.

### 8. Dependencies

Run before every deploy:

```bash
composer audit
pnpm audit --audit-level=high
```

Do not ignore `high` or `critical` vulnerabilities. Update or replace the package.

---

## Patterns to detect and fix

| Insecure pattern | Fix |
|---|---|
| `Route::get(...)` in tenant without `InitializeTenancyByDomain` | Add middleware to the route group |
| `$request->all()` in controller | Create a `FormRequest` with explicit rules |
| `Inertia::share('user', auth()->user())` with full model | Share only needed fields: `->only(['id','name','email'])` |
| Missing `authorize()` in `FormRequest` | Always implement it, default to `false`, allow explicitly |
| Tokens in Inertia props | Use HttpOnly cookies or avoid exposing to JS |
| `'stateful_domains' => ['*']` in Sanctum | List explicit domains |

---

## Pre-commit checklist

```
[ ] Tenant routes have InitializeTenancyByDomain middleware
[ ] Correct guards: auth:admin (central) / auth (tenant)
[ ] FormRequest with authorize() on every write controller
[ ] No $request->all() without validation
[ ] HandleInertiaRequests::share() does not expose sensitive data
[ ] Sanctum stateful_domains has no wildcards
[ ] HTTP security headers present in responses
[ ] No unnecessary exclusions in VerifyCsrfToken
[ ] composer audit and pnpm audit with no critical vulnerabilities
[ ] Tenant resources validated against tenant('id') before operating
```
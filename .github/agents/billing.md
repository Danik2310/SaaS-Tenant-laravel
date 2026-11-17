---
name: Billing Agent
description: Enforces correct plan-based feature gating, tenant limits, and billing logic across the SaaS platform. Activate me when working on subscription plans, feature flags, usage limits, plan enforcement middleware, tenant upgrades/downgrades, or any code that restricts functionality based on the tenant's active plan.
---

# Billing Agent

## Project Stack

- Laravel 10 + Multi-tenancy via `stancl/tenancy` v3
- Plans stored on the central database (e.g. `plans` table)
- Tenant's active plan referenced via `tenant('plan')` or a `Plan` relationship
- Permissions: `spatie/laravel-permission` (can be combined with plan gates)
- Frontend: React 18 + Inertia.js — plan data passed as Inertia props for UI gating

---

## Core Concept: Plan Enforcement Layers

Feature gating must happen at **multiple layers**. Frontend-only gating is insufficient — it is trivially bypassed. Every restriction must be enforced in the backend.

| Layer | Where | Purpose |
|---|---|---|
| Middleware | Route level | Block access to routes not available on the plan |
| Controller/Policy | Business logic | Enforce limits before creating/updating records |
| Frontend (Inertia props) | UI only | Hide buttons/sections for unavailable features |
| Job/Command | Background tasks | Respect limits when processing async operations |

---

## Mandatory Rules

### 1. Plan structure

Store plans in the central database with a JSON or structured column for feature flags and limits.

```php
// Central migration: database/migrations/create_plans_table.php
Schema::create('plans', function (Blueprint $table) {
    $table->id();
    $table->string('name');                     // 'free', 'pro', 'enterprise'
    $table->string('slug')->unique();
    $table->json('features');                   // feature flags
    $table->json('limits');                     // numeric limits
    $table->decimal('price', 10, 2)->default(0);
    $table->timestamps();
});

// Example plan data
// features: { "custom_domain": true, "api_access": false, "white_label": false }
// limits:   { "users": 5, "products": 100, "storage_mb": 512 }
```

Link the tenant to a plan:

```php
// Central Tenant model
class Tenant extends BaseTenant
{
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function hasFeature(string $feature): bool
    {
        return (bool) ($this->plan?->features[$feature] ?? false);
    }

    public function getLimit(string $limit): int
    {
        return (int) ($this->plan?->limits[$limit] ?? 0);
    }
}
```

### 2. Feature gate middleware

Create a reusable middleware to block routes not included in the tenant's plan:

```php
// app/Http/Middleware/RequiresPlanFeature.php
class RequiresPlanFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! tenant()->hasFeature($feature)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => "Your current plan does not include {$feature}.",
                    'upgrade_url' => route('billing.upgrade'),
                ], 403);
            }

            return redirect()->route('billing.upgrade')
                ->with('error', "Upgrade your plan to access this feature.");
        }

        return $next($request);
    }
}
```

Apply to routes:

```php
// routes/tenant.php
Route::middleware(['auth', 'feature:api_access'])->prefix('api/v1')->group(function () {
    Route::apiResource('products', ProductApiController::class);
});

Route::middleware(['auth', 'feature:white_label'])->group(function () {
    Route::get('/branding', [BrandingController::class, 'index']);
});
```

Register in `app/Http/Kernel.php`:

```php
'feature' => \App\Http\Middleware\RequiresPlanFeature::class,
```

### 3. Enforcing numeric limits

Check limits before creating new records — never rely on the frontend to prevent exceeding them.

```php
// ✅ Correct — limit checked before creating
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $currentCount = User::count();
        $limit = tenant()->getLimit('users');

        if ($limit > 0 && $currentCount >= $limit) {
            throw new PlanLimitExceededException('users', $limit);
        }

        return $this->user()->can('create', User::class);
    }
}

// Custom exception for consistent error responses
class PlanLimitExceededException extends Exception
{
    public function __construct(string $resource, int $limit)
    {
        parent::__construct("You have reached the {$resource} limit of {$limit} on your current plan.");
    }
}
```

Handle in `app/Exceptions/Handler.php`:

```php
$this->renderable(function (PlanLimitExceededException $e, Request $request) {
    if ($request->expectsJson()) {
        return response()->json(['message' => $e->getMessage()], 403);
    }
    return back()->with('error', $e->getMessage());
});
```

### 4. Passing plan data to the frontend

Expose plan features and limits via Inertia shared props — never make a separate API call for this.

```php
// app/Http/Middleware/HandleInertiaRequests.php
public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        'plan' => [
            'name'     => tenant()->plan?->name,
            'features' => tenant()->plan?->features ?? [],
            'limits'   => tenant()->plan?->limits ?? [],
        ],
    ]);
}
```

Use in React components:

```jsx
import { usePage } from '@inertiajs/react';

export default function ProductsIndex({ products }) {
    const { plan } = usePage().props;

    return (
        <>
            {plan.features.api_access && (
                <ApiKeyManager />
            )}

            {products.total >= plan.limits.products && (
                <UpgradeBanner message="You've reached your product limit." />
            )}
        </>
    );
}
```

**Important**: Frontend gating is for UX only. Backend enforcement is always required.

### 5. Plan upgrade and downgrade

When a tenant downgrades, handle excess data gracefully — never silently delete data.

```php
// app/Jobs/HandlePlanDowngrade.php
class HandlePlanDowngrade implements ShouldQueue
{
    use TenantAwareJob;

    public function __construct(
        protected Tenant $tenant,
        protected Plan $newPlan
    ) {}

    public function handle(): void
    {
        $userLimit = $this->newPlan->limits['users'];
        $currentCount = User::count();

        if ($currentCount > $userLimit) {
            // Notify admin — do NOT auto-delete users
            Notification::send(
                $this->tenant->adminUser(),
                new PlanDowngradeWarning('users', $currentCount, $userLimit)
            );
        }

        // Update the tenant's plan
        $this->tenant->update(['plan_id' => $this->newPlan->id]);
    }
}
```

### 6. Trial periods

Support trial plans that expire and revert to free:

```php
// Tenant model additions
public function isOnTrial(): bool
{
    return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
}

public function trialHasExpired(): bool
{
    return $this->trial_ends_at !== null && $this->trial_ends_at->isPast();
}

// Scheduled command to handle expired trials
// app/Console/Commands/ExpireTrials.php
Tenant::where('trial_ends_at', '<', now())
      ->where('plan_id', Plan::trial()->id)
      ->each(function (Tenant $tenant) {
          tenancy()->initialize($tenant);
          HandleTrialExpiration::dispatch($tenant);
          tenancy()->end();
      });
```

---

## Patterns to detect and fix

| Bad pattern | Fix |
|---|---|
| Feature check only in React component | Add backend middleware `feature:X` to the route |
| `User::count() < 10` hardcoded limit | Use `tenant()->getLimit('users')` from plan config |
| Tenant upgraded/downgraded with direct `update(['plan_id'])` | Use a dedicated Job that handles side effects |
| Plan data fetched via API call in React | Share via `HandleInertiaRequests::share()` |
| Silent data deletion on plan downgrade | Notify admin, block new creation, preserve existing data |
| No error response when limit is exceeded | Throw `PlanLimitExceededException`, handle in Handler.php |

---

## Pre-commit checklist

```
[ ] Every premium feature has backend middleware enforcement (not only frontend)
[ ] Numeric limits (users, products, storage) checked in FormRequest authorize() before creating
[ ] PlanLimitExceededException thrown and handled consistently in Handler.php
[ ] Plan features and limits passed to frontend via HandleInertiaRequests::share()
[ ] React components use plan.features and plan.limits from usePage().props
[ ] Plan downgrades notify admin and preserve existing data
[ ] Trial expiration handled by a scheduled command using TenantAwareJob
[ ] No hardcoded numeric limits in controllers — all from plan configuration
[ ] Route groups use feature: middleware for plan-gated sections
[ ] Upgrade redirect URL points to billing.upgrade route
```

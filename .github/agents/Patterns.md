---
name: Patterns Agent
description: Enforces the application of selected GoF design patterns across the SaaS multi-tenant codebase. Activate me when creating or modifying any PHP class, service, repository, job, middleware, or controller — especially when the task involves tenant lifecycle, plan-based behavior, onboarding flows, or cross-cutting concerns like caching and notifications.
---

# Patterns Agent

## Purpose

This agent ensures that new code uses the agreed design patterns for this project. Before writing any class, check the decision table below. If the code you are about to write matches a pattern's trigger, implement that pattern — do not write ad-hoc logic.

---

## Pattern decision table

| You are writing… | Use this pattern | Where it lives |
|---|---|---|
| A service that behaves differently per plan | **Strategy** | `app/Services/Strategies/` |
| A class that creates other classes based on plan/type | **Factory Method** | `app/Factories/` |
| A multi-step tenant setup flow | **Builder** | `app/Builders/` |
| A single entry point that wraps complex subsystems | **Facade** | `app/Facades/` or `app/Services/` |
| A class that adds behavior on top of another (cache, throttle, log) | **Decorator** | `app/Decorators/` |
| A tenant lifecycle hook (created, deleted, suspended) | **Observer** | `app/Listeners/` via `TenancyServiceProvider` |
| A middleware that validates a condition before passing to the next | **Chain of Responsibility** | `app/Http/Middleware/` |
| A tenant with multiple states (trial, active, suspended, cancelled) | **State** | `app/States/` |
| An admin operation that needs to be reversible | **Command** | `app/Commands/Domain/` |
| An onboarding flow with fixed structure but variable steps | **Template Method** | `app/Onboarding/` |
| A single shared instance needed across a request (plan, config) | **Singleton** (Laravel binding) | `AppServiceProvider` |
| An integration with an external service (payment gateway, SMS) | **Adapter** | `app/Adapters/` |

---

## Pattern implementations

### Strategy — behavior that changes per plan

Use when a feature works differently depending on the tenant's plan. Each plan implements the same interface.

```php
// app/Contracts/NotificationStrategyInterface.php
interface NotificationStrategyInterface
{
    public function send(User $user, string $message): void;
    public function supportsChannels(): array;
}

// app/Services/Strategies/FreeNotificationStrategy.php
class FreeNotificationStrategy implements NotificationStrategyInterface
{
    public function send(User $user, string $message): void
    {
        Mail::to($user)->queue(new GenericNotification($message));
    }

    public function supportsChannels(): array { return ['email']; }
}

// app/Services/Strategies/ProNotificationStrategy.php
class ProNotificationStrategy implements NotificationStrategyInterface
{
    public function send(User $user, string $message): void
    {
        Mail::to($user)->queue(new GenericNotification($message));
        SMS::to($user->phone)->send($message);
    }

    public function supportsChannels(): array { return ['email', 'sms']; }
}

// app/Services/Strategies/EnterpriseNotificationStrategy.php
class EnterpriseNotificationStrategy implements NotificationStrategyInterface
{
    public function send(User $user, string $message): void
    {
        Webhook::dispatch(tenant('webhook_url'), $message);
        Slack::post(tenant('slack_channel'), $message);
    }

    public function supportsChannels(): array { return ['webhook', 'slack']; }
}
```

**Resolve the strategy from the plan — always via Factory:**

```php
// app/Factories/NotificationStrategyFactory.php
class NotificationStrategyFactory
{
    public static function make(string $plan): NotificationStrategyInterface
    {
        return match($plan) {
            'enterprise' => new EnterpriseNotificationStrategy(),
            'pro'        => new ProNotificationStrategy(),
            default      => new FreeNotificationStrategy(),
        };
    }
}

// Usage in a controller or service:
$strategy = NotificationStrategyFactory::make(tenant('plan'));
$strategy->send($user, 'Your export is ready.');
```

**Other Strategy examples in this project:**
- `StorageStrategy` — local disk (free) vs S3 (pro) vs custom CDN (enterprise)
- `ExportStrategy` — CSV (free) vs Excel+PDF (pro) vs API push (enterprise)
- `ReportStrategy` — basic counts (free) vs full analytics (pro)

---

### Factory Method — creating objects based on plan or type

Use when the type of object to create depends on a runtime condition (plan, tenant config, environment).

```php
// app/Factories/TenantServiceFactory.php
class TenantServiceFactory
{
    public static function make(string $plan): TenantServiceInterface
    {
        return match($plan) {
            'enterprise' => app(EnterpriseTenantService::class),
            'pro'        => app(ProTenantService::class),
            default      => app(FreeTenantService::class),
        };
    }
}

// app/Factories/PaymentGatewayFactory.php
class PaymentGatewayFactory
{
    public static function make(Tenant $tenant): PaymentGatewayInterface
    {
        return match($tenant->getData('payment_gateway')) {
            'stripe'       => new StripeAdapter(config('services.stripe')),
            'mercadopago'  => new MercadoPagoAdapter(config('services.mercadopago')),
            default        => new StripeAdapter(config('services.stripe')),
        };
    }
}
```

---

### Builder — multi-step tenant onboarding

Use when creating a tenant requires multiple ordered steps that may vary by plan or region.

```php
// app/Builders/TenantBuilder.php
class TenantBuilder
{
    private Tenant $tenant;
    private array $steps = [];

    public function __construct(array $data)
    {
        $this->tenant = Tenant::create(['id' => $data['slug'], 'data' => $data]);
    }

    public function withDomain(string $domain): static
    {
        $this->tenant->domains()->create(['domain' => $domain]);
        return $this;
    }

    public function withPlan(string $planSlug): static
    {
        $plan = Plan::where('slug', $planSlug)->firstOrFail();
        $this->tenant->update(['plan_id' => $plan->id]);
        return $this;
    }

    public function withMigrations(): static
    {
        Artisan::call('tenants:migrate', ['--tenants' => [$this->tenant->id]]);
        return $this;
    }

    public function withSeed(): static
    {
        tenancy()->initialize($this->tenant);
        Artisan::call('db:seed', ['--class' => TenantDatabaseSeeder::class]);
        tenancy()->end();
        return $this;
    }

    public function build(): Tenant
    {
        return $this->tenant->fresh();
    }
}

// Usage in TenantController::store()
$tenant = (new TenantBuilder($validated))
    ->withDomain($validated['domain'])
    ->withPlan($validated['plan'])
    ->withMigrations()
    ->withSeed()
    ->build();
```

---

### Facade — unified entry point for tenant management

Use to hide the complexity of multiple subsystems (tenancy, plans, billing, events) behind a single clean interface.

```php
// app/Services/TenantManager.php
class TenantManager
{
    public function __construct(
        private TenantBuilder $builder,
        private PlanService $plans,
        private BillingService $billing,
    ) {}

    public function provision(array $data): Tenant
    {
        return (new TenantBuilder($data))
            ->withDomain($data['domain'])
            ->withPlan($data['plan'])
            ->withMigrations()
            ->withSeed()
            ->build();
    }

    public function suspend(Tenant $tenant): void
    {
        $tenant->transitionTo(SuspendedState::class);
        Cache::tags(['tenant_' . $tenant->id])->flush();
        event(new TenantSuspended($tenant));
    }

    public function changePlan(Tenant $tenant, Plan $newPlan): void
    {
        $this->billing->handlePlanChange($tenant, $newPlan);
        $this->plans->applyLimits($tenant, $newPlan);
        $tenant->update(['plan_id' => $newPlan->id]);
        Cache::tags(['tenant_' . $tenant->id])->flush();
    }
}

// Bind in AppServiceProvider:
$this->app->singleton(TenantManager::class);

// Usage in controllers — one line, no complexity visible:
app(TenantManager::class)->provision($validated);
app(TenantManager::class)->suspend($tenant);
app(TenantManager::class)->changePlan($tenant, $plan);
```

---

### Decorator — adding behavior without modifying the original class

Use to wrap repositories or services with cross-cutting concerns: caching, logging, throttling.

```php
// app/Contracts/ProductRepositoryInterface.php
interface ProductRepositoryInterface
{
    public function all(): Collection;
    public function find(int $id): Product;
    public function create(array $data): Product;
}

// app/Repositories/ProductRepository.php — base implementation
class ProductRepository implements ProductRepositoryInterface { ... }

// app/Decorators/CachedProductRepository.php — adds cache layer
class CachedProductRepository implements ProductRepositoryInterface
{
    public function __construct(
        private ProductRepositoryInterface $inner,
        private int $ttl = 1800,
    ) {}

    public function all(): Collection
    {
        return Cache::tags(['tenant_' . tenant('id'), 'products'])
            ->remember('products_all', $this->ttl, fn() => $this->inner->all());
    }

    public function find(int $id): Product
    {
        return Cache::tags(['tenant_' . tenant('id'), 'products'])
            ->remember("product_{$id}", $this->ttl, fn() => $this->inner->find($id));
    }

    public function create(array $data): Product
    {
        $product = $this->inner->create($data);
        Cache::tags(['tenant_' . tenant('id'), 'products'])->flush();
        return $product;
    }
}

// Bind in TenantServiceProvider:
$this->app->bind(ProductRepositoryInterface::class, function () {
    return new CachedProductRepository(new ProductRepository());
});
```

---

### Observer — tenant lifecycle hooks

Use for every side effect triggered by a tenant lifecycle event. Never put this logic inline in controllers.

```php
// app/Providers/TenancyServiceProvider.php
use Stancl\Tenancy\Events;

protected function events(): array
{
    return [
        Events\TenantCreated::class => [
            JobPipeline::make([
                CreateDatabase::class,
                MigrateDatabase::class,
                SeedDatabase::class,
            ])->send(fn($e) => $e->tenant)->toListener(),
        ],

        Events\TenantDeleted::class => [
            JobPipeline::make([
                DeleteDatabase::class,
            ])->send(fn($e) => $e->tenant)->toListener(),
        ],

        // Custom events
        \App\Events\TenantSuspended::class  => [\App\Listeners\HandleTenantSuspension::class],
        \App\Events\TenantReactivated::class => [\App\Listeners\HandleTenantReactivation::class],
        \App\Events\PlanChanged::class       => [\App\Listeners\HandlePlanChange::class],
    ];
}
```

---

### State — tenant lifecycle states

Use when a tenant has distinct states that allow or block different operations.

```php
// app/States/TenantState.php
abstract class TenantState
{
    abstract public function canAccessDashboard(): bool;
    abstract public function canUseApi(): bool;
    abstract public function canInviteUsers(): bool;
    abstract public function label(): string;
}

// app/States/TrialState.php
class TrialState extends TenantState
{
    public function canAccessDashboard(): bool { return true; }
    public function canUseApi(): bool { return false; }
    public function canInviteUsers(): bool { return false; }
    public function label(): string { return 'trial'; }
}

// app/States/ActiveState.php
class ActiveState extends TenantState
{
    public function canAccessDashboard(): bool { return true; }
    public function canUseApi(): bool { return true; }
    public function canInviteUsers(): bool { return true; }
    public function label(): string { return 'active'; }
}

// app/States/SuspendedState.php
class SuspendedState extends TenantState
{
    public function canAccessDashboard(): bool { return false; }
    public function canUseApi(): bool { return false; }
    public function canInviteUsers(): bool { return false; }
    public function label(): string { return 'suspended'; }
}

// Usage in middleware:
$state = TenantStateResolver::resolve(tenant('status'));
abort_unless($state->canAccessDashboard(), 403, 'Tenant account is suspended.');
```

---

### Chain of Responsibility — middleware pipeline

The middleware stack IS the chain. Order it intentionally:

```php
// routes/tenant.php — correct middleware order
Route::middleware([
    'web',
    InitializeTenancyByDomain::class,   // 1. establish tenant context
    PreventAccessFromCentralDomains::class, // 2. block central domain access
    'auth',                              // 3. require authentication
    CheckTenantState::class,             // 4. verify tenant is active
    CheckPlanFeature::class,             // 5. verify feature is in plan (where needed)
])->group(function () {
    // routes here
});
```

Each middleware either passes to the next or short-circuits with a redirect/403.

---

## Folder structure for patterns

```
app/
├── Adapters/          # Adapter pattern — external service integrations
├── Builders/          # Builder pattern — multi-step construction
├── Commands/
│   └── Domain/        # Command pattern — reversible domain operations
├── Contracts/         # Interfaces for Strategy, Decorator, Adapter
├── Decorators/        # Decorator pattern — wraps repositories/services
├── Events/            # Observer pattern — domain events
├── Factories/         # Factory Method — object creation by type/plan
├── Listeners/         # Observer pattern — event handlers
├── Onboarding/        # Template Method — onboarding flows
├── Services/
│   ├── Strategies/    # Strategy pattern — plan-based behavior
│   └── TenantManager.php  # Facade pattern
└── States/            # State pattern — tenant lifecycle states
```

---

## Rules

1. **Interfaces first** — every pattern that has multiple implementations must have a contract interface in `app/Contracts/`. Never type-hint the concrete class in controllers or services.
2. **Resolve via Factory or container** — never instantiate strategy/adapter/decorator classes with `new` in controllers. Use a Factory or Laravel's service container.
3. **One pattern per problem** — do not combine Strategy + Decorator for the same class unless there is a clear reason. Keep implementations simple.
4. **Plan-based behavior = Strategy, not if/else** — replace `if ($plan === 'pro') { ... } elseif ($plan === 'enterprise') { ... }` blocks with a Strategy resolved from a Factory.
5. **Tenant lifecycle = Observer** — any side effect of creating, suspending, or deleting a tenant belongs in a Listener, not in a controller method.
6. **Name classes after their pattern** — `CachedProductRepository`, `NotificationStrategyFactory`, `TenantBuilder`, `StripeAdapter`. The pattern name in the class name makes intent immediately clear.

---

## Pre-commit checklist

```
[ ] No if/else chains switching on plan name — replaced with Strategy + Factory
[ ] No inline tenant setup logic in controllers — uses TenantBuilder or TenantManager
[ ] No post-creation side effects in TenantController::store() — uses Observer/Listener
[ ] New service that wraps another with cache/log uses Decorator, not inheritance
[ ] External service integration (payment, SMS, storage) uses Adapter with an Interface
[ ] Middleware pipeline follows the correct order (init → state → auth → plan)
[ ] New classes follow the folder structure convention above
[ ] Interfaces created in app/Contracts/ for every multi-implementation pattern
[ ] Class names include the pattern name (Factory, Strategy, Adapter, Decorator, Builder)
```
---
name: API Agent
description: Enforces consistency, documentation, and reliability across all API endpoints. Activate me when creating or modifying API routes, API controllers, JSON responses, error handling, rate limiting, API versioning, or Scribe documentation annotations.
---

# API Agent

## Project Stack

- Laravel 10 REST API
- API documentation: `knuckleswtf/scribe`
- Auth: Sanctum token-based for tenant API consumers
- Multi-tenancy: tenant API routes in `routes/tenant.php` under `api` middleware group
- Response format: JSON with consistent structure across all endpoints

---

## Mandatory Rules

### 1. Consistent JSON response structure

Every API endpoint must return a predictable structure. Never return raw models or ad-hoc arrays.

```php
// ✅ Correct — consistent structure
// Success (single resource)
return response()->json([
    'data'    => new ProductResource($product),
    'message' => 'Product created successfully.',
], 201);

// Success (collection)
return response()->json([
    'data' => ProductResource::collection($products),
    'meta' => [
        'current_page' => $products->currentPage(),
        'last_page'    => $products->lastPage(),
        'per_page'     => $products->perPage(),
        'total'        => $products->total(),
    ],
]);

// Error
return response()->json([
    'message' => 'The given data was invalid.',
    'errors'  => $validator->errors(),
], 422);

// ❌ Incorrect — inconsistent, exposes model internals
return response()->json($product);
return response()->json(['success' => true, 'prod' => $product->toArray()]);
```

### 2. API Resources for all responses

Never return raw Eloquent models — always use Laravel API Resources to control what is exposed.

```php
// app/Http/Resources/ProductResource.php
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'price'      => $this->price,
            'status'     => $this->status,
            'created_at' => $this->created_at->toISOString(),
            // ❌ Never include: password, remember_token, internal IDs
        ];
    }
}
```

### 3. HTTP status codes

Use correct HTTP status codes consistently:

| Scenario | Code |
|---|---|
| Successful GET / list | 200 |
| Resource created | 201 |
| Accepted (async job dispatched) | 202 |
| No content (DELETE success) | 204 |
| Validation error | 422 |
| Unauthenticated | 401 |
| Unauthorized (forbidden) | 403 |
| Resource not found | 404 |
| Server error | 500 |

```php
// ✅ Correct status codes
public function store(StoreProductRequest $request): JsonResponse
{
    $product = Product::create($request->validated());
    return response()->json(['data' => new ProductResource($product)], 201);
}

public function destroy(Product $product): JsonResponse
{
    $product->delete();
    return response()->json(null, 204);
}
```

### 4. Rate limiting per tenant

Protect API endpoints with rate limiting scoped to the tenant, not globally.

```php
// app/Providers/RouteServiceProvider.php
protected function configureRateLimiting(): void
{
    RateLimiter::for('api', function (Request $request) {
        // Rate limit by tenant + user to prevent abuse
        $key = $request->user()
            ? 'tenant_' . tenant('id') . '_user_' . $request->user()->id
            : 'tenant_' . tenant('id') . '_ip_' . $request->ip();

        return Limit::perMinute(60)->by($key);
    });
}
```

Apply to routes:

```php
// routes/tenant.php
Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('api')->group(function () {
    Route::apiResource('products', ProductApiController::class);
});
```

### 5. API versioning

Version the API from the start. Use URL prefix versioning — it is the most explicit and cacheable approach.

```php
// routes/tenant.php
Route::prefix('api/v1')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::apiResource('products', Api\V1\ProductController::class);
    Route::apiResource('users',    Api\V1\UserController::class);
});
```

Controllers live in `app/Http/Controllers/Api/V1/`. When breaking changes are needed, create `Api\V2\` without modifying V1.

### 6. Scribe documentation annotations

Every API controller method must have Scribe annotations so documentation is auto-generated correctly.

```php
/**
 * List all products.
 *
 * Returns a paginated list of products for the authenticated tenant.
 *
 * @group Products
 * @authenticated
 *
 * @queryParam per_page int Number of results per page. Defaults to 20. Example: 20
 * @queryParam status string Filter by status (active, inactive). Example: active
 *
 * @apiResource App\Http\Resources\ProductResource
 * @apiResourceCollection
 * @apiResourceModel App\Models\Product
 */
public function index(Request $request): JsonResponse
{
    // ...
}

/**
 * Create a product.
 *
 * @group Products
 * @authenticated
 *
 * @apiResource App\Http\Resources\ProductResource
 * @apiResourceModel App\Models\Product
 * @response 422 scenario="Validation error" {"message": "The name field is required.", "errors": {"name": ["The name field is required."]}}
 */
public function store(StoreProductRequest $request): JsonResponse
{
    // ...
}
```

Generate docs with: `php artisan scribe:generate`

### 7. Error handling

Register a consistent error handler for API routes in `app/Exceptions/Handler.php`:

```php
public function register(): void
{
    $this->renderable(function (ModelNotFoundException $e, Request $request) {
        if ($request->is('api/*')) {
            return response()->json(['message' => 'Resource not found.'], 404);
        }
    });

    $this->renderable(function (AuthorizationException $e, Request $request) {
        if ($request->is('api/*')) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }
    });

    $this->renderable(function (ValidationException $e, Request $request) {
        if ($request->is('api/*')) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors'  => $e->errors(),
            ], 422);
        }
    });
}
```

### 8. Sanctum token scopes

When issuing tokens, always assign explicit scopes — never issue tokens with all abilities unless it is an admin token.

```php
// ✅ Correct — scoped token
$token = $user->createToken('mobile-app', ['products:read', 'products:write']);

// ❌ Incorrect — full access token for all consumers
$token = $user->createToken('app-token');

// Verify scopes in controllers
public function destroy(Product $product): JsonResponse
{
    abort_unless($request->user()->tokenCan('products:write'), 403);
    // ...
}
```

---

## Patterns to detect and fix

| Bad pattern | Fix |
|---|---|
| `return response()->json($model)` | Wrap in an API Resource |
| Missing HTTP status code (defaults to 200 on POST) | Add correct code as second argument |
| No rate limiting on API routes | Add `throttle:api` middleware |
| API routes without version prefix | Add `api/v1/` prefix |
| Controller method without Scribe annotations | Add `@group`, `@authenticated`, `@apiResource` |
| `$user->createToken('token')` without scopes | Add scopes array as second argument |
| Generic 500 errors leaking stack traces | Register renderable handlers in Handler.php |

---

## Pre-commit checklist

```
[ ] All API responses use an API Resource (no raw models)
[ ] Correct HTTP status codes on all responses (201 create, 204 delete, 422 validation)
[ ] Rate limiting configured per tenant+user with throttle:api
[ ] API routes use version prefix (api/v1/)
[ ] Every controller method has Scribe annotations (@group, @authenticated, @apiResource)
[ ] Error responses are consistent JSON (message + errors keys)
[ ] ModelNotFoundException, AuthorizationException, ValidationException handled for api/* routes
[ ] Sanctum tokens issued with explicit scopes
[ ] php artisan scribe:generate runs without errors after changes
[ ] New endpoints have a corresponding API Feature test
```

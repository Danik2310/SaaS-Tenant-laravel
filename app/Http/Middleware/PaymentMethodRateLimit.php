<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PaymentMethodRateLimit
{
    /**
     * The rate limiter instance.
     */
    protected RateLimiter $limiter;

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $this->resolveRequestSignature($request);

        // Different limits for different operations
        $limits = $this->getLimitsForMethod($request->method(), $request->route()?->getName());

        foreach ($limits as $limit) {
            if ($this->limiter->tooManyAttempts($key.'_'.$limit['key'], $limit['max_attempts'])) {
                return $this->buildRateLimitResponse($request, $limit);
            }

            $this->limiter->hit($key.'_'.$limit['key'], $limit['decay_seconds']);
        }

        $response = $next($request);

        // Add rate limit headers to response
        $response->headers->set('X-RateLimit-Limit', $limits[0]['max_attempts'] ?? 60);
        $response->headers->set('X-RateLimit-Remaining', $this->limiter->remaining($key.'_'.$limits[0]['key'], $limits[0]['max_attempts']));

        return $response;
    }

    /**
     * Resolve request signature for rate limiting.
     */
    protected function resolveRequestSignature(Request $request): string
    {
        $userId = auth('admin')->id() ?? 'guest';
        $ip = $request->ip();
        $route = $request->route()?->getName() ?? $request->path();

        return sha1($userId.'|'.$ip.'|'.$route);
    }

    /**
     * Get rate limits based on HTTP method and route.
     */
    protected function getLimitsForMethod(string $method, ?string $routeName): array
    {
        $limits = [];

        // Base limits for all payment method operations
        $limits[] = [
            'key' => 'base',
            'max_attempts' => 100, // 100 requests per hour
            'decay_seconds' => 3600,
        ];

        // Specific limits based on operation type
        switch ($method) {
            case 'POST': // Create
                $limits[] = [
                    'key' => 'create',
                    'max_attempts' => 10, // 10 creates per hour
                    'decay_seconds' => 3600,
                ];
                break;

            case 'PUT': // Update
                $limits[] = [
                    'key' => 'update',
                    'max_attempts' => 50, // 50 updates per hour
                    'decay_seconds' => 3600,
                ];
                break;

            case 'PATCH': // Toggle active (less restrictive than full update)
                $limits[] = [
                    'key' => 'toggle',
                    'max_attempts' => 100, // 100 toggles per hour
                    'decay_seconds' => 3600,
                ];
                break;

            case 'DELETE': // Delete
                $limits[] = [
                    'key' => 'delete',
                    'max_attempts' => 5, // 5 deletes per hour
                    'decay_seconds' => 3600,
                ];
                break;

            case 'GET': // Read operations
                $limits[] = [
                    'key' => 'read',
                    'max_attempts' => 200, // 200 reads per hour
                    'decay_seconds' => 3600,
                ];
                break;
        }

        // Stricter limits for sensitive operations
        if (str_contains($routeName ?? '', 'payment-method') && in_array($method, ['POST', 'PUT', 'DELETE'])) {
            $limits[] = [
                'key' => 'sensitive',
                'max_attempts' => 20, // 20 sensitive operations per hour
                'decay_seconds' => 3600,
            ];
        }

        return $limits;
    }

    /**
     * Build rate limit exceeded response.
     */
    protected function buildRateLimitResponse(Request $request, array $limit): Response
    {
        $retryAfter = $this->limiter->availableIn(
            $this->resolveRequestSignature($request).'_'.$limit['key']
        );

        return response()->json([
            'error' => 'Too Many Requests',
            'message' => 'Rate limit exceeded for payment method operations.',
            'retry_after' => $retryAfter,
            'limit' => $limit['max_attempts'],
            'limit_type' => $limit['key'],
        ], 429)->withHeaders([
            'Retry-After' => $retryAfter,
            'X-RateLimit-Limit' => $limit['max_attempts'],
            'X-RateLimit-Remaining' => 0,
            'X-RateLimit-Reset' => now()->addSeconds($retryAfter)->timestamp,
        ]);
    }
}

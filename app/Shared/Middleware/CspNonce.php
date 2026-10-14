<?php

declare(strict_types=1);

namespace App\Shared\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Generates a per-request Content-Security-Policy nonce.
 *
 * Flow:
 *  1. Generate a random nonce.
 *  2. Register it with Vite so emitted script/style tags carry `nonce="…"`.
 *  3. Share it as `csp_nonce` for the root view.
 *
 * Pairs with {@see SecurityHeaders}, which emits the matching CSP header.
 */
class CspNonce
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Str::random(32);

        Vite::useCspNonce($nonce);
        view()->share('csp_nonce', $nonce);

        return $next($request);
    }
}

<?php

declare(strict_types=1);

namespace App\Shared\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

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

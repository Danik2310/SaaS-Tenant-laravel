<?php

declare(strict_types=1);

namespace App\Shared\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds security headers to every `web` response (runs on the way out, so it
 * sees the finished response).
 *
 * Flow:
 *  1. Set `nosniff`, frame-deny, XSS protection, referrer-policy and HSTS
 *     headers.
 *  2. Emit a Content-Security-Policy — a permissive dev CSP in local/testing,
 *     otherwise a nonce-based production CSP using Vite's nonce.
 *
 * @see CspNonce
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        if (app()->environment('local', 'testing')) {
            $response->headers->set('Content-Security-Policy', $this->devCsp());
        } else {
            $nonce = Vite::cspNonce() ?: Str::random(32);
            $response->headers->set('Content-Security-Policy', $this->productionCsp($nonce));
        }

        return $response;
    }

    private function devCsp(): string
    {
        $connectSources = ["'self'", 'ws://127.0.0.1:5174', 'http://127.0.0.1:5174'];
        $reverb = $this->reverbConnectSource();

        if ($reverb !== null) {
            $connectSources[] = $reverb;

            // The browser may be opened on either loopback spelling; allow the
            // counterpart so VITE_REVERB_HOST and the address bar can disagree.
            if (str_contains($reverb, '://localhost')) {
                $connectSources[] = str_replace('://localhost', '://127.0.0.1', $reverb);
            } elseif (str_contains($reverb, '://127.0.0.1')) {
                $connectSources[] = str_replace('://127.0.0.1', '://localhost', $reverb);
            }
        }

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' http://127.0.0.1:5174",
            'connect-src '.implode(' ', $connectSources),
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
            "font-src 'self' data: https://fonts.bunny.net",
            "img-src 'self' data:",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ]);
    }

    private function productionCsp(string $nonce): string
    {
        $connectSources = ["'self'"];
        $reverb = $this->reverbConnectSource();

        if ($reverb !== null) {
            $connectSources[] = $reverb;
        }

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            'connect-src '.implode(' ', $connectSources),
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
            "font-src 'self' data: https://fonts.bunny.net",
            "img-src 'self' data:",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ]);
    }

    /**
     * WebSocket origin for the Echo client, derived from the same Reverb
     * connection the server broadcasts through.
     */
    private function reverbConnectSource(): ?string
    {
        $options = (array) config('broadcasting.connections.reverb.options', []);
        $host = trim((string) ($options['host'] ?? ''));

        if ($host === '') {
            return null;
        }

        $scheme = ($options['scheme'] ?? 'https') === 'https' ? 'wss' : 'ws';
        $port = $options['port'] ?? null;

        return $scheme.'://'.$host.($port ? ':'.$port : '');
    }
}

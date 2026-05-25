<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckImpersonationExpiry
{
    public function handle(Request $request, Closure $next)
    {
        $impersonating = session('impersonate_tenant');
        $startedAt = session('impersonate_started_at');

        if ($impersonating && $startedAt) {
            $ttl = config('impersonation.ttl', 60);
            $expiresAt = $startedAt + ($ttl * 60);

            if (now()->timestamp > $expiresAt) {
                session()->forget(['impersonate_tenant', 'impersonate_started_at']);

                activity('impersonation')
                    ->causedBy(Auth::guard('admin')->user())
                    ->log('Impersonation session expired');
            }
        }

        return $next($request);
    }
}

<?php

namespace App\Shared\Middleware;

use App\Shared\Support\ImpersonationState;
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
                ImpersonationState::clear();

                activity('impersonation')
                    ->causedBy(Auth::guard('admin')->user())
                    ->log('Impersonation session expired');
            }
        }

        return $next($request);
    }
}

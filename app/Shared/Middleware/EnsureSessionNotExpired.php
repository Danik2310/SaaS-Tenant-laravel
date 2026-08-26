<?php

namespace App\Shared\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureSessionNotExpired
{
    public function handle(Request $request, Closure $next, ?string $guard = null)
    {
        $guardName = $guard ?: $this->getDefaultGuard();
        $user = Auth::guard($guardName)->user();

        if (! $user) {
            return $next($request);
        }

        $absoluteLifetime = (int) config('session.absolute_lifetime', 720);
        $lastActivity = session('last_activity');

        if ($lastActivity && (now()->timestamp - $lastActivity) > ($absoluteLifetime * 60)) {
            Auth::guard($guardName)->logout();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Session expired.'], 401);
            }

            return redirect()->route('login');
        }

        session(['last_activity' => now()->timestamp]);

        return $next($request);
    }

    protected function getDefaultGuard(): string
    {
        return config('auth.defaults.guard', 'web');
    }
}

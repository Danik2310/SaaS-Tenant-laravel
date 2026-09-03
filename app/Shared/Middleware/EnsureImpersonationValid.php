<?php

namespace App\Shared\Middleware;

use App\Shared\Support\ImpersonatedAdmin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureImpersonationValid
{
    /**
     * Validate an active impersonation (god mode) session on the tenant side.
     *
     * When no impersonation is present this middleware is a no-op. When a
     * session exists it guarantees the session has not expired, still belongs
     * to the requested tenant, surfaces a synthetic admin identity for the
     * tenant app, and enforces read-only access.
     */
    public function handle(Request $request, Closure $next)
    {
        $impersonation = session('impersonation');

        if (! $impersonation || ! is_array($impersonation)) {
            return $next($request);
        }

        $startedAt = (int) ($impersonation['started_at'] ?? 0);
        $ttl = (int) ($impersonation['ttl'] ?? config('impersonation.ttl', 60));

        if ($startedAt + ($ttl * 60) < now()->getTimestamp()) {
            session()->forget('impersonation');

            activity('impersonation')
                ->log('Impersonation session expired');

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Impersonation session expired.'], 401);
            }

            return redirect(route('admin.login'));
        }

        if ((string) ($impersonation['tenant_id'] ?? '') !== (string) tenant('id')) {
            session()->forget('impersonation');

            abort(403, 'Impersonation session does not match this tenant.');
        }

        if (config('impersonation.read_only', true) && ! $this->isReadOnly($request)) {
            abort(403, 'Impersonation sessions are read-only.');
        }

        $this->setImpersonatedAuth($impersonation);

        return $next($request);
    }

    protected function isReadOnly(Request $request): bool
    {
        if ($request->isMethod('GET') || $request->isMethod('HEAD') || $request->isMethod('OPTIONS')) {
            return true;
        }

        if ($request->routeIs(['impersonation.stop', 'god-mode.stop'])) {
            return true;
        }

        return false;
    }

    protected function setImpersonatedAuth(array $impersonation): void
    {
        $identity = new ImpersonatedAdmin([
            'id' => $impersonation['admin_id'] ?? null,
            'name' => $impersonation['admin_name'] ?? 'Administrator',
            'email' => $impersonation['admin_email'] ?? '',
        ]);

        Auth::guard('web')->setUser($identity);
    }
}

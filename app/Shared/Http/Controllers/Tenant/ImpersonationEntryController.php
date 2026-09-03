<?php

namespace App\Shared\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Shared\Support\ImpersonationToken;
use Illuminate\Http\Request;

/**
 * @group Tenant Impersonation (God Mode)
 *
 * Tenant-side entry and exit for impersonated administrator sessions.
 */
class ImpersonationEntryController extends Controller
{
    /**
     * Enter an impersonation (god mode) session for the current tenant.
     *
     * The token is signed with the shared application key by the central
     * admin app. It must be unexpired and must reference the exact tenant
     * resolved from the current domain.
     */
    public function enter(Request $request)
    {
        if ($request->session()->has('impersonation')) {
            return redirect()->route('tenant.dashboard');
        }

        $payload = ImpersonationToken::verify((string) $request->query('impersonate_token', ''));

        if ($payload === null) {
            abort(403, 'Invalid or expired impersonation link.');
        }

        if (! tenant() instanceof Tenant || (string) tenant('id') !== (string) ($payload['tenant_id'] ?? '')) {
            abort(403, 'Impersonation link does not match this tenant.');
        }

        $tokenTtl = (int) config('impersonation.token_ttl', 300);

        if (time() - (int) ($payload['iat'] ?? 0) > $tokenTtl) {
            abort(403, 'Impersonation link has expired.');
        }

        session([
            'impersonation' => [
                'admin_id' => (string) ($payload['admin_id'] ?? ''),
                'admin_name' => (string) ($payload['admin_name'] ?? 'Administrator'),
                'admin_email' => (string) ($payload['admin_email'] ?? ''),
                'tenant_id' => (string) tenant('id'),
                'started_at' => time(),
                'ttl' => (int) config('impersonation.ttl', 60),
            ],
        ]);

        activity('impersonation')
            ->withProperties(['admin_name' => $payload['admin_name'] ?? null, 'tenant_id' => tenant('id')])
            ->log('Entered god mode session');

        return redirect()->route('tenant.dashboard');
    }

    /**
     * Exit the current impersonation session and return to the admin app.
     */
    public function stop(Request $request)
    {
        $impersonation = $request->session()->get('impersonation');

        $request->session()->forget('impersonation');

        if ($impersonation) {
            activity('impersonation')
                ->withProperties(['admin_name' => $impersonation['admin_name'] ?? null, 'tenant_id' => tenant('id')])
                ->log('Left god mode session');
        }

        $centralUrl = config('tenancy.central_domains.0')
            ?? parse_url(config('app.url'), PHP_URL_HOST);

        return redirect($centralUrl);
    }
}

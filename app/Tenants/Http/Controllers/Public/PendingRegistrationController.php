<?php

declare(strict_types=1);

namespace App\Tenants\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Tenants\Support\TenantVerificationChannel;
use App\Tenants\Support\TenantVerificationCooldown;
use App\Tenants\Support\TenantVerificationMailer;
use App\Tenants\Support\TenantVerificationUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The confirmation-waiting screen for self-service signups.
 *
 * The signed URL is the credential: the owner has no session yet, and the
 * pending screen deliberately offers no way into the workspace. The screen can
 * be re-opened from the verification mail, polls its own `verified` prop, and
 * exposes a rate-limited resend action.
 *
 * Tenancy is started only to read the tenant-side owner row and is always ended
 * again when this controller started it; test helpers may already have a tenant
 * initialized, and ending theirs would break the rest of the test.
 */
class PendingRegistrationController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $tenant = Tenant::find((string) $request->query('tenant', ''));

        if ($tenant === null) {
            return redirect()->route('register.tenant');
        }

        $email = $this->email($request, $tenant);

        if ($email === '') {
            return redirect()->route('register.tenant');
        }

        $ownerEmail = strtolower(trim((string) $tenant->email));

        // The central projection lets a verified owner skip tenancy entirely.
        // A different address still falls through to the tenant-side user row,
        // which stays authoritative.
        $verified = $email === $ownerEmail && $tenant->hasVerifiedOwnerEmail();
        $tenantReady = $verified;
        $startedTenancy = ! tenancy()->initialized;

        if (! $verified) {
            try {
                if ($startedTenancy) {
                    tenancy()->initialize($tenant);
                }

                $user = User::where('email', $email)->first();
                $tenantReady = $user !== null;
                $verified = $user !== null && $user->hasVerifiedEmail();
            } catch (\Throwable $e) {
                $tenantReady = false;

                Log::warning('Could not read the pending registration owner', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
            } finally {
                if ($startedTenancy && tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        }

        return Inertia::render('Auth/TenantRegisterSuccess', [
            'domain' => (string) $tenant->domain,
            'plan' => (string) ($tenant->plan?->name ?? 'Trial'),
            'trialDays' => $tenant->status === 'Trial'
                ? (int) config('tenancy.trial_days', 14)
                : null,
            'email' => $email,
            'verified' => $verified,
            'verificationChannel' => TenantVerificationChannel::for($tenant, $email),
            'tenantReady' => $tenantReady,
            'resendUrl' => TenantVerificationUrl::resendFor($tenant, $email),
            'loginUrl' => TenantVerificationUrl::loginFor($tenant),
            'cooldownSeconds' => TenantVerificationCooldown::remaining($tenant, $email),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function resend(Request $request): RedirectResponse
    {
        $tenant = Tenant::find((string) $request->query('tenant', ''));

        if ($tenant === null) {
            return redirect()->route('register.tenant');
        }

        $email = $this->email($request, $tenant);

        if ($email === '') {
            return redirect()->route('register.tenant');
        }

        $pendingUrl = TenantVerificationUrl::pendingFor($tenant, $email);

        if ($pendingUrl === null) {
            return redirect()->route('register.tenant');
        }

        if (TenantVerificationCooldown::isActive($tenant, $email)) {
            return redirect()->to($pendingUrl)->with('status', 'verification-cooldown');
        }

        $sent = false;
        $startedTenancy = ! tenancy()->initialized;

        try {
            if ($startedTenancy) {
                tenancy()->initialize($tenant);
            }

            $user = User::where('email', $email)->first();

            if ($user === null) {
                return redirect()->to($pendingUrl)->with('status', 'verification-preparing');
            }

            if ($user->hasVerifiedEmail()) {
                return redirect()->to($pendingUrl)->with('status', 'already-verified');
            }

            $sent = TenantVerificationMailer::send($user, $tenant);
        } catch (\Throwable $e) {
            Log::warning('Could not resend the tenant verification email', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->to($pendingUrl)->with('status', 'verification-preparing');
        } finally {
            if ($startedTenancy && tenancy()->initialized) {
                tenancy()->end();
            }
        }

        if (! $sent) {
            return redirect()->to($pendingUrl)->with('status', 'verification-failed');
        }

        TenantVerificationCooldown::start($tenant, $email);

        return redirect()->to($pendingUrl)->with('status', 'verification-link-sent');
    }

    private function email(Request $request, Tenant $tenant): string
    {
        return strtolower(trim((string) $request->query('email', $tenant->email)));
    }
}

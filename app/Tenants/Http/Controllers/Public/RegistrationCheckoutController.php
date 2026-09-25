<?php

declare(strict_types=1);

namespace App\Tenants\Http\Controllers\Public;

use App\Billing\Contracts\PaymentGatewayInterface;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Shared\Contracts\PublicPlanCatalogInterface;
use App\Tenants\Contracts\TenantManagerInterface;
use App\Tenants\Jobs\CreateTenantAdminUser;
use App\Tenants\Support\SubdomainGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Response;

/**
 * Completes a paid-plan self-service signup only after the hosted checkout
 * reports a successful payment. Provisioning (tenant database, domain, admin
 * user, paid subscription) intentionally happens here and never in store().
 */
class RegistrationCheckoutController extends Controller
{
    private const PENDING_REGISTRATION_KEY = 'pending_tenant_registration';

    public function __construct(
        private TenantManagerInterface $tenantManager,
        private SubdomainGenerator $subdomainGenerator,
        private PublicPlanCatalogInterface $publicPlans,
    ) {}

    public function success(Request $request)
    {
        $sessionId = (string) $request->query('session_id', '');

        if ($sessionId === '') {
            return redirect()->route('register.tenant');
        }

        $session = app(PaymentGatewayInterface::class)->retrieveCheckoutSession($sessionId);

        if (($session['payment_status'] ?? '') !== 'paid') {
            return redirect()->route('register.tenant');
        }

        $pending = $request->session()->pull(self::PENDING_REGISTRATION_KEY);

        if (! is_array($pending)
            || ($pending['token'] ?? null) !== ($session['metadata']['pending_token'] ?? null)) {
            return redirect()->route('register.tenant');
        }

        $validated = $pending['payload'] ?? [];
        $planSlug = (string) ($pending['plan'] ?? '');

        if (! is_array($validated) || empty($validated['email']) || $planSlug === '') {
            return redirect()->route('register.tenant');
        }

        $existing = Tenant::withTrashed()->where('email', $validated['email'])->first();

        if ($existing) {
            $domain = (string) ($existing->domains()->first()?->domain ?? '');

            return $this->successResponse($domain, $validated, $existing);
        }

        $selectedPlan = $this->publicPlans->activePlanBySlug($planSlug);

        if ($selectedPlan === null || (float) $selectedPlan->price <= 0) {
            return redirect()->route('register.tenant');
        }

        $domain = $this->subdomainGenerator->generate($validated['company_name']);

        try {
            $tenant = $this->tenantManager->provision([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'domain' => $domain,
                'plan' => $planSlug,
                'company_name' => $validated['company_name'],
                'first_name' => $validated['first_name'] ?? null,
                'last_name' => $validated['last_name'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'address_line1' => $validated['address_line1'] ?? null,
                'address_line2' => $validated['address_line2'] ?? null,
                'city' => $validated['city'] ?? null,
                'state' => $validated['state'] ?? null,
                'postal_code' => $validated['postal_code'] ?? null,
                'country' => $validated['country'] ?? null,
                'confirmed_payment' => true,
                'public_signup' => true,
            ]);
        } catch (\InvalidArgumentException $e) {
            Log::warning('Paid tenant registration failed after payment', [
                'email' => $validated['email'],
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('register.tenant');
        }

        dispatch(new CreateTenantAdminUser(
            $tenant,
            $this->adminDisplayName($validated),
            $validated['email'],
            $validated['password'],
        ));

        return $this->successResponse($domain, $validated, $tenant);
    }

    public function cancel(Request $request)
    {
        $request->session()->forget(self::PENDING_REGISTRATION_KEY);

        $plan = (string) $request->query('plan', '');

        return redirect()->route('register.tenant', $plan !== '' ? ['plan' => $plan] : []);
    }

    private function successResponse(string $domain, array $validated, Tenant $tenant): Response
    {
        return inertia('Auth/TenantRegisterSuccess', [
            'domain' => $domain,
            'loginUrl' => 'https://'.$domain.'/login',
            'plan' => (string) ($tenant->plan?->name ?? 'Trial'),
            'trialDays' => $tenant->status === 'Trial'
                ? (int) config('tenancy.trial_days', 14)
                : null,
            'email' => $validated['email'],
        ]);
    }

    private function adminDisplayName(array $validated): string
    {
        $contactName = trim(implode(' ', array_filter([
            $validated['first_name'] ?? null,
            $validated['last_name'] ?? null,
        ])));

        return $contactName !== '' ? $contactName : $validated['company_name'];
    }
}

<?php

declare(strict_types=1);

namespace App\Tenants\Http\Controllers\Public;

use App\Billing\Contracts\PaymentGatewayInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreTenantRegistrationRequest;
use App\Models\Plan;
use App\Shared\Contracts\PublicPlanCatalogInterface;
use App\Tenants\Contracts\TenantManagerInterface;
use App\Tenants\Jobs\CreateTenantAdminUser;
use App\Tenants\Support\SubdomainGenerator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class TenantRegistrationController extends Controller
{
    /**
     * Session key holding a pending paid-plan signup while the guest completes
     * the hosted checkout.
     */
    private const PENDING_REGISTRATION_KEY = 'pending_tenant_registration';

    public function __construct(
        private TenantManagerInterface $tenantManager,
        private SubdomainGenerator $subdomainGenerator,
        private PublicPlanCatalogInterface $publicPlans,
    ) {}

    public function create(): Response
    {
        $plans = $this->publicPlans->allSignupPlans();

        return Inertia::render('Auth/TenantRegister', [
            'plans' => $plans,
            'selected_plan' => $this->selectedPlan($plans),
            'tenant_domain_suffix' => config('tenancy.tenant_domain_suffix', 'sasapp'),
        ]);
    }

    public function store(StoreTenantRegistrationRequest $request)
    {
        $validated = $request->validated();

        $selectedPlan = $this->publicPlans->activePlanBySlug($validated['plan'] ?? null);

        // Paid-but-active selection: no tenant is provisioned yet. The guest is
        // sent to a hosted checkout; provisioning happens only after payment.
        if ($selectedPlan !== null && (float) $selectedPlan->price > 0) {
            return $this->startPaidCheckout($request, $validated, $selectedPlan);
        }

        $domain = $this->subdomainGenerator->forRequest($validated);

        // Single choke point for the no-payment path: paid, inactive or
        // unknown slugs are resolved down to 'trial' so a guest can never
        // provision a paid workspace without payment.
        $planSlug = $this->publicPlans->resolveSignupPlanSlug($validated['plan'] ?? null);

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
                'public_signup' => true,
            ]);
        } catch (InvalidArgumentException $e) {
            Log::warning('Tenant registration failed', [
                'email' => $validated['email'],
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'provisioning' => 'We could not create your workspace. Please try again.',
            ]);
        }

        dispatch(new CreateTenantAdminUser(
            $tenant,
            $this->adminDisplayName($validated),
            $validated['email'],
            $validated['password'],
        ));

        return $this->successResponse($domain, $validated, $tenant);
    }

    private function startPaidCheckout(StoreTenantRegistrationRequest $request, array $validated, Plan $plan)
    {
        $token = Str::random(32);

        $request->session()->put(self::PENDING_REGISTRATION_KEY, [
            'token' => $token,
            'plan' => (string) $plan->slug,
            'payload' => $validated,
        ]);

        $checkout = app(PaymentGatewayInterface::class)->createCheckoutSession([
            'amount' => (float) $plan->price,
            'currency' => strtolower((string) config('currency.base_currency', 'USD')),
            'name' => (string) $plan->name,
            'success_url' => $request->root().'/register/payment/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $request->root().'/register/payment/cancel',
            'customer_email' => $validated['email'],
            'metadata' => [
                'pending_token' => $token,
                'plan' => (string) $plan->slug,
                'email' => $validated['email'],
            ],
        ]);

        return Inertia::location((string) $checkout['url']);
    }

    private function successResponse(string $domain, array $validated, $tenant): Response
    {
        return Inertia::render('Auth/TenantRegisterSuccess', [
            'domain' => $domain,
            'loginUrl' => 'https://'.$domain.'/login',
            'plan' => (string) ($tenant->plan?->name ?? 'Trial'),
            'trialDays' => $tenant->status === 'Trial'
                ? (int) config('tenancy.trial_days', 14)
                : null,
            'email' => $validated['email'],
        ]);
    }

    /**
     * Only surface a plan that is actually listed, so a tampered ?plan= value
     * cannot preselect something the table would not show.
     *
     * @param  array<int, array{slug: string}>  $plans
     */
    private function selectedPlan(array $plans): ?string
    {
        $requested = request()->query('plan');

        if (! is_string($requested) || $requested === '') {
            return null;
        }

        foreach ($plans as $plan) {
            if ($plan['slug'] === $requested) {
                return $requested;
            }
        }

        return null;
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

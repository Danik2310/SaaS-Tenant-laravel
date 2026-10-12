<?php

declare(strict_types=1);

namespace App\Tenants\Http\Controllers\Public;

use App\Billing\Contracts\PaymentGatewayInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreTenantRegistrationRequest;
use App\Models\Plan;
use App\Shared\Contracts\LocationCatalog;
use App\Shared\Contracts\PublicPlanCatalogInterface;
use App\Tenants\Contracts\TenantManagerInterface;
use App\Tenants\Jobs\CreateTenantAdminUser;
use App\Tenants\Support\SubdomainGenerator;
use Illuminate\Support\Facades\Hash;
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

    /**
     * A pending signup older than this is discarded instead of being honoured.
     */
    private const PENDING_SIGNUP_TTL_MINUTES = 60;

    public function __construct(
        private TenantManagerInterface $tenantManager,
        private SubdomainGenerator $subdomainGenerator,
        private PublicPlanCatalogInterface $publicPlans,
        private LocationCatalog $locations,
    ) {}

    public function create(): Response
    {
        $plans = $this->publicPlans->allSignupPlans();

        $cancelled = request()->boolean('cancelled');
        $prefill = null;
        $pending = session()->get(self::PENDING_REGISTRATION_KEY);

        if ($cancelled && is_array($pending) && $this->isPendingFresh($pending)) {
            $prefill = $this->prefill($pending);
        }

        $states = $this->locations->statesByCountry();

        return Inertia::render('Auth/TenantRegister', [
            'plans' => $plans,
            'selected_plan' => $this->selectedPlan($plans),
            'tenant_domain_suffix' => config('tenancy.tenant_domain_suffix', 'sasapp'),
            'trial_days' => (int) config('tenancy.trial_days', 14),
            'cancelled' => $cancelled,
            'prefill' => $prefill,
            'countries' => $this->locations->countries(),
            'states' => $states,
        ]);
    }

    public function store(StoreTenantRegistrationRequest $request)
    {
        $validated = $request->validated();

        // Hash at the boundary so the plaintext password is never written to
        // the session or serialized into a queued job.
        $validated['password'] = Hash::make($validated['password']);
        unset($validated['password_confirmation']);

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
            'expires_at' => now()->addMinutes(self::PENDING_SIGNUP_TTL_MINUTES)->getTimestamp(),
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
            ],
        ]);

        return Inertia::location((string) $checkout['url']);
    }

    /**
     * Keep the refill data minimal and credential-free: the pending payload is
     * never sent back to the client wholesale.
     *
     * @param  array{plan?: string, payload?: array<string, mixed>}  $pending
     * @return array<string, string|null>
     */
    private function prefill(array $pending): array
    {
        $payload = is_array($pending['payload'] ?? null) ? $pending['payload'] : [];

        return array_filter([
            'plan' => isset($pending['plan']) ? (string) $pending['plan'] : null,
            'name' => isset($payload['name']) ? (string) $payload['name'] : null,
            'email' => isset($payload['email']) ? (string) $payload['email'] : null,
            'subdomain' => isset($payload['subdomain']) ? (string) $payload['subdomain'] : null,
        ], fn ($value) => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $pending
     */
    private function isPendingFresh(array $pending): bool
    {
        $expiresAt = $pending['expires_at'] ?? null;

        return ! is_int($expiresAt) || $expiresAt > now()->getTimestamp();
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

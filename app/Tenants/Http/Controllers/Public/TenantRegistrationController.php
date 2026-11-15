<?php

declare(strict_types=1);

namespace App\Tenants\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreTenantRegistrationRequest;
use App\Models\Plan;
use App\Models\Tenant;
use App\Shared\Contracts\LocationCatalog;
use App\Shared\Contracts\PaymentGatewayInterface;
use App\Shared\Contracts\PublicPlanCatalogInterface;
use App\Shared\Support\OAuthCallbackError;
use App\Shared\Support\OAuthProviderLinks;
use App\Tenants\Contracts\TenantManagerInterface;
use App\Tenants\Jobs\CreateTenantAdminUser;
use App\Tenants\Support\PendingOAuthSignup;
use App\Tenants\Support\SubdomainGenerator;
use App\Tenants\Support\TenantVerificationCooldown;
use App\Tenants\Support\TenantVerificationUrl;
use Illuminate\Http\RedirectResponse;
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
            // Google-first signup: the button on the plan step, and the
            // verified identity when the callback has already run.
            'providers' => OAuthProviderLinks::forSignup(),
            'oauthIdentity' => PendingOAuthSignup::identity(),
            // Same-host `?oauth_error=` from the signup callback.
            'oauthError' => OAuthCallbackError::fromCode(request()->query('oauth_error')),
        ]);
    }

    public function store(StoreTenantRegistrationRequest $request)
    {
        $validated = $request->validated();
        $identity = PendingOAuthSignup::identity();

        if ($identity !== null) {
            // The verified Google address wins over the submitted payload, and
            // a passwordless account gets no hash at all.
            $validated['email'] = $identity['email'];
            $validated['password'] = null;
        } else {
            // Hash at the boundary so the plaintext password is never written
            // to the session or serialized into a queued job.
            $validated['password'] = Hash::make($validated['password']);
        }

        unset($validated['password_confirmation']);

        $selectedPlan = $this->publicPlans->activePlanBySlug($validated['plan'] ?? null);

        // Paid-but-active selection: no tenant is provisioned yet. The guest is
        // sent to a hosted checkout; provisioning happens only after payment.
        if ($selectedPlan !== null && (float) $selectedPlan->price > 0) {
            return $this->startPaidCheckout($request, $validated, $selectedPlan, $identity);
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
            $this->adminDisplayName($validated, $identity),
            $validated['email'],
            $validated['password'],
        ));

        // Provisioning succeeded: the parked Google identity must not silently
        // apply to a later signup in the same browser.
        PendingOAuthSignup::forget();

        return $this->pendingRedirect($tenant, $validated['email']);
    }

    /**
     * Forget the verified Google identity parked by the signup callback, so the
     * visitor can complete the same form with an email and password instead.
     */
    public function forgetOAuth(): RedirectResponse
    {
        PendingOAuthSignup::forget();

        // Same-host relative redirect; see signupCallback().
        return redirect('/register');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array{name: string, email: string}|null  $identity
     */
    private function startPaidCheckout(StoreTenantRegistrationRequest $request, array $validated, Plan $plan, ?array $identity = null)
    {
        $token = Str::random(32);

        $request->session()->put(self::PENDING_REGISTRATION_KEY, [
            'token' => $token,
            'plan' => (string) $plan->slug,
            'payload' => $validated,
            // The session identity can expire before the checkout returns, so
            // the verified Google name travels with the pending signup.
            'admin_name' => $identity['name'] ?? null,
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

    /**
     * Send the owner to the signed confirmation-waiting screen instead of
     * rendering it in the POST response: the waiting screen must survive a
     * refresh, a resend redirect, and the verification link's own redirect.
     */
    private function pendingRedirect(Tenant $tenant, string $email): RedirectResponse
    {
        $url = TenantVerificationUrl::pendingFor($tenant, $email);

        if ($url === null) {
            return redirect()->route('register.tenant');
        }

        // The initial signup mail is already on its way (or queued). Start the
        // cooldown now so the first resend cannot duplicate it immediately.
        TenantVerificationCooldown::start($tenant, $email);

        return redirect()->to($url)->with('status', 'verification-link-sent');
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

    /**
     * @param  array<string, mixed>  $validated
     * @param  array{name: string, email: string}|null  $identity
     */
    private function adminDisplayName(array $validated, ?array $identity = null): string
    {
        // A Google signup never asks for the person's name, so the verified
        // profile name becomes the admin display name instead of the company.
        if ($identity !== null && $identity['name'] !== '') {
            return $identity['name'];
        }

        $contactName = trim(implode(' ', array_filter([
            $validated['first_name'] ?? null,
            $validated['last_name'] ?? null,
        ])));

        return $contactName !== '' ? $contactName : $validated['company_name'];
    }
}

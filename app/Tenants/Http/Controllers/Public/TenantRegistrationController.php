<?php

declare(strict_types=1);

namespace App\Tenants\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreTenantRegistrationRequest;
use App\Tenants\Contracts\TenantManagerInterface;
use App\Tenants\Jobs\CreateTenantAdminUser;
use App\Tenants\Support\SubdomainGenerator;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class TenantRegistrationController extends Controller
{
    public function __construct(
        private TenantManagerInterface $tenantManager,
        private SubdomainGenerator $subdomainGenerator,
    ) {}

    public function create(): Response
    {
        return Inertia::render('Auth/TenantRegister');
    }

    public function store(StoreTenantRegistrationRequest $request)
    {
        $validated = $request->validated();
        $domain = $this->subdomainGenerator->generate($validated['company_name']);

        try {
            $tenant = $this->tenantManager->provision([
                'name' => $validated['company_name'],
                'email' => $validated['email'],
                'domain' => $domain,
                'plan' => 'trial',
                'phone' => $validated['phone'] ?? null,
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
            $validated['name'],
            $validated['email'],
            $validated['password'],
        ));

        return Inertia::render('Auth/TenantRegisterSuccess', [
            'domain' => $domain,
            'loginUrl' => 'https://'.$domain.'/login',
            'plan' => 'Trial',
            'trialDays' => (int) config('tenancy.trial_days', 14),
            'email' => $validated['email'],
        ]);
    }
}

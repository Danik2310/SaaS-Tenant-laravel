<?php

namespace App\Http\Requests\Public;

use App\Models\Domain;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class StoreTenantRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_name' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255',
            'password' => ['required', 'confirmed', Password::defaults()],
            'subdomain' => [
                'nullable',
                'string',
                'max:63',
                'lowercase',
                'regex:/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/',
            ],
            'phone' => 'nullable|string|max:50',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'address_line1' => 'nullable|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'plan' => 'nullable|string|max:64',
            'terms' => 'required|accepted',
            'website' => 'nullable|string|prohibited',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $data = $validator->validated();

                if (! empty($data['email'])) {
                    $this->checkEmail($data['email'], $validator);
                }
            },
            // Kept independent of the email closure so a plan error never
            // couples to (or masks) the email conflict check.
            function (Validator $validator) {
                $data = $validator->validated();

                if (! empty($data['plan'])) {
                    $this->checkPlan($data['plan'], $validator);
                }
            },
            // Field-level duplicate guard: reject a taken workspace address
            // before TenantBuilder::withDomain() would throw a generic error.
            function (Validator $validator) {
                $data = $validator->validated();

                if (empty($data['subdomain'])) {
                    return;
                }

                $domain = strtolower((string) $data['subdomain'])
                    .'.'.config('tenancy.tenant_domain_suffix', 'sasapp');

                if (Domain::where('domain', $domain)->exists()) {
                    $validator->errors()->add('subdomain', 'That workspace address is already taken.');
                }
            },
        ];
    }

    private function checkEmail(string $email, Validator $validator): void
    {
        $existing = Tenant::withTrashed()->where('email', $email)->first();

        if (! $existing) {
            return;
        }

        $validator->errors()->add('email', "A tenant with the email '{$email}' already exists.");
    }

    /**
     * Reject tampered slugs (unknown or inactive) outright. Paid-but-active
     * slugs are accepted here and clamped to 'trial' downstream by the plan
     * catalog, so a Buy click never dead-ends the guest.
     */
    private function checkPlan(string $planSlug, Validator $validator): void
    {
        $plan = Plan::where('slug', $planSlug)->first();

        if (! $plan || $plan->status !== 'active') {
            $validator->errors()->add('plan', 'That plan is not available for signup.');
        }
    }
}

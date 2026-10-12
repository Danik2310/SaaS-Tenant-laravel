<?php

namespace App\Http\Requests\Public;

use App\Models\Domain;
use App\Models\Plan;
use App\Models\Tenant;
use App\Shared\Contracts\LocationCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class StoreTenantRegistrationRequest extends FormRequest
{
    public function __construct(
        private LocationCatalog $locations,
    ) {
        parent::__construct();
    }

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
            'city' => [
                'nullable',
                'string',
                'max:100',
                "regex:/^[\p{L}][\p{L}\p{M}'\-. ]*$/u",
            ],
            'state' => 'nullable|string|max:100',
            'postal_code' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[A-Za-z0-9][A-Za-z0-9 -]*$/',
            ],
            'country' => [
                'nullable',
                'string',
                'max:100',
                Rule::in($this->countryNames()),
            ],
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
            // Location integrity: a State must belong to the chosen Country and
            // a City must be tied to both, so a partially-filled address never
            // lands in the database.
            fn (Validator $validator) => $this->checkLocation($validator),
        ];
    }

    private function checkEmail(string $email, Validator $validator): void
    {
        // A generic, non-echoing message so a guest cannot probe which emails
        // have accounts (or enumerate previously registered workspaces).
        $exists = Tenant::where('email', $email)->exists();

        if ($exists) {
            $validator->errors()->add('email', 'An account already exists for that email.');
        }
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

    private function checkLocation(Validator $validator): void
    {
        $data = $validator->validated();
        $country = $data['country'] ?? null;
        $state = $data['state'] ?? null;
        $city = $data['city'] ?? null;

        if (empty($state) && empty($city)) {
            return;
        }

        if (empty($country)) {
            $validator->errors()->add(
                'country',
                'Select the country before filling in the state or city.'
            );

            return;
        }

        if (! empty($state) && ! $this->locations->stateBelongsToCountry($country, $state)) {
            $validator->errors()->add(
                'state',
                'That is not a recognized state or province for the selected country.'
            );
        }

        if (! empty($city) && ! $this->locations->cityMatches($country, $state ?: null, $city)) {
            $validator->errors()->add(
                'city',
                empty($state)
                    ? 'That city is not recognized for the selected country.'
                    : 'That city is not recognized for the selected state.'
            );
        }
    }

    private function countryNames(): array
    {
        return array_column($this->locations->countries(), 'name');
    }
}

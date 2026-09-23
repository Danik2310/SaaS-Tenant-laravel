<?php

namespace App\Http\Requests\Public;

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
            'phone' => 'nullable|string|max:50',
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

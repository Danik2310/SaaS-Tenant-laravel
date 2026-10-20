<?php

namespace App\Http\Requests\Tenant;

use App\Billing\Factories\ResourceEnforcementFactory;
use App\Models\User;
use App\Shared\Exceptions\PlanLimitExceededException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = tenant();

        if (! $tenant) {
            return false;
        }

        $strategy = ResourceEnforcementFactory::make($tenant);
        $limit = $strategy->maxUsers();

        if ($limit !== PHP_INT_MAX && User::count() >= $limit) {
            throw new PlanLimitExceededException('users', $limit);
        }

        return true;
    }

    /**
     * Normalize the email before validation so the stored value matches the
     * lowercase form the unique index and lookup queries assume.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('email') && is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class)->whereNull('deleted_at'),
            ],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ];
    }
}

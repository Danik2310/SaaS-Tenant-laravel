<?php

namespace App\Http\Requests\Tenant;

use App\Billing\Factories\ResourceEnforcementFactory;
use App\Shared\Exceptions\PlanLimitExceededException;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
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

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ];
    }
}

<?php

namespace App\Http\Requests\Public;

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
}

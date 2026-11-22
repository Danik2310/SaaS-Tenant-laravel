<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Billing;

use App\Models\Tenant;
use App\Shared\Constants\PermissionNames;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the tenant's billing profile edit. This is an explicit billing
 * action: it never touches the tenant user's personal profile fields.
 */
class UpdateBillingProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && tenant() instanceof Tenant && $user->can(PermissionNames::MANAGE_PAYMENTS);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'billing_email' => ['required', 'email', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'tax_id' => ['nullable', 'string', 'max:100'],
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use App\Shared\Constants\PermissionNames;
use Illuminate\Foundation\Http\FormRequest;

class TogglePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->can(PermissionNames::EDIT_PAYMENT_METHODS);
    }

    public function rules(): array
    {
        return [
            'active' => 'sometimes|boolean',
        ];
    }
}

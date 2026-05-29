<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class TogglePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->can('manage payment methods');
    }

    public function rules(): array
    {
        return [
            'active' => 'sometimes|boolean',
        ];
    }
}

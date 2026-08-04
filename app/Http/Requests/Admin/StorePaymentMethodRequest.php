<?php

namespace App\Http\Requests\Admin;

use App\Shared\Constants\PermissionNames;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->can(PermissionNames::CREATE_PAYMENT_METHODS);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:mysql_central.payment_methods,name',
            'provider' => 'required|in:stripe,paypal,other',
            'api_key' => 'nullable|string|min:10',
            'secret_key' => 'nullable|string|min:10',
            'mode' => 'required|in:test,live',
            'active' => 'boolean',
        ];
    }
}

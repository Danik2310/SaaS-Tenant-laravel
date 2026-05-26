<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->can('manage payment methods');
    }

    public function rules(): array
    {
        $methodId = $this->route('id');

        return [
            'name' => 'required|string|max:255|unique:payment_methods,name,'.$methodId,
            'provider' => 'required|in:stripe,paypal,other',
            'api_key' => 'nullable|string|min:10',
            'secret_key' => 'nullable|string|min:10',
            'mode' => 'required|in:test,live',
            'active' => 'boolean',
        ];
    }
}

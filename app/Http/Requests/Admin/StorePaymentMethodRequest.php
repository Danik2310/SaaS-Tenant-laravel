<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:payment_methods,name',
            'provider' => 'required|in:stripe,paypal,other',
            'api_key' => 'nullable|string|min:10',
            'secret_key' => 'nullable|string|min:10',
            'mode' => 'required|in:test,live',
            'active' => 'boolean',
        ];
    }
}

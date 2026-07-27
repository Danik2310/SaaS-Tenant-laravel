<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubscriptionPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->can('manage subscriptions');
    }

    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|string|in:stripe,bank_transfer,cash,manual',
            'reference' => 'nullable|string|max:255',
            'status' => 'required|string|in:pending,completed,failed,refunded',
            'paid_at' => 'required|date',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}

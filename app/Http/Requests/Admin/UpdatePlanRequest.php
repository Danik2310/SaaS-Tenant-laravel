<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->can('manage plans');
    }

    public function rules(): array
    {
        $planId = $this->route('id');

        return [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:mysql_central.plans,slug,'.$planId,
            'status' => 'sometimes|required|string|in:active,inactive',
            'price' => 'required|numeric|min:0',
            'duration_months' => 'sometimes|required|integer|min:1',
            'max_users' => 'nullable|integer|min:1',
            'max_storage' => 'nullable|integer|min:0',
            'max_warehouses' => 'nullable|integer|min:1',
            'max_categories' => 'nullable|integer|min:1',
            'max_products' => 'nullable|integer|min:1',
            'features' => 'nullable|string',
        ];
    }
}

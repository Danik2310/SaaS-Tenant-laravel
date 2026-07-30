<?php

namespace App\Http\Requests\Admin;

use App\Shared\Contracts\ServerDiskInfo;
use Illuminate\Foundation\Http\FormRequest;

class StorePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->can('manage plans');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:mysql_central.plans,slug',
            'status' => 'required|string|in:active,inactive',
            'price' => 'required|numeric|min:0',
            'duration_months' => 'required|integer|min:1',
            'max_users' => 'nullable|integer|min:1',
            'max_storage' => 'nullable|integer|min:0',
            'max_warehouses' => 'nullable|integer|min:1',
            'max_categories' => 'nullable|integer|min:1',
            'max_products' => 'nullable|integer|min:1',
            'features' => 'nullable|string',
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                $maxStorage = $this->input('max_storage');

                if ($maxStorage === null) {
                    return;
                }

                $serverDisk = app(ServerDiskInfo::class);
                $totalGb = $serverDisk->totalGb();

                if ($totalGb === null) {
                    return;
                }

                $requestedGb = (int) $maxStorage / 1024;

                if ($requestedGb > $totalGb) {
                    $validator->errors()->add(
                        'max_storage',
                        "Max storage ({$requestedGb} GB) exceeds server capacity ({$totalGb} GB)."
                    );
                }
            },
        ];
    }
}

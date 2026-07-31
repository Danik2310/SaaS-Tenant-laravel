<?php

namespace App\Http\Requests\Admin;

use App\Shared\Contracts\ServerDiskInfo;
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
            'features' => $this->featureFlagsRule(),
        ];
    }

    private function featureFlagsRule(): array
    {
        $known = array_keys(config('plan_features'));

        return [
            'nullable',
            function ($attribute, $value, $fail) use ($known) {
                $keys = is_array($value)
                    ? $value
                    : array_filter(array_map('trim', explode(',', (string) $value)));

                $unknown = array_values(array_diff($keys, $known));

                if ($unknown !== []) {
                    $fail('Unknown feature flag(s): '.implode(', ', $unknown).'. Allowed flags: '.implode(', ', $known).'.');
                }
            },
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

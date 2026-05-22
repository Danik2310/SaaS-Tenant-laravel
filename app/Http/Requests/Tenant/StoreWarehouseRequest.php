<?php

namespace App\Http\Requests\Tenant;

use App\Exceptions\PlanLimitExceededException;
use App\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;

class StoreWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! tenant()) {
            return false;
        }

        $currentCount = Warehouse::count();
        $limit = tenant()->getLimit('warehouses');

        if ($limit !== PHP_INT_MAX && $currentCount >= $limit) {
            throw new PlanLimitExceededException('warehouses', $limit);
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
        ];
    }
}

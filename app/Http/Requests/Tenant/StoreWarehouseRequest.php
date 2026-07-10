<?php

namespace App\Http\Requests\Tenant;

use App\Billing\Factories\ResourceEnforcementFactory;
use App\Models\Warehouse;
use App\Shared\Exceptions\PlanLimitExceededException;
use Illuminate\Foundation\Http\FormRequest;

class StoreWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->user() === null || ! tenant()) {
            return false;
        }

        if (! $this->user()->can('manage inventory')) {
            return false;
        }

        $strategy = ResourceEnforcementFactory::make(tenant());
        $limit = $strategy->maxWarehouses();

        $currentCount = Warehouse::count();

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

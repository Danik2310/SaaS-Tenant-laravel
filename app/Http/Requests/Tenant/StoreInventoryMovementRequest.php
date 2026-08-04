<?php

namespace App\Http\Requests\Tenant;

use App\Shared\Constants\PermissionNames;
use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->user() === null || tenant() === null) {
            return false;
        }

        return $this->user()->can(PermissionNames::MANAGE_INVENTORY);
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'type' => 'required|in:in,out,adjustment',
            'quantity' => 'required|integer|min:1',
            'reason' => 'nullable|string|max:500',
        ];
    }
}

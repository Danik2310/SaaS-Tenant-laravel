<?php

namespace App\Http\Requests\Tenant;

use App\Shared\Constants\PermissionNames;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWarehouseRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
        ];
    }
}

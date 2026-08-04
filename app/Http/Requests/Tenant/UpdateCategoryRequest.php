<?php

namespace App\Http\Requests\Tenant;

use App\Shared\Constants\PermissionNames;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->user() === null || tenant() === null) {
            return false;
        }

        return $this->user()->can(PermissionNames::MANAGE_CATEGORIES);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
        ];
    }
}

<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return tenant() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
        ];
    }
}

<?php

namespace App\Http\Requests\Tenant;

use App\Exceptions\PlanLimitExceededException;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! tenant()) {
            return false;
        }

        $currentCount = Category::count();
        $limit = tenant()->getLimit('categories');

        if ($limit !== PHP_INT_MAX && $currentCount >= $limit) {
            throw new PlanLimitExceededException('categories', $limit);
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
        ];
    }
}

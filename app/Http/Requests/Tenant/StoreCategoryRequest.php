<?php

namespace App\Http\Requests\Tenant;

use App\Billing\Factories\ResourceEnforcementFactory;
use App\Models\Category;
use App\Shared\Exceptions\PlanLimitExceededException;
use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->user() === null || ! tenant()) {
            return false;
        }

        if (! $this->user()->can('manage categories')) {
            return false;
        }

        $strategy = ResourceEnforcementFactory::make(tenant());
        $limit = $strategy->maxCategories();

        $currentCount = Category::count();

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
